<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Laminas\Hydrator\DoctrineObject;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleEntityWithGenericField;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

use function sprintf;
use function str_ends_with;

#[CoversClass(DoctrineObject::class)]
class TypeConversionTest extends HydratorTestCase
{
    private const int TIMESTAMP = 1_522_353_676;

    /** @return iterable<string, array{string|null, mixed, mixed}> */
    private static function conversions(): iterable
    {
        $dateTime = (new DateTime())->setTimestamp(self::TIMESTAMP);

        foreach (['datetime', 'datetimetz', 'time', 'date'] as $baseType) {
            foreach ([$baseType, $baseType . '_immutable'] as $type) {
                $expected = str_ends_with($type, '_immutable') ? DateTimeImmutable::createFromMutable($dateTime) : $dateTime;

                yield $type . ' from timestamp' => [$type, self::TIMESTAMP, $expected];
                yield $type . ' from string' => [$type, $dateTime->format('Y-m-d\TH:i:s\.u'), $expected];
                yield $type . ' from DateTime' => [$type, clone $dateTime, $expected];
                yield $type . ' from DateTimeImmutable' => [$type, DateTimeImmutable::createFromMutable($dateTime), $expected];
                yield $type . ' from empty string' => [$type, '', null];
            }
        }

        yield 'integer' => ['integer', '123465', 123465];
        yield 'smallint' => ['smallint', '123465', 123465];
        yield 'float' => ['float', '123.465', 123.465];
        yield 'boolean from boolean' => ['boolean', true, true];
        yield 'boolean from integer' => ['boolean', 1, true];

        foreach (['string', 'text', 'bigint'] as $type) {
            yield $type => [$type, 'stringvalue', 'stringvalue'];
            yield $type . ' from integer' => [$type, 12345, '12345'];
        }

        yield 'decimal' => ['decimal', '123.45', '123.45'];
        yield 'decimal from integer' => ['decimal', 12345, '12345'];
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
}
