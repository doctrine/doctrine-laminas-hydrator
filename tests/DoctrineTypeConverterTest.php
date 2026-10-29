<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use BCMath\Number;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\Laminas\Hydrator\DoctrineTypeConverter;
use Doctrine\Persistence\Mapping\ClassMetadata;
use DoctrineTest\Laminas\Hydrator\Assets\PrefixingTypeConverter;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleEntityWithGenericField;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleEnum;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleStringEnum;
use DoctrineTest\Laminas\Hydrator\Support\ClassMetadataWithFieldMapping;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use ValueError;

use function class_exists;
use function str_ends_with;

#[CoversClass(DoctrineTypeConverter::class)]
class DoctrineTypeConverterTest extends HydratorTestCase
{
    private const int TIMESTAMP = 1_522_353_676;

    private const array DATE_TIME_TYPES = [
        'date',
        'time',
        'datetime',
        'datetime_utc',
        'datetimetz',
    ];

    /** @return iterable<string, array{string|null, mixed, mixed}> */
    public static function hydrateProvider(): iterable
    {
        $dateTime = (new DateTime())->setTimestamp(self::TIMESTAMP);

        foreach (self::DATE_TIME_TYPES as $baseType) {
            foreach ([$baseType, $baseType . '_immutable'] as $type) {
                $expected = str_ends_with($type, '_immutable') ? DateTimeImmutable::createFromMutable($dateTime) : $dateTime;

                yield $type . ' from timestamp' => [$type, self::TIMESTAMP, $expected];
                yield $type . ' from string' => [$type, $dateTime->format('Y-m-d\TH:i:s\.u'), $expected];
                yield $type . ' from DateTime' => [$type, clone $dateTime, $expected];
                yield $type . ' from DateTimeImmutable' => [$type, DateTimeImmutable::createFromMutable($dateTime), $expected];
                yield $type . ' from empty string' => [$type, '', null];
            }
        }

        $negativeInterval         = new DateInterval('P1D');
        $negativeInterval->invert = 1;

        yield 'dateinterval' => ['dateinterval', 'P1Y2M3DT4H5M6S', new DateInterval('P1Y2M3DT4H5M6S')];
        yield 'dateinterval with positive sign' => ['dateinterval', '+P1D', new DateInterval('P1D')];
        yield 'dateinterval with negative sign' => ['dateinterval', '-P1D', $negativeInterval];
        yield 'dateinterval from DateInterval' => ['dateinterval', new DateInterval('P1D'), new DateInterval('P1D')];
        yield 'dateinterval from empty string' => ['dateinterval', '', null];

        yield 'integer' => ['integer', '123465', 123465];
        yield 'smallint' => ['smallint', '123465', 123465];
        yield 'float' => ['float', '123.465', 123.465];
        yield 'smallfloat' => ['smallfloat', '123.465', 123.465];
        yield 'boolean from boolean' => ['boolean', true, true];
        yield 'boolean from integer' => ['boolean', 1, true];

        foreach (['string', 'ascii_string', 'text', 'guid', 'enum', 'bigint', 'decimal'] as $type) {
            yield $type => [$type, '123.45', '123.45'];
            yield $type . ' from integer' => [$type, 12345, '12345'];
        }

        // Types without conversion
        foreach (['binary', 'blob', 'json', 'jsonb', 'json_object', 'jsonb_object', 'simple_array', 'custom'] as $type) {
            yield $type => [$type, ['a' => 'value'], ['a' => 'value']];
        }

        yield 'enum returned by a strategy' => ['string', SimpleEnum::One, SimpleEnum::One];
        yield 'null' => ['integer', null, null];
        yield 'null without type' => [null, null, null];
    }

    #[DataProvider('hydrateProvider')]
    public function testHydrate(string|null $type, mixed $value, mixed $expected): void
    {
        $converted = (new DoctrineTypeConverter())->hydrate($value, $this->createFieldMetadata($type), 'genericField');

        if ($expected instanceof DateTimeInterface || $expected instanceof DateInterval) {
            $this->assertInstanceOf($expected::class, $converted);
            $this->assertEquals($expected, $converted);
        } else {
            $this->assertSame($expected, $converted);
        }
    }

    public function testHydrateNumber(): void
    {
        $converter = new DoctrineTypeConverter();
        $metadata  = $this->createFieldMetadata('number');

        foreach (['123.45', 12345, 123.45] as $value) {
            $converted = $converter->hydrate($value, $metadata, 'genericField');

            // BCMath\Number requires PHP 8.4 with the bcmath extension, otherwise values are kept as they are
            if (class_exists(Number::class)) {
                $this->assertInstanceOf(Number::class, $converted);
                $this->assertSame((string) $value, (string) $converted);
            } else {
                $this->assertSame($value, $converted);
            }
        }
    }

    /** @return iterable<string, array{array<string, mixed>|object, mixed, SimpleEnum|SimpleStringEnum}> */
    public static function enumProvider(): iterable
    {
        // Doctrine ORM 2 and ODM return the field mapping as array, ORM 3 as object
        yield 'string backed, array mapping' => [['enumType' => SimpleStringEnum::class], 'H', SimpleStringEnum::Hearts];
        yield 'string backed, object mapping' => [(object) ['enumType' => SimpleStringEnum::class], 'S', SimpleStringEnum::Spades];
        yield 'int backed' => [['enumType' => SimpleEnum::class], 1, SimpleEnum::One];
        yield 'int backed from string' => [['enumType' => SimpleEnum::class], '2', SimpleEnum::Two];
        yield 'enum instance' => [['enumType' => SimpleEnum::class], SimpleEnum::Two, SimpleEnum::Two];
    }

    /** @param array<string, mixed>|object $mapping */
    #[DataProvider('enumProvider')]
    public function testHydrateEnumFromFieldMapping(array|object $mapping, mixed $value, SimpleEnum|SimpleStringEnum $expected): void
    {
        $converted = (new DoctrineTypeConverter())->hydrate($value, $this->createMetadataWithFieldMapping($mapping), 'genericField');

        $this->assertSame($expected, $converted);
    }

    public function testHydrateInvalidEnumValueThrowsException(): void
    {
        $metadata = $this->createMetadataWithFieldMapping(['enumType' => SimpleStringEnum::class]);

        $this->expectException(ValueError::class);

        (new DoctrineTypeConverter())->hydrate('X', $metadata, 'genericField');
    }

    public function testExtractReturnsValuesAsTheyAreByDefault(): void
    {
        $converter = new DoctrineTypeConverter();
        $values    = [SimpleStringEnum::Hearts, new DateTime(), new DateTimeImmutable(), new DateInterval('P1D'), 'value'];

        foreach ($values as $value) {
            $this->assertSame($value, $converter->extract($value, $this->createFieldMetadata('datetime'), 'genericField'));
        }
    }

    public function testExtractEnums(): void
    {
        $converter = new DoctrineTypeConverter(extractEnums: true);
        $metadata  = $this->createFieldMetadata('string');
        $dateTime  = new DateTime();

        $this->assertSame('H', $converter->extract(SimpleStringEnum::Hearts, $metadata, 'genericField'));
        $this->assertSame(2, $converter->extract(SimpleEnum::Two, $metadata, 'genericField'));
        $this->assertSame($dateTime, $converter->extract($dateTime, $metadata, 'genericField'));
    }

    /** @return iterable<string, array{string, DateTimeInterface|DateInterval, string}> */
    public static function extractDateTimeProvider(): iterable
    {
        $dateTime = new DateTimeImmutable('2026-10-29 14:30:15', new DateTimeZone('+01:00'));

        yield 'date' => ['date', DateTime::createFromImmutable($dateTime), '2026-10-29'];
        yield 'date_immutable' => ['date_immutable', $dateTime, '2026-10-29'];
        yield 'time' => ['time', $dateTime, '14:30:15'];
        yield 'time_immutable' => ['time_immutable', $dateTime, '14:30:15'];

        foreach (['datetime', 'datetime_utc', 'datetimetz'] as $type) {
            yield $type => [$type, DateTime::createFromImmutable($dateTime), '2026-10-29T14:30:15+01:00'];
            yield $type . '_immutable' => [$type . '_immutable', $dateTime, '2026-10-29T14:30:15+01:00'];
        }

        $negativeInterval         = new DateInterval('P1Y2M3DT4H5M6S');
        $negativeInterval->invert = 1;

        yield 'dateinterval' => ['dateinterval', new DateInterval('P1Y2M3DT4H5M6S'), '+P01Y02M03DT04H05M06S'];
        yield 'negative dateinterval' => ['dateinterval', $negativeInterval, '-P01Y02M03DT04H05M06S'];
    }

    #[DataProvider('extractDateTimeProvider')]
    public function testExtractDateTimes(string $type, DateTimeInterface|DateInterval $value, string $expected): void
    {
        $converter = new DoctrineTypeConverter(extractDateTimes: true);

        $this->assertSame($expected, $converter->extract($value, $this->createFieldMetadata($type), 'genericField'));
        $this->assertSame(SimpleEnum::One, $converter->extract(SimpleEnum::One, $this->createFieldMetadata($type), 'genericField'));
    }

    /** @return iterable<string, array{string, DateTimeInterface|DateInterval}> */
    public static function roundTripProvider(): iterable
    {
        yield 'datetime' => ['datetime', new DateTime('2026-10-29 14:30:15')];
        yield 'datetimetz_immutable' => ['datetimetz_immutable', new DateTimeImmutable('2026-10-29 14:30:15+05:00')];
        yield 'date' => ['date', new DateTime('2026-10-29 00:00:00')];
        yield 'dateinterval' => ['dateinterval', new DateInterval('P1Y2M3DT4H5M6S')];
    }

    #[DataProvider('roundTripProvider')]
    public function testExtractedValuesCanBeHydratedAgain(string $type, DateTimeInterface|DateInterval $value): void
    {
        $converter = new DoctrineTypeConverter(extractEnums: true, extractDateTimes: true);
        $metadata  = $this->createFieldMetadata($type);

        $hydrated = $converter->hydrate($converter->extract($value, $metadata, 'genericField'), $metadata, 'genericField');

        $this->assertEquals($value, $hydrated);
    }

    public function testRegisteredConverterIsUsedForItsType(): void
    {
        $converter = new DoctrineTypeConverter(typeConverters: ['uuid' => new PrefixingTypeConverter()]);
        $metadata  = $this->createFieldMetadata('uuid');

        $this->assertSame('hydrated genericField: abc', $converter->hydrate('abc', $metadata, 'genericField'));
        $this->assertSame('extracted genericField: abc', $converter->extract('abc', $metadata, 'genericField'));

        // Null is passed to the registered converter as well
        $this->assertSame('hydrated genericField: null', $converter->hydrate(null, $metadata, 'genericField'));
    }

    public function testRegisteredConverterReplacesBuiltInConversion(): void
    {
        $converter = new DoctrineTypeConverter(extractDateTimes: true, typeConverters: ['datetime' => new PrefixingTypeConverter()]);
        $metadata  = $this->createFieldMetadata('datetime');

        $this->assertSame('hydrated genericField: 2026-10-29', $converter->hydrate('2026-10-29', $metadata, 'genericField'));
        $this->assertSame('extracted genericField: DateTime', $converter->extract(new DateTime(), $metadata, 'genericField'));
    }

    public function testRegisteredConverterIsNotUsedForOtherTypes(): void
    {
        $converter = new DoctrineTypeConverter(typeConverters: ['uuid' => new PrefixingTypeConverter()]);

        $this->assertSame(123, $converter->hydrate('123', $this->createFieldMetadata('integer'), 'genericField'));
        $this->assertSame('value', $converter->hydrate('value', $this->createFieldMetadata(null), 'genericField'));
    }

    public function testFieldsMappedToEnumsAreNotAffectedByRegisteredConverters(): void
    {
        // The metadata has the type "string" and the enum type SimpleStringEnum
        $converter = new DoctrineTypeConverter(typeConverters: ['string' => new PrefixingTypeConverter()]);
        $metadata  = $this->createMetadataWithFieldMapping(['enumType' => SimpleStringEnum::class]);

        $this->assertSame(SimpleStringEnum::Hearts, $converter->hydrate('H', $metadata, 'genericField'));
    }

    public function testRegisteredConvertersMustImplementTheInterface(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The converter for type "uuid" must implement');

        /** @phpstan-ignore argument.type */
        new DoctrineTypeConverter(typeConverters: ['uuid' => new stdClass()]);
    }

    private function createFieldMetadata(string|null $type): ClassMetadata
    {
        return $this->createMetadata(SimpleEntityWithGenericField::class, ['id' => 'integer', 'genericField' => $type]);
    }

    /** @param array<string, mixed>|object $mapping */
    private function createMetadataWithFieldMapping(array|object $mapping): ClassMetadata
    {
        $metadata = $this->createStub(ClassMetadataWithFieldMapping::class);
        $metadata->method('hasField')->willReturn(true);
        $metadata->method('getTypeOfField')->willReturn('string');
        $metadata->method('getFieldMapping')->willReturn($mapping);

        return $metadata;
    }
}
