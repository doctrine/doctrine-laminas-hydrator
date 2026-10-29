<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Support;

use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineObjectHydrator;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use DoctrineTest\Laminas\Hydrator\Assets\ByValueDifferentiatorEntity;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function array_key_exists;
use function array_keys;
use function is_array;
use function sprintf;

/**
 * Base class for hydrator tests, providing metadata and object managers built from compact definitions
 */
abstract class HydratorTestCase extends TestCase
{
    /** @return iterable<string, array{bool}> */
    public static function byValueProvider(): iterable
    {
        yield 'by value' => [true];
        yield 'by reference' => [false];
    }

    protected function createHydrator(ObjectManager $objectManager, bool $byValue): DoctrineObjectHydrator
    {
        return new DoctrineObjectHydrator($objectManager, $byValue);
    }

    /**
     * @param class-string                $className
     * @param array<string, string|null>  $fields     Field names mapped to their Doctrine type
     * @param array<string, class-string> $toOne      Single valued associations mapped to their target class
     * @param array<string, class-string> $toMany     Collection valued associations mapped to their target class
     * @param list<string>                $identifier
     */
    protected function createMetadata(
        string $className,
        array $fields,
        array $toOne = [],
        array $toMany = [],
        array $identifier = ['id'],
    ): ClassMetadata {
        $associations = $toOne + $toMany;
        $reflection   = new ReflectionClass($className);

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getName')->willReturn($className);
        $metadata->method('getReflectionClass')->willReturn($reflection);
        $metadata->method('getFieldNames')->willReturn(array_keys($fields));
        $metadata->method('getAssociationNames')->willReturn(array_keys($associations));
        $metadata->method('getIdentifier')->willReturn($identifier);
        $metadata->method('getIdentifierFieldNames')->willReturn($identifier);
        $metadata->method('hasField')->willReturnCallback(
            static fn (string $name): bool => array_key_exists($name, $fields),
        );
        $metadata->method('getTypeOfField')->willReturnCallback(
            static fn (string $name): string|null => $fields[$name] ?? null,
        );
        $metadata->method('hasAssociation')->willReturnCallback(
            static fn (string $name): bool => isset($associations[$name]),
        );
        $metadata->method('isSingleValuedAssociation')->willReturnCallback(
            static fn (string $name): bool => isset($toOne[$name]),
        );
        $metadata->method('isCollectionValuedAssociation')->willReturnCallback(
            static fn (string $name): bool => isset($toMany[$name]),
        );
        $metadata->method('getAssociationTargetClass')->willReturnCallback(
            static fn (string $name): string|null => $associations[$name] ?? null,
        );
        $metadata->method('getIdentifierValues')->willReturnCallback(
            static function (object $object) use ($reflection, $identifier): array {
                $values = [];
                foreach ($identifier as $field) {
                    $property = $reflection->getProperty($field);
                    $value    = $property->isInitialized($object) ? $property->getValue($object) : null;
                    if ($value === null) {
                        continue;
                    }

                    $values[$field] = $value;
                }

                return $values;
            },
        );

        return $metadata;
    }

    /** Metadata of ByValueDifferentiatorEntity, the target entity of most associations in the tests */
    protected function createByValueDifferentiatorMetadata(string $idType = 'integer'): ClassMetadata
    {
        return $this->createMetadata(ByValueDifferentiatorEntity::class, ['id' => $idType, 'field' => 'string']);
    }

    /**
     * Creates an object manager stub whose find() returns the given entities, matched by class and identifier
     *
     * @param list<ClassMetadata> $metadata
     * @param list<object>        $entities
     */
    protected function createObjectManager(array $metadata, array $entities = []): ObjectManager&Stub
    {
        $objectManager = $this->createStub(ObjectManager::class);
        $this->configureObjectManager($objectManager, $metadata);
        $objectManager->method('find')->willReturnCallback(
            static function (string $className, mixed $identifier) use ($entities): object|null {
                $id = is_array($identifier) ? ($identifier['id'] ?? null) : $identifier;
                foreach ($entities as $entity) {
                    if ($entity instanceof $className && $entity->getId() === $id) {
                        return $entity;
                    }
                }

                return null;
            },
        );

        return $objectManager;
    }

    /**
     * Creates an object manager mock, to be used for setting expectations on find()
     *
     * @param list<ClassMetadata> $metadata
     */
    protected function createObjectManagerMock(array $metadata): ObjectManager&MockObject
    {
        $objectManager = $this->createMock(ObjectManager::class);
        $this->configureObjectManager($objectManager, $metadata);

        return $objectManager;
    }

    /** @param list<ClassMetadata> $metadata */
    private function configureObjectManager(ObjectManager&Stub $objectManager, array $metadata): void
    {
        $metadataByClass = [];
        foreach ($metadata as $classMetadata) {
            $metadataByClass[$classMetadata->getName()] = $classMetadata;
        }

        $objectManager->method('getClassMetadata')->willReturnCallback(
            static fn (string $className): ClassMetadata => $metadataByClass[$className]
                ?? throw new InvalidArgumentException(sprintf('No metadata configured for "%s"', $className)),
        );
    }
}
