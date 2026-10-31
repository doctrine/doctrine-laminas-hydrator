<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\Laminas\Hydrator\DoctrineTypeConverter;
use Doctrine\Laminas\Hydrator\TypeConverterInterface;
use Doctrine\Persistence\Mapping\ClassMetadata;
use DoctrineTest\Laminas\Hydrator\Assets\PrefixingTypeConverter;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleEntityWithGenericField;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

use function sprintf;

#[CoversClass(DoctrineObject::class)]
class TypeConversionTest extends HydratorTestCase
{
    /**
     * Representative conversions, all conversions are covered by DoctrineTypeConverterTest
     *
     * @return iterable<string, array{string|null, mixed, mixed}>
     */
    private static function conversions(): iterable
    {
        yield 'integer' => ['integer', '123465', 123465];
        yield 'string from integer' => ['string', 12345, '12345'];
        yield 'boolean' => ['boolean', 1, true];
        yield 'datetime' => ['datetime', '2026-10-29 14:30:15', new DateTime('2026-10-29 14:30:15')];
        yield 'datetime_immutable' => ['datetime_immutable', '2026-10-29 14:30:15', new DateTimeImmutable('2026-10-29 14:30:15')];
        yield 'datetime from empty string' => ['datetime', '', null];
        yield 'null without type' => [null, null, null];
    }

    /** @return iterable<string, array{bool, string|null, mixed, mixed}> */
    public static function conversionProvider(): iterable
    {
        foreach (self::conversions() as $name => [$type, $value, $expected]) {
            yield sprintf('%s, by value', $name) => [true, $type, $value, $expected];
            yield sprintf('%s, by reference', $name) => [false, $type, $value, $expected];
        }
    }

    #[DataProvider('conversionProvider')]
    public function testHydrateConvertsValue(bool $byValue, string|null $type, mixed $value, mixed $expected): void
    {
        $metadata = $this->createMetadata(SimpleEntityWithGenericField::class, ['id' => 'integer', 'genericField' => $type]);
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);

        $entity = $hydrator->hydrate(['genericField' => $value], new SimpleEntityWithGenericField());

        if ($expected instanceof DateTimeInterface) {
            $this->assertInstanceOf($expected::class, $entity->getGenericField());
            $this->assertEquals($expected, $entity->getGenericField());
        } else {
            $this->assertSame($expected, $entity->getGenericField());
        }
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function extractDateTimesProvider(): iterable
    {
        yield 'by value' => [true, false];
        yield 'by value, extracting date times' => [true, true];
        yield 'by reference' => [false, false];
        yield 'by reference, extracting date times' => [false, true];
    }

    #[DataProvider('extractDateTimesProvider')]
    public function testExtractConvertsDateTimesIfEnabled(bool $byValue, bool $extractDateTimes): void
    {
        $date   = new DateTime('2026-10-29 14:30:15');
        $entity = new SimpleEntityWithGenericField();
        $entity->setId(1);
        $entity->setGenericField($date);

        $metadata = $this->createMetadata(SimpleEntityWithGenericField::class, ['id' => 'integer', 'genericField' => 'date']);
        $hydrator = new DoctrineObject(
            $this->createObjectManager([$metadata]),
            $byValue,
            typeConverter: new DoctrineTypeConverter(extractDateTimes: $extractDateTimes),
        );

        $this->assertSame(['id' => 1, 'genericField' => $extractDateTimes ? '2026-10-29' : $date], $hydrator->extract($entity));
    }

    #[DataProvider('byValueProvider')]
    public function testUsesConverterRegisteredForCustomType(bool $byValue): void
    {
        $metadata = $this->createMetadata(SimpleEntityWithGenericField::class, ['id' => 'integer', 'genericField' => 'uuid']);
        $hydrator = new DoctrineObject(
            $this->createObjectManager([$metadata]),
            $byValue,
            typeConverter: new DoctrineTypeConverter(typeConverters: ['uuid' => new PrefixingTypeConverter()]),
        );

        $entity = $hydrator->hydrate(['id' => '1', 'genericField' => 'abc'], new SimpleEntityWithGenericField());

        $this->assertSame(1, $entity->getId());
        $this->assertSame('hydrated genericField: abc', $entity->getGenericField());
        $this->assertSame(
            ['id' => 1, 'genericField' => 'extracted genericField: hydrated genericField: abc'],
            $hydrator->extract($entity),
        );
    }

    #[DataProvider('byValueProvider')]
    public function testUsesCustomTypeConverter(bool $byValue): void
    {
        $typeConverter = new class implements TypeConverterInterface {
            public function hydrate(mixed $value, ClassMetadata $metadata, string $fieldName): mixed
            {
                return 'hydrated ' . $fieldName;
            }

            public function extract(mixed $value, ClassMetadata $metadata, string $fieldName): mixed
            {
                return 'extracted ' . $fieldName;
            }
        };

        $metadata = $this->createMetadata(SimpleEntityWithGenericField::class, ['id' => 'integer', 'genericField' => 'string']);
        $hydrator = new DoctrineObject($this->createObjectManager([$metadata]), $byValue, typeConverter: $typeConverter);

        $entity = $hydrator->hydrate(['genericField' => 'value'], new SimpleEntityWithGenericField());

        $this->assertSame('hydrated genericField', $entity->getGenericField());
        $this->assertSame(['id' => 'extracted id', 'genericField' => 'extracted genericField'], $hydrator->extract($entity));
    }
}
