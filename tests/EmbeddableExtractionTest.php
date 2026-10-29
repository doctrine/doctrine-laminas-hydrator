<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Laminas\Hydrator\AssociationExtraction;
use Doctrine\Laminas\Hydrator\DoctrineObject;
use Doctrine\Laminas\Hydrator\EmbeddableExtraction;
use Doctrine\Persistence\ObjectManager;
use DoctrineTest\Laminas\Hydrator\Assets\Address;
use DoctrineTest\Laminas\Hydrator\Assets\GeoLocation;
use DoctrineTest\Laminas\Hydrator\Assets\PersonWithAddress;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleStrategy;
use DoctrineTest\Laminas\Hydrator\Support\HydratorTestCase;
use InvalidArgumentException;
use Laminas\Hydrator\NamingStrategy\UnderscoreNamingStrategy;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

use function iterator_to_array;

#[CoversClass(DoctrineObject::class)]
class EmbeddableExtractionTest extends HydratorTestCase
{
    private const array ARRAY_DATA = [
        'id' => 1,
        'name' => 'Mr. Example',
        'address' => [
            'postalCode' => '48149',
            'city' => 'Münster',
            'geo' => ['lat' => 51.96, 'lng' => 7.62],
        ],
    ];

    private const array FLATTENED_DATA = [
        'id' => 1,
        'name' => 'Mr. Example',
        'address__postalCode' => '48149',
        'address__city' => 'Münster',
        'address__geo__lat' => 51.96,
        'address__geo__lng' => 7.62,
    ];

    #[DataProvider('byValueProvider')]
    public function testEmbeddablesAreExtractedAsObjectsByDefault(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createPersonObjectManager(), $byValue);
        $person   = $this->createPerson();

        $this->assertSame(EmbeddableExtraction::Object, $hydrator->getEmbeddableExtraction());
        $this->assertSame('__', $hydrator->getEmbeddableSeparator());
        $this->assertSame($person->getAddress(), $hydrator->extract($person)['address']);
    }

    #[DataProvider('byValueProvider')]
    public function testEmbeddablesAreListedOnceInFieldNames(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createPersonObjectManager(), $byValue);
        $hydrator->extract($this->createPerson());

        $this->assertSame(['id', 'name', 'address', 'partner'], iterator_to_array($hydrator->getFieldNames(), false));
    }

    #[DataProvider('byValueProvider')]
    public function testExtractAsArray(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createPersonObjectManager(), $byValue);
        $hydrator->setEmbeddableExtraction(EmbeddableExtraction::Array);

        $this->assertSame(self::ARRAY_DATA + ['partner' => null], $hydrator->extract($this->createPerson()));
    }

    #[DataProvider('byValueProvider')]
    public function testExtractFlattened(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createPersonObjectManager(), $byValue);
        $hydrator->setEmbeddableExtraction(EmbeddableExtraction::Flattened);

        $this->assertSame(self::FLATTENED_DATA + ['partner' => null], $hydrator->extract($this->createPerson()));
    }

    #[DataProvider('byValueProvider')]
    public function testExtractFlattenedWithSeparatorAndNamingStrategy(bool $byValue): void
    {
        $hydrator = $this->createHydrator($this->createPersonObjectManager(), $byValue);
        $hydrator->setEmbeddableExtraction(EmbeddableExtraction::Flattened, '.');
        $hydrator->setNamingStrategy(new UnderscoreNamingStrategy());

        $data = $hydrator->extract($this->createPerson());

        $this->assertSame('48149', $data['address.postal_code']);
        $this->assertSame(51.96, $data['address.geo.lat']);
    }

    /** @return iterable<string, array{bool, EmbeddableExtraction, array<string, mixed>}> */
    public static function modeProvider(): iterable
    {
        foreach ([true => 'by value', false => 'by reference'] as $byValue => $name) {
            yield $name . ', array' => [(bool) $byValue, EmbeddableExtraction::Array, self::ARRAY_DATA];
            yield $name . ', flattened' => [(bool) $byValue, EmbeddableExtraction::Flattened, self::FLATTENED_DATA];
        }
    }

    /** @return iterable<string, array{bool, EmbeddableExtraction}> */
    public static function arrayAndFlattenedProvider(): iterable
    {
        foreach (self::modeProvider() as $name => [$byValue, $mode]) {
            yield $name => [$byValue, $mode];
        }
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('modeProvider')]
    public function testHydrateCreatesEmbeddables(bool $byValue, EmbeddableExtraction $mode, array $data): void
    {
        $hydrator = $this->createHydrator($this->createPersonObjectManager(), $byValue);
        $hydrator->setEmbeddableExtraction($mode);

        // The coordinates are converted to float by the type of their fields
        $data = $this->replaceCoordinates($data, '51.96', '7.62');

        $person = $hydrator->hydrate($data, new PersonWithAddress());

        $address = $person->getAddress();
        $this->assertNotNull($address);
        $geo = $address->getGeo();
        $this->assertNotNull($geo);

        $this->assertSame('Mr. Example', $person->getName());
        $this->assertSame('48149', $address->getPostalCode());
        $this->assertSame('Münster', $address->getCity());
        $this->assertSame(51.96, $geo->getLat());
        $this->assertSame(7.62, $geo->getLng());
    }

    /** @return iterable<string, array{bool, EmbeddableExtraction, array<string, mixed>}> */
    public static function partialDataProvider(): iterable
    {
        foreach ([true => 'by value', false => 'by reference'] as $byValue => $name) {
            yield $name . ', array' => [(bool) $byValue, EmbeddableExtraction::Array, ['address' => ['city' => 'Berlin']]];
            yield $name . ', flattened' => [(bool) $byValue, EmbeddableExtraction::Flattened, ['address__city' => 'Berlin']];
        }
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('partialDataProvider')]
    public function testHydrateUpdatesExistingEmbeddable(bool $byValue, EmbeddableExtraction $mode, array $data): void
    {
        $person  = $this->createPerson();
        $address = $person->getAddress();
        $this->assertNotNull($address);

        $hydrator = $this->createHydrator($this->createPersonObjectManager(), $byValue);
        $hydrator->setEmbeddableExtraction($mode);
        $hydrator->hydrate($data, $person);

        $this->assertSame($address, $person->getAddress());
        $this->assertSame('Berlin', $address->getCity());
        $this->assertSame('48149', $address->getPostalCode());
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('modeProvider')]
    public function testExtractedDataCanBeHydratedAgain(bool $byValue, EmbeddableExtraction $mode, array $data): void
    {
        $hydrator = $this->createHydrator($this->createPersonObjectManager(), $byValue);
        $hydrator->setEmbeddableExtraction($mode);

        $extracted = $hydrator->extract($this->createPerson());
        $person    = $hydrator->hydrate($extracted, new PersonWithAddress());

        $this->assertSame($data + ['partner' => null], $extracted);
        $this->assertSame($extracted, $hydrator->extract($person));
    }

    /** @return iterable<string, array{bool, EmbeddableExtraction, array<string, mixed>}> */
    public static function otherFormatProvider(): iterable
    {
        foreach ([true => 'by value', false => 'by reference'] as $byValue => $name) {
            yield $name . ', object with flattened keys' => [(bool) $byValue, EmbeddableExtraction::Object, ['address__city' => 'Berlin']];
            yield $name . ', array with flattened keys' => [(bool) $byValue, EmbeddableExtraction::Array, ['address__city' => 'Berlin']];
        }
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('otherFormatProvider')]
    public function testFlattenedKeysAreOnlyHydratedInFlattenedMode(bool $byValue, EmbeddableExtraction $mode, array $data): void
    {
        $person = $this->createPerson();

        $hydrator = $this->createHydrator($this->createPersonObjectManager(), $byValue);
        $hydrator->setEmbeddableExtraction($mode);
        $hydrator->hydrate($data, $person);

        $this->assertSame('Münster', $person->getAddress()?->getCity());
    }

    /** @return iterable<string, array{bool, EmbeddableExtraction}> */
    public static function allModesProvider(): iterable
    {
        foreach ([true => 'by value', false => 'by reference'] as $byValue => $name) {
            foreach (EmbeddableExtraction::cases() as $mode) {
                yield $name . ', ' . $mode->name => [(bool) $byValue, $mode];
            }
        }
    }

    #[DataProvider('allModesProvider')]
    public function testEmbeddableObjectsAreHydratedInAllModes(bool $byValue, EmbeddableExtraction $mode): void
    {
        $address = new Address();

        $hydrator = $this->createHydrator($this->createPersonObjectManager(), $byValue);
        $hydrator->setEmbeddableExtraction($mode);
        $person = $hydrator->hydrate(['address' => $address], new PersonWithAddress());

        $this->assertSame($address, $person->getAddress());
    }

    #[DataProvider('arrayAndFlattenedProvider')]
    public function testStrategiesCanBeAddedForFieldsOfEmbeddables(bool $byValue, EmbeddableExtraction $mode): void
    {
        $hydrator = $this->createHydrator($this->createPersonObjectManager(), $byValue);
        $hydrator->setEmbeddableExtraction($mode);
        $hydrator->addStrategy('address.city', new SimpleStrategy());

        $person = $hydrator->hydrate(
            $mode === EmbeddableExtraction::Array ? ['address' => ['city' => 'Berlin']] : ['address__city' => 'Berlin'],
            new PersonWithAddress(),
        );
        $this->assertSame('modified while hydrating', $person->getAddress()?->getCity());

        $data = $hydrator->extract($person);
        $city = $mode === EmbeddableExtraction::Array ? $data['address']['city'] : $data['address__city'];
        $this->assertSame('modified while extracting', $city);
    }

    #[DataProvider('arrayAndFlattenedProvider')]
    public function testValuesConvertedByStrategiesAreLeftUntouched(bool $byValue, EmbeddableExtraction $mode): void
    {
        $hydrator = $this->createHydrator($this->createPersonObjectManager(), $byValue);
        $hydrator->setEmbeddableExtraction($mode);
        $hydrator->addStrategy('address', new SimpleStrategy());

        $this->assertSame('modified while extracting', $hydrator->extract($this->createPerson())['address']);
    }

    #[DataProvider('arrayAndFlattenedProvider')]
    public function testSettingAppliesToAssociatedEntities(bool $byValue, EmbeddableExtraction $mode): void
    {
        $hydrator = $this->createHydrator($this->createPersonObjectManager(), $byValue);
        $hydrator->setEmbeddableExtraction($mode);
        $hydrator->setAssociationExtraction(AssociationExtraction::Array);

        $partnerData = $mode === EmbeddableExtraction::Array
            ? ['address' => ['city' => 'Berlin']]
            : ['address__city' => 'Berlin'];

        $person = $hydrator->hydrate(['partner' => $partnerData], new PersonWithAddress());
        $this->assertSame('Berlin', $person->getPartner()?->getAddress()?->getCity());

        $partner = $hydrator->extract($person)['partner'];
        $this->assertSame('Berlin', $mode === EmbeddableExtraction::Array ? $partner['address']['city'] : $partner['address__city']);
    }

    #[DataProvider('arrayAndFlattenedProvider')]
    public function testMissingEmbeddableIsExtractedAsNull(bool $byValue, EmbeddableExtraction $mode): void
    {
        $person = $this->createPerson();
        $person->setAddress(null);

        $hydrator = $this->createHydrator($this->createPersonObjectManager(), $byValue);
        $hydrator->setEmbeddableExtraction($mode);

        $this->assertSame(['id' => 1, 'name' => 'Mr. Example', 'address' => null, 'partner' => null], $hydrator->extract($person));
    }

    #[DataProvider('byValueProvider')]
    public function testFlattenedKeysMustNotCollideWithOtherFields(bool $byValue): void
    {
        // With "_" as separator and underscore naming, "address.city" and "addressCity" both become "address_city"
        $metadata = $this->createMetadata(
            PersonWithAddress::class,
            ['id' => 'integer', 'address.city' => 'string', 'addressCity' => 'string'],
        );
        $hydrator = $this->createHydrator($this->createObjectManager([$metadata]), $byValue);
        $hydrator->setEmbeddableExtraction(EmbeddableExtraction::Flattened, '_');
        $hydrator->setNamingStrategy(new UnderscoreNamingStrategy());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The field "address.city" is flattened to the key "address_city" of the field "addressCity"');

        $hydrator->hydrate(['address_city' => 'Berlin'], new PersonWithAddress());
    }

    public function testSeparatorMustNotBeEmpty(): void
    {
        $hydrator = $this->createHydrator($this->createPersonObjectManager(), true);

        $this->expectException(InvalidArgumentException::class);

        $hydrator->setEmbeddableExtraction(EmbeddableExtraction::Flattened, '');
    }

    private function createPerson(): PersonWithAddress
    {
        $geo = new GeoLocation();
        $geo->setLat(51.96);
        $geo->setLng(7.62);

        $address = new Address();
        $address->setPostalCode('48149');
        $address->setCity('Münster');
        $address->setGeo($geo);

        $person = new PersonWithAddress();
        $person->setId(1);
        $person->setName('Mr. Example');
        $person->setAddress($address);

        return $person;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function replaceCoordinates(array $data, string $lat, string $lng): array
    {
        if (isset($data['address']['geo'])) {
            $data['address']['geo'] = ['lat' => $lat, 'lng' => $lng];
        } else {
            $data['address__geo__lat'] = $lat;
            $data['address__geo__lng'] = $lng;
        }

        return $data;
    }

    private function createPersonObjectManager(): ObjectManager
    {
        // Doctrine ORM lists the fields of embeddables by their full name
        return $this->createObjectManager([
            $this->createMetadata(
                PersonWithAddress::class,
                [
                    'id' => 'integer',
                    'name' => 'string',
                    'address.postalCode' => 'string',
                    'address.city' => 'string',
                    'address.geo.lat' => 'float',
                    'address.geo.lng' => 'float',
                ],
                toOne: ['partner' => PersonWithAddress::class],
            ),
        ]);
    }
}
