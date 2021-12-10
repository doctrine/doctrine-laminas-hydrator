<?php

declare(strict_types=1);

namespace Doctrine\Laminas\Hydrator\Strategy;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Laminas\Hydrator\Strategy\StrategyInterface;

interface CollectionStrategyInterface extends StrategyInterface
{
    /**
     * Set the name of the collection
     */
    public function setCollectionName(string $collectionName): void;

    /**
     * Get the name of the collection
     */
    public function getCollectionName(): string;

    /**
     * Set the class metadata
     */
    public function setClassMetadata(ClassMetadata $classMetadata): void;

    /**
     * Get the class metadata
     */
    public function getClassMetadata(): ClassMetadata;

    /**
     * Set the object
     */
    public function setObject(object $object): void;

    /**
     * Get the object
     */
    public function getObject(): object;
}
