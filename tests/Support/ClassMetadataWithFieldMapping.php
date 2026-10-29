<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Support;

use Doctrine\Persistence\Mapping\ClassMetadata;

/**
 * Class metadata providing field mappings, like the metadata of Doctrine ORM and ODM
 *
 * @template T of object
 * @template-implements ClassMetadata<T>
 */
abstract class ClassMetadataWithFieldMapping implements ClassMetadata
{
    /** @return array<string, mixed>|object Doctrine ORM 3 returns an object, ORM 2 and ODM an array */
    abstract public function getFieldMapping(string $fieldName): array|object;
}
