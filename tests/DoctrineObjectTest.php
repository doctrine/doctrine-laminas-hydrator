<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineObjectHydrator;
use Doctrine\Laminas\Hydrator\Filter;
use Doctrine\Laminas\Hydrator\Strategy;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use DoctrineTest\Laminas\Hydrator\Assets\SimpleEnum;
use InvalidArgumentException;
use Laminas\Hydrator\NamingStrategy\UnderscoreNamingStrategy;
use Laminas\Hydrator\Strategy\StrategyInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use ReflectionClass;
use stdClass;
use TypeError;

use function array_keys;
use function assert;
use function explode;
use function implode;
use function time;

use const PHP_VERSION_ID;

class DoctrineObjectTest extends TestCase
{
    use ProphecyTrait;

    protected DoctrineObjectHydrator $hydratorByValue;

    protected DoctrineObjectHydrator $hydratorByReference;

    /** @var ClassMetadata&MockObject */
    protected $metadata;

    /** @var ObjectManager&MockObject */
    protected $objectManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metadata      = $this->createMock(ClassMetadata::class);
        $this->objectManager = $this->createMock(ObjectManager::class);

        $this->objectManager
            ->method('getClassMetadata')
            ->will($this->returnValue($this->metadata));
    }

    public function configureObjectManagerForSimpleEntity(string $className = Assets\SimpleEntity::class): void
    {
        $refl = new ReflectionClass($className);

        $this
            ->metadata
            ->method('getName')
            ->will($this->returnValue($className));
        $this
            ->metadata
            ->method('getAssociationNames')
            ->will($this->returnValue([]));

        $this
            ->metadata
            ->method('getFieldNames')
            ->will($this->returnValue(['id', 'field']));

        $this
            ->metadata
            ->method('getTypeOfField')
            ->with($this->logicalOr($this->equalTo('id'), $this->equalTo('field')))
            ->willReturnCallback(
                static function ($arg) {
                    if ($arg === 'id') {
                        return 'integer';
                    }

                    if ($arg === 'field') {
                        return 'string';
                    }

                    throw new InvalidArgumentException();
                }
            );

        $this
            ->metadata
            ->method('hasAssociation')
            ->will($this->returnValue(false));

        $this
            ->metadata
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(['id']));

        $this
            ->metadata
            ->method('getReflectionClass')
            ->will($this->returnValue($refl));

        $this->hydratorByValue     = new DoctrineObjectHydrator(
            $this->objectManager,
            true
        );
        $this->hydratorByReference = new DoctrineObjectHydrator(
            $this->objectManager,
            false
        );
    }

    public function configureObjectManagerForByValueDifferentiatorEntity(): void
    {
        $this->configureObjectManagerForSimpleEntity(Assets\ByValueDifferentiatorEntity::class);
    }

    public function configureObjectManagerForNamingStrategyEntity(): void
    {
        $refl = new ReflectionClass(Assets\NamingStrategyEntity::class);

        $this
            ->metadata
            ->method('getName')
            ->will($this->returnValue(Assets\NamingStrategyEntity::class));
        $this
            ->metadata
            ->method('getAssociationNames')
            ->will($this->returnValue([]));

        $this
            ->metadata
            ->method('getFieldNames')
            ->will($this->returnValue(['camelCase']));

        $this
            ->metadata
            ->method('getTypeOfField')
            ->with($this->equalTo('camelCase'))
            ->will($this->returnValue('string'));

        $this
            ->metadata
            ->method('hasAssociation')
            ->will($this->returnValue(false));

        $this
            ->metadata
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(['camelCase']));

        $this
            ->metadata
            ->method('getReflectionClass')
            ->will($this->returnValue($refl));

        $this->hydratorByValue     = new DoctrineObjectHydrator(
            $this->objectManager,
            true
        );
        $this->hydratorByReference = new DoctrineObjectHydrator(
            $this->objectManager,
            false
        );
    }

    public function configureObjectManagerForSimpleIsEntity(): void
    {
        $refl = new ReflectionClass(Assets\SimpleIsEntity::class);

        $this
            ->metadata
            ->method('getName')
            ->will($this->returnValue(Assets\SimpleIsEntity::class));
        $this
            ->metadata
            ->method('getAssociationNames')
            ->will($this->returnValue([]));

        $this
            ->metadata
            ->method('getFieldNames')
            ->will($this->returnValue(['id', 'done']));

        $this
            ->metadata
            ->method('getTypeOfField')
            ->with($this->logicalOr($this->equalTo('id'), $this->equalTo('done')))
            ->willReturnCallback(
                static function ($arg) {
                    if ($arg === 'id') {
                        return 'integer';
                    }

                    if ($arg === 'done') {
                        return 'boolean';
                    }

                    throw new InvalidArgumentException();
                }
            );

        $this
            ->metadata
            ->method('hasAssociation')
            ->will($this->returnValue(false));

        $this
            ->metadata
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(['id']));

        $this
            ->metadata
            ->method('getReflectionClass')
            ->will($this->returnValue($refl));

        $this->hydratorByValue     = new DoctrineObjectHydrator(
            $this->objectManager,
            true
        );
        $this->hydratorByReference = new DoctrineObjectHydrator(
            $this->objectManager,
            false
        );
    }

    public function configureObjectManagerForSimpleEntityWithIsBoolean(): void
    {
        $refl = new ReflectionClass(Assets\SimpleEntityWithIsBoolean::class);

        $this
            ->metadata
            ->method('getName')
            ->will($this->returnValue(Assets\SimpleEntityWithIsBoolean::class));
        $this
            ->metadata
            ->method('getAssociationNames')
            ->will($this->returnValue([]));

        $this
            ->metadata
            ->method('getFieldNames')
            ->will($this->returnValue(['id', 'isActive']));

        $this
            ->metadata
            ->method('getTypeOfField')
            ->with($this->logicalOr($this->equalTo('id'), $this->equalTo('isActive')))
            ->willReturnCallback(
                static function ($arg) {
                    if ($arg === 'id') {
                        return 'integer';
                    }

                    if ($arg === 'isActive') {
                        return 'boolean';
                    }

                    throw new InvalidArgumentException();
                }
            );

        $this
            ->metadata
            ->method('hasAssociation')
            ->will($this->returnValue(false));

        $this
            ->metadata
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(['id']));

        $this
            ->metadata
            ->method('getReflectionClass')
            ->will($this->returnValue($refl));

        $this->hydratorByValue     = new DoctrineObjectHydrator(
            $this->objectManager,
            true
        );
        $this->hydratorByReference = new DoctrineObjectHydrator(
            $this->objectManager,
            false
        );
    }

    public function configureObjectManagerForSimpleEntityWithStringId(string $className = Assets\SimpleEntity::class): void
    {
        $refl = new ReflectionClass($className);

        $this
            ->metadata
            ->method('getName')
            ->will($this->returnValue($className));
        $this
            ->metadata
            ->method('getAssociationNames')
            ->will($this->returnValue([]));

        $this
            ->metadata
            ->method('getFieldNames')
            ->will($this->returnValue(['id', 'field']));

        $this
            ->metadata
            ->method('getTypeOfField')
            ->with($this->logicalOr($this->equalTo('id'), $this->equalTo('field')))
            ->will($this->returnValue('string'));

        $this
            ->metadata
            ->method('hasAssociation')
            ->will($this->returnValue(false));

        $this
            ->metadata
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(['id']));

        $this
            ->metadata
            ->method('getReflectionClass')
            ->will($this->returnValue($refl));

        $this->hydratorByValue     = new DoctrineObjectHydrator(
            $this->objectManager,
            true
        );
        $this->hydratorByReference = new DoctrineObjectHydrator(
            $this->objectManager,
            false
        );
    }

    public function configureObjectManagerForByValueDifferentiatorEntityWithStringId(): void
    {
        $this->configureObjectManagerForSimpleEntityWithStringId(Assets\ByValueDifferentiatorEntity::class);
    }

    public function configureObjectManagerForSimpleEntityWithDateTime(): void
    {
        $refl = new ReflectionClass(Assets\SimpleEntityWithDateTime::class);

        $this
            ->metadata
            ->method('getAssociationNames')
            ->will($this->returnValue([]));

        $this
            ->metadata
            ->method('getFieldNames')
            ->will($this->returnValue(['id', 'date']));

        $this
            ->metadata
            ->method('getTypeOfField')
            ->with($this->logicalOr($this->equalTo('id'), $this->equalTo('date')))
            ->willReturnCallback(
                static function ($arg) {
                    if ($arg === 'id') {
                        return 'integer';
                    }

                    if ($arg === 'date') {
                        return 'datetime';
                    }

                    throw new InvalidArgumentException();
                }
            );

        $this
            ->metadata
            ->method('hasAssociation')
            ->will($this->returnValue(false));

        $this
            ->metadata
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(['id']));

        $this
            ->metadata
            ->method('getReflectionClass')
            ->will($this->returnValue($refl));

        $this->hydratorByValue     = new DoctrineObjectHydrator(
            $this->objectManager,
            true
        );
        $this->hydratorByReference = new DoctrineObjectHydrator(
            $this->objectManager,
            false
        );
    }

    public function configureObjectManagerForSimpleEntityWithEmbeddable(): void
    {
        $refl = new ReflectionClass(Assets\SimpleEntityWithEmbeddable::class);

        $this
            ->metadata
            ->method('getAssociationNames')
            ->will($this->returnValue([]));

        $this
            ->metadata
            ->method('getFieldNames')
            ->will($this->returnValue(['id', 'embedded.field']));

        $this
            ->metadata
            ->method('getTypeOfField')
            ->with($this->logicalOr(
                $this->equalTo('id'),
                $this->equalTo('embedded.field'),
                $this->equalTo('embedded')
            ))
            ->willReturnCallback(
                static function (string $arg): ?string {
                    if ($arg === 'id') {
                        return 'integer';
                    }

                    if ($arg === 'embedded.field') {
                        return 'string';
                    }

                    if ($arg === 'embedded') {
                        return null;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $this
            ->metadata
            ->method('hasAssociation')
            ->will($this->returnValue(false));

        $this
            ->metadata
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(['id']));

        $this
            ->metadata
            ->method('getReflectionClass')
            ->will($this->returnValue($refl));

        $this->hydratorByValue     = new DoctrineObjectHydrator(
            $this->objectManager,
            true
        );
        $this->hydratorByReference = new DoctrineObjectHydrator(
            $this->objectManager,
            false
        );
    }

    public function configureObjectManagerForOneToOneEntity(): void
    {
        $refl = new ReflectionClass(Assets\OneToOneEntity::class);

        $this
            ->metadata
            ->method('getFieldNames')
            ->will($this->returnValue(['id']));

        $this
            ->metadata
            ->method('getAssociationNames')
            ->will($this->returnValue(['toOne']));

        $this
            ->metadata
            ->method('getTypeOfField')
            ->with($this->logicalOr($this->equalTo('id'), $this->equalTo('toOne')))
            ->willReturnCallback(
                static function ($arg) {
                    if ($arg === 'id') {
                        return 'integer';
                    }

                    if ($arg === 'toOne') {
                        return Assets\ByValueDifferentiatorEntity::class;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $this
            ->metadata
            ->method('hasAssociation')
            ->with($this->logicalOr($this->equalTo('id'), $this->equalTo('toOne')))
            ->willReturnCallback(
                static function ($arg) {
                    if ($arg === 'id') {
                        return false;
                    }

                    if ($arg === 'toOne') {
                        return true;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $this
            ->metadata
            ->method('isSingleValuedAssociation')
            ->with('toOne')
            ->will($this->returnValue(true));

        $this
            ->metadata
            ->method('getAssociationTargetClass')
            ->with('toOne')
            ->will($this->returnValue(Assets\ByValueDifferentiatorEntity::class));

        $this
            ->metadata
            ->method('getReflectionClass')
            ->will($this->returnValue($refl));

        $this
            ->metadata
            ->method('getIdentifier')
            ->will($this->returnValue(['id']));

        $this->hydratorByValue     = new DoctrineObjectHydrator(
            $this->objectManager,
            true
        );
        $this->hydratorByReference = new DoctrineObjectHydrator(
            $this->objectManager,
            false
        );
    }

    public function configureObjectManagerForOneToOneEntityNotNullable(): void
    {
        $refl = new ReflectionClass(Assets\OneToOneEntityNotNullable::class);

        $this
            ->metadata
            ->method('getFieldNames')
            ->will($this->returnValue(['id']));

        $this
            ->metadata
            ->method('getAssociationNames')
            ->will($this->returnValue(['toOne']));

        $this
            ->metadata
            ->method('getTypeOfField')
            ->with(
                $this->logicalOr(
                    $this->equalTo('id'),
                    $this->equalTo('toOne'),
                    $this->equalTo('field')
                )
            )
            ->willReturnCallback(
                static function ($arg) {
                    if ($arg === 'id') {
                        return 'integer';
                    }

                    if ($arg === 'toOne') {
                        return Assets\ByValueDifferentiatorEntity::class;
                    }

                    if ($arg === 'field') {
                        return 'string';
                    }

                    throw new InvalidArgumentException();
                }
            );

        $this
            ->metadata
            ->method('hasAssociation')
            ->with(
                $this->logicalOr(
                    $this->equalTo('id'),
                    $this->equalTo('toOne'),
                    $this->equalTo('field')
                )
            )
            ->willReturnCallback(
                static function ($arg) {
                    if ($arg === 'id' || $arg === 'field') {
                        return false;
                    }

                    if ($arg === 'toOne') {
                        return true;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $this
            ->metadata
            ->method('isSingleValuedAssociation')
            ->with('toOne')
            ->will($this->returnValue(true));

        $this
            ->metadata
            ->method('getAssociationTargetClass')
            ->with('toOne')
            ->will($this->returnValue(Assets\ByValueDifferentiatorEntity::class));

        $this
            ->metadata
            ->method('getReflectionClass')
            ->will($this->returnValue($refl));

        $this
            ->metadata
            ->method('getIdentifier')
            ->will($this->returnValue(['id']));

        $this->hydratorByValue     = new DoctrineObjectHydrator(
            $this->objectManager,
            true
        );
        $this->hydratorByReference = new DoctrineObjectHydrator(
            $this->objectManager,
            false
        );
    }

    public function configureObjectManagerForOneToManyEntity(): void
    {
        $refl = new ReflectionClass(Assets\OneToManyEntity::class);

        $this
            ->metadata
            ->method('getFieldNames')
            ->will($this->returnValue(['id']));

        $this
            ->metadata
            ->method('getAssociationNames')
            ->will($this->returnValue(['entities']));

        $this
            ->metadata
            ->method('getTypeOfField')
            ->with(
                $this->logicalOr(
                    $this->equalTo('id'),
                    $this->equalTo('entities'),
                    $this->equalTo('field')
                )
            )
            ->willReturnCallback(
                static function ($arg) {
                    if ($arg === 'id') {
                        return 'integer';
                    }

                    if ($arg === 'field') {
                        return 'string';
                    }

                    if ($arg === 'entities') {
                        return ArrayCollection::class;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $this
            ->metadata
            ->method('hasAssociation')
            ->with($this->logicalOr($this->equalTo('id'), $this->equalTo('entities'), $this->equalTo('field')))
            ->willReturnCallback(
                static function ($arg) {
                    if ($arg === 'id') {
                        return false;
                    }

                    if ($arg === 'field') {
                        return false;
                    }

                    if ($arg === 'entities') {
                        return true;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $this
            ->metadata
            ->method('isSingleValuedAssociation')
            ->with('entities')
            ->will($this->returnValue(false));

        $this
            ->metadata
            ->method('isCollectionValuedAssociation')
            ->with('entities')
            ->will($this->returnValue(true));

        $this
            ->metadata
            ->method('getAssociationTargetClass')
            ->with('entities')
            ->will($this->returnValue(Assets\ByValueDifferentiatorEntity::class));

        $this
            ->metadata
            ->method('getReflectionClass')
            ->will($this->returnValue($refl));

        $this->metadata
            ->method('getIdentifier')
            ->will($this->returnValue(['id']));

        $this->hydratorByValue     = new DoctrineObjectHydrator(
            $this->objectManager,
            true
        );
        $this->hydratorByReference = new DoctrineObjectHydrator(
            $this->objectManager,
            false
        );
    }

    public function configureObjectManagerForOneToManyArrayEntity(): void
    {
        $refl = new ReflectionClass(Assets\OneToManyArrayEntity::class);

        $this
            ->metadata
            ->method('getFieldNames')
            ->will($this->returnValue(['id']));

        $this
            ->metadata
            ->method('getAssociationNames')
            ->will($this->returnValue(['entities']));

        $this
            ->metadata
            ->method('getTypeOfField')
            ->with(
                $this->logicalOr(
                    $this->equalTo('id'),
                    $this->equalTo('entities'),
                    $this->equalTo('field')
                )
            )
            ->willReturnCallback(
                static function ($arg) {
                    if ($arg === 'id') {
                        return 'integer';
                    }

                    if ($arg === 'field') {
                        return 'string';
                    }

                    if ($arg === 'entities') {
                        return ArrayCollection::class;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $this
            ->metadata
            ->method('hasAssociation')
            ->with($this->logicalOr($this->equalTo('id'), $this->equalTo('entities')))
            ->willReturnCallback(
                static function ($arg) {
                    if ($arg === 'id') {
                        return false;
                    }

                    if ($arg === 'field') {
                        return 'string';
                    }

                    if ($arg === 'entities') {
                        return true;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $this
            ->metadata
            ->method('isSingleValuedAssociation')
            ->with('entities')
            ->will($this->returnValue(false));

        $this
            ->metadata
            ->method('isCollectionValuedAssociation')
            ->with('entities')
            ->will($this->returnValue(true));

        $this
            ->metadata
            ->method('getAssociationTargetClass')
            ->with('entities')
            ->will($this->returnValue(Assets\ByValueDifferentiatorEntity::class));

        $this
            ->metadata
            ->method('getReflectionClass')
            ->will($this->returnValue($refl));

        $this->metadata
            ->method('getIdentifier')
            ->will($this->returnValue(['id']));

        $this->hydratorByValue     = new DoctrineObjectHydrator(
            $this->objectManager,
            true
        );
        $this->hydratorByReference = new DoctrineObjectHydrator(
            $this->objectManager,
            false
        );
    }

    public function configureObjectManagerForSimpleEntityWithEnum(): void
    {
        $refl = new ReflectionClass(Assets\SimpleEntityWithEnum::class);

        $this
            ->metadata
            ->method('getAssociationNames')
            ->will($this->returnValue([]));

        $this
            ->metadata
            ->method('getFieldNames')
            ->will($this->returnValue(['id', 'enum']));

        $this
            ->metadata
            ->method('getTypeOfField')
            ->with($this->logicalOr($this->equalTo('id'), $this->equalTo('enum')))
            ->willReturnCallback(
                static function ($arg) {
                    if ($arg === 'id') {
                        return 'integer';
                    }

                    if ($arg === 'enum') {
                        return 'enum';
                    }

                    throw new InvalidArgumentException();
                }
            );

        $this
            ->metadata
            ->method('hasAssociation')
            ->will($this->returnValue(false));

        $this
            ->metadata
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(['id']));

        $this
            ->metadata
            ->method('getReflectionClass')
            ->will($this->returnValue($refl));

        $this->hydratorByValue     = new DoctrineObjectHydrator(
            $this->objectManager,
            true
        );
        $this->hydratorByReference = new DoctrineObjectHydrator(
            $this->objectManager,
            false
        );
    }

    public function testObjectIsPassedForContextToStrategies(): void
    {
        $entity = new Assets\SimpleEntity();
        $entity->setId(2);
        $entity->setField('foo');

        $this->configureObjectManagerForSimpleEntityWithStringId();

        $hydrator = $this->hydratorByValue;
        $entity   = $hydrator->hydrate(['id' => 3, 'field' => 'bar'], $entity);
        $this->assertEquals(['id' => 3, 'field' => 'bar'], $hydrator->extract($entity));

        $hydrator->addStrategy('id', new Assets\ContextStrategy());
        $entity = $hydrator->hydrate(['id' => '3', 'field' => 'bar'], $entity);
        $this->assertEquals('3bar', $entity->getId());
        $this->assertEquals(['id' => '3barbar', 'field' => 'bar'], $hydrator->extract($entity));
    }

    public function testCanExtractSimpleEntityByValue(): void
    {
        // When using extraction by value, it will use the public API of the entity to retrieve values (getters)
        $entity = new Assets\ByValueDifferentiatorEntity();
        $entity->setId(2);
        $entity->setField('foo', false);

        $this->configureObjectManagerForByValueDifferentiatorEntity();

        $data = $this->hydratorByValue->extract($entity);
        $this->assertEquals(['id' => 2, 'field' => 'From getter: foo'], $data);
    }

    public function testCanExtractSimpleEntityByReference(): void
    {
        // When using extraction by reference, it won't use the public API of entity (getters won't be called)
        $entity = new Assets\ByValueDifferentiatorEntity();
        $entity->setId(2);
        $entity->setField('foo', false);

        $this->configureObjectManagerForByValueDifferentiatorEntity();

        $data = $this->hydratorByReference->extract($entity);
        $this->assertEquals(['id' => 2, 'field' => 'foo'], $data);
    }

    public function testDoesNotExtractUninitializedVariables(): void
    {
        // When using extraction by reference, it won't use the public API of entity (getters won't be called)
        $entity = new Assets\SimpleEntityPhp74();
        $entity->setId(2);

        $this->configureObjectManagerForSimpleEntity(Assets\SimpleEntityPhp74::class);

        $data = $this->hydratorByReference->extract($entity);
        $this->assertEquals(['id' => 2], $data);

        $entity->setField('value');
        $data = $this->hydratorByReference->extract($entity);
        $this->assertEquals(['id' => 2, 'field' => 'value'], $data);
    }

    public function testCanHydrateSimpleEntityByValue(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $entity = new Assets\ByValueDifferentiatorEntity();
        $this->configureObjectManagerForByValueDifferentiatorEntity();
        $data = ['field' => 'foo'];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity);
        $this->assertEquals('From setter: foo', $entity->getField(false));
    }

    /**
     * When using hydration by value, it will use the public API of the entity to set values (setters)
     *
     * @covers \Doctrine\Laminas\Hydrator\DoctrineObject::hydrateByValue
     */
    public function testCanHydrateSimpleEntityWithStringIdByValue(): void
    {
        $entity = new Assets\ByValueDifferentiatorEntity();
        $data   = ['id' => 'bar', 'field' => 'foo'];

        $this->configureObjectManagerForByValueDifferentiatorEntityWithStringId();

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity);
        $this->assertEquals('From setter: foo', $entity->getField(false));
    }

    public function testCanHydrateSimpleEntityByReference(): void
    {
        // When using hydration by reference, it won't use the public API of the entity to set values (setters)
        $entity = new Assets\ByValueDifferentiatorEntity();
        $this->configureObjectManagerForByValueDifferentiatorEntity();
        $data = ['field' => 'foo'];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity);
        $this->assertEquals('foo', $entity->getField(false));
    }

    /**
     * When using hydration by reference, it won't use the public API of the entity to set values (getters)
     *
     * @covers \Doctrine\Laminas\Hydrator\DoctrineObject::hydrateByReference
     */
    public function testCanHydrateSimpleEntityWithStringIdByReference(): void
    {
        $entity = new Assets\ByValueDifferentiatorEntity();
        $data   = ['id' => 'bar', 'field' => 'foo'];

        $this->configureObjectManagerForByValueDifferentiatorEntityWithStringId();

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity);
        $this->assertEquals('foo', $entity->getField(false));
    }

    public function testReuseExistingEntityIfDataArrayContainsIdentifier(): void
    {
        // When using hydration by reference, it won't use the public API of the entity to set values (setters)
        $entity = new Assets\ByValueDifferentiatorEntity();

        $this->configureObjectManagerForByValueDifferentiatorEntity();
        $data = ['id' => 1];

        $entityInDatabaseWithIdOfOne = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfOne->setId(1);
        $entityInDatabaseWithIdOfOne->setField('bar', false);

        $this
            ->objectManager
            ->expects($this->once())
            ->method('find')
            ->with(Assets\ByValueDifferentiatorEntity::class, ['id' => 1])
            ->will($this->returnValue($entityInDatabaseWithIdOfOne));

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity);
        $this->assertEquals('bar', $entity->getField(false));
    }

    /**
     * Test for https://github.com/doctrine/DoctrineModule/issues/456
     */
    public function testReuseExistingEntityIfDataArrayContainsIdentifierWithZeroIdentifier(): void
    {
        // When using hydration by reference, it won't use the public API of the entity to set values (setters)
        $entity = new Assets\ByValueDifferentiatorEntity();

        $this->configureObjectManagerForByValueDifferentiatorEntity();
        $data = ['id' => 0];

        $entityInDatabaseWithIdOfOne = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfOne->setId(0);
        $entityInDatabaseWithIdOfOne->setField('bar', false);

        $this
            ->objectManager
            ->expects($this->once())
            ->method('find')
            ->with(Assets\ByValueDifferentiatorEntity::class, ['id' => 0])
            ->will($this->returnValue($entityInDatabaseWithIdOfOne));

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity);
        $this->assertEquals('bar', $entity->getField(false));
    }

    public function testCanExtractSimpleEntityWithEmbeddableByValue(): void
    {
        // When using extraction by value, it will use the public API of the entity to retrieve values (getters)
        $entity = new Assets\SimpleEntityWithEmbeddable();
        $entity->setId(2);
        $entity->getEmbedded()->setField('foo');

        $this->configureObjectManagerForSimpleEntityWithEmbeddable();

        $data = $this->hydratorByValue->extract($entity);
        $this->assertEquals(['id' => 2, 'embedded' => $entity->getEmbedded()], $data);
    }

    public function testCanExtractSimpleEntityWithEmbeddableByReference(): void
    {
        // When using extraction by reference, it won't use the public API of entity (getters won't be called)
        $entity = new Assets\SimpleEntityWithEmbeddable();
        $entity->setId(2);
        $entity->getEmbedded()->setField('foo');

        $this->configureObjectManagerForSimpleEntityWithEmbeddable();

        $data = $this->hydratorByReference->extract($entity);
        $this->assertEquals(['id' => 2, 'embedded' => $entity->getEmbedded()], $data);
    }

    public function testCanHydrateSimpleEntityWithEmbeddableByValue(): void
    {
        // When using extraction by value, it will use the public API of the entity to retrieve values (getters)
        $entity = new Assets\SimpleEntityWithEmbeddable();

        $embedded = new Assets\EmbedabbleEntity();
        $embedded->setField('foo');
        $data = ['embedded' => $embedded];

        $this->configureObjectManagerForSimpleEntityWithEmbeddable();

        $entity = $this->hydratorByValue->hydrate($data, $entity);
        $this->assertInstanceOf(Assets\SimpleEntityWithEmbeddable::class, $entity);
        $this->assertSame($entity->getEmbedded(), $embedded);
    }

    public function testCanHydrateSimpleEntityWithEmbeddableByReference(): void
    {
        // When using extraction by reference, it won't use the public API of entity (getters won't be called)
        $entity = new Assets\SimpleEntityWithEmbeddable();

        $embedded = new Assets\EmbedabbleEntity();
        $embedded->setField('foo');
        $data = ['embedded' => $embedded];

        $this->configureObjectManagerForSimpleEntityWithEmbeddable();

        $entity = $this->hydratorByReference->hydrate($data, $entity);
        $this->assertInstanceOf(Assets\SimpleEntityWithEmbeddable::class, $entity);
        $this->assertSame($entity->getEmbedded(), $embedded);
    }

    public function testExtractOneToOneAssociationByValue(): void
    {
        // When using extraction by value, it will use the public API of the entity to retrieve values (getters)
        $toOne = new Assets\ByValueDifferentiatorEntity();
        $toOne->setId(2);
        $toOne->setField('foo', false);

        $entity = new Assets\OneToOneEntity();
        $entity->setId(2);
        $entity->setToOne($toOne);

        $this->configureObjectManagerForOneToOneEntity();

        $data = $this->hydratorByValue->extract($entity);

        $this->assertEquals(2, $data['id']);
        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $data['toOne']);
        $this->assertEquals('Modified from getToOne getter', $data['toOne']->getField(false));
        $this->assertSame($toOne, $data['toOne']);
    }

    public function testExtractOneToOneAssociationByReference(): void
    {
        // When using extraction by value, it will use the public API of the entity to retrieve values (getters)
        $toOne = new Assets\ByValueDifferentiatorEntity();
        $toOne->setId(2);
        $toOne->setField('foo', false);

        $entity = new Assets\OneToOneEntity();
        $entity->setId(2);
        $entity->setToOne($toOne, false);

        $this->configureObjectManagerForOneToOneEntity();

        $data = $this->hydratorByReference->extract($entity);

        $this->assertEquals(2, $data['id']);
        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $data['toOne']);
        $this->assertEquals('foo', $data['toOne']->getField(false));
        $this->assertSame($toOne, $data['toOne']);
    }

    public function testHydrateOneToOneAssociationByValue(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $toOne = new Assets\ByValueDifferentiatorEntity();
        $toOne->setId(2);
        $toOne->setField('foo', false);

        $entity = new Assets\OneToOneEntity();
        $this->configureObjectManagerForOneToOneEntity();

        $data = ['toOne' => $toOne];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\OneToOneEntity::class, $entity);
        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity->getToOne(false));
        $this->assertEquals('Modified from setToOne setter', $entity->getToOne(false)->getField(false));
    }

    public function testHydrateOneToOneAssociationByReference(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $toOne = new Assets\ByValueDifferentiatorEntity();
        $toOne->setId(2);
        $toOne->setField('foo', false);

        $entity = new Assets\OneToOneEntity();
        $this->configureObjectManagerForOneToOneEntity();

        $data = ['toOne' => $toOne];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\OneToOneEntity::class, $entity);
        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity->getToOne(false));
        $this->assertEquals('foo', $entity->getToOne(false)->getField(false));
    }

    public function testHydrateOneToOneAssociationByValueUsingIdentifierForRelation(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $entity = new Assets\OneToOneEntity();
        $this->configureObjectManagerForOneToOneEntity();

        // Use entity of id 1 as relation
        $data = ['toOne' => 1];

        $entityInDatabaseWithIdOfOne = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfOne->setId(1);
        $entityInDatabaseWithIdOfOne->setField('bar', false);

        $this
            ->objectManager
            ->expects($this->once())
            ->method('find')
            ->with(Assets\ByValueDifferentiatorEntity::class, 1)
            ->will($this->returnValue($entityInDatabaseWithIdOfOne));

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\OneToOneEntity::class, $entity);
        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity->getToOne(false));
        $this->assertSame($entityInDatabaseWithIdOfOne, $entity->getToOne(false));
    }

    public function testHydrateOneToOneAssociationByReferenceUsingIdentifierForRelation(): void
    {
        // When using hydration by reference, it won't use the public API of the entity to set values (setters)
        $entity = new Assets\OneToOneEntity();
        $this->configureObjectManagerForOneToOneEntity();

        // Use entity of id 1 as relation
        $data = ['toOne' => 1];

        $entityInDatabaseWithIdOfOne = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfOne->setId(1);
        $entityInDatabaseWithIdOfOne->setField('bar', false);

        $this
            ->objectManager
            ->expects($this->once())
            ->method('find')
            ->with(Assets\ByValueDifferentiatorEntity::class, 1)
            ->will($this->returnValue($entityInDatabaseWithIdOfOne));

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\OneToOneEntity::class, $entity);
        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity->getToOne(false));
        $this->assertSame($entityInDatabaseWithIdOfOne, $entity->getToOne(false));
    }

    public function testHydrateOneToOneAssociationByValueUsingIdentifierArrayForRelation(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $entity = new Assets\OneToOneEntity();
        $this->configureObjectManagerForOneToOneEntity();

        // Use entity of id 1 as relation
        $data = ['toOne' => ['id' => 1]];

        $entityInDatabaseWithIdOfOne = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfOne->setId(1);
        $entityInDatabaseWithIdOfOne->setField('bar', false);

        $this
            ->objectManager
            ->expects($this->once())
            ->method('find')
            ->with(Assets\ByValueDifferentiatorEntity::class, ['id' => 1])
            ->will($this->returnValue($entityInDatabaseWithIdOfOne));

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\OneToOneEntity::class, $entity);
        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity->getToOne(false));
        $this->assertSame($entityInDatabaseWithIdOfOne, $entity->getToOne(false));
    }

    public function testHydrateOneToOneAssociationByValueUsingFullArrayForRelation(): void
    {
        $entity = new Assets\OneToOneEntityNotNullable();
        $this->configureObjectManagerForOneToOneEntityNotNullable();

        // Use entity of id 1 as relation
        $data = ['toOne' => ['id' => 1, 'field' => 'foo']];

        $entityInDatabaseWithIdOfOne = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfOne->setId(1);
        $entityInDatabaseWithIdOfOne->setField('bar', false);

        $this
            ->objectManager
            ->expects($this->once())
            ->method('find')
            ->with(
                Assets\ByValueDifferentiatorEntity::class,
                ['id' => 1]
            )
            ->will($this->returnValue($entityInDatabaseWithIdOfOne));

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf(
            Assets\OneToOneEntityNotNullable::class,
            $entity
        );
        $this->assertInstanceOf(
            Assets\ByValueDifferentiatorEntity::class,
            $entity->getToOne(false)
        );
        $this->assertSame($entityInDatabaseWithIdOfOne, $entity->getToOne(false));
        $this->assertEquals(
            'From getter: Modified from setToOne setter',
            $entityInDatabaseWithIdOfOne->getField()
        );
    }

    public function testHydrateOneToOneAssociationByReferenceUsingIdentifierArrayForRelation(): void
    {
        // When using hydration by reference, it won't use the public API of the entity to set values (setters)
        $entity = new Assets\OneToOneEntity();
        $this->configureObjectManagerForOneToOneEntity();

        // Use entity of id 1 as relation
        $data = ['toOne' => ['id' => 1]];

        $entityInDatabaseWithIdOfOne = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfOne->setId(1);
        $entityInDatabaseWithIdOfOne->setField('bar', false);

        $this
            ->objectManager
            ->expects($this->once())
            ->method('find')
            ->with(Assets\ByValueDifferentiatorEntity::class, ['id' => 1])
            ->will($this->returnValue($entityInDatabaseWithIdOfOne));

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\OneToOneEntity::class, $entity);
        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity->getToOne(false));
        $this->assertSame($entityInDatabaseWithIdOfOne, $entity->getToOne(false));
    }

    public function testCanHydrateOneToOneAssociationByValueWithNullableRelation(): void
    {
        // When using hydration by value, it will use the public API of the entity to retrieve values (setters)
        $entity = new Assets\OneToOneEntity();
        $this->configureObjectManagerForOneToOneEntity();

        $data = ['toOne' => null];

        $this->metadata->expects($this->atLeastOnce())
            ->method('hasAssociation');

        $object = $this->hydratorByValue->hydrate($data, $entity);
        $this->assertNull($object->getToOne(false));
    }

    public function testCanHydrateOneToOneAssociationByReferenceWithNullableRelation(): void
    {
        // When using hydration by reference, it won't use the public API of the entity to retrieve values (setters)
        $entity = new Assets\OneToOneEntity();

        $this->configureObjectManagerForOneToOneEntity();
        $this->objectManager->expects($this->never())->method('find');
        $this->metadata->expects($this->atLeastOnce())->method('hasAssociation');

        $data = ['toOne' => null];

        $object = $this->hydratorByReference->hydrate($data, $entity);
        $this->assertNull($object->getToOne(false));
    }

    public function testExtractOneToManyAssociationByValue(): void
    {
        // When using extraction by value, it will use the public API of the entity to retrieve values (getters)
        $toMany1 = new Assets\ByValueDifferentiatorEntity();
        $toMany1->setId(2);
        $toMany1->setField('foo', false);

        $toMany2 = new Assets\ByValueDifferentiatorEntity();
        $toMany2->setId(3);
        $toMany2->setField('bar', false);

        $collection = new ArrayCollection([$toMany1, $toMany2]);

        $entity = new Assets\OneToManyEntity();
        $entity->setId(4);
        $entity->addEntities($collection);

        $this->configureObjectManagerForOneToManyEntity();

        $data = $this->hydratorByValue->extract($entity);

        $this->assertEquals(4, $data['id']);
        $this->assertInstanceOf(Collection::class, $data['entities']);

        $this->assertEquals($toMany1->getId(), $data['entities'][0]->getId());
        $this->assertSame($toMany1, $data['entities'][0]);
        $this->assertEquals($toMany2->getId(), $data['entities'][1]->getId());
        $this->assertSame($toMany2, $data['entities'][1]);
    }

    /**
     * @depends testExtractOneToManyAssociationByValue
     */
    public function testExtractOneToManyByValueWithArray(): void
    {
        // When using extraction by value, it will use the public API of the entity to retrieve values (getters)
        $toMany1 = new Assets\ByValueDifferentiatorEntity();
        $toMany1->setId(2);
        $toMany1->setField('foo', false);

        $toMany2 = new Assets\ByValueDifferentiatorEntity();
        $toMany2->setId(3);
        $toMany2->setField('bar', false);

        $collection = new ArrayCollection([$toMany1, $toMany2]);

        $entity = new Assets\OneToManyArrayEntity();
        $entity->setId(4);
        $entity->addEntities($collection);

        $this->configureObjectManagerForOneToManyArrayEntity();

        $data = $this->hydratorByValue->extract($entity);

        $this->assertEquals(4, $data['id']);
        $this->assertIsArray($data['entities']);

        $this->assertEquals($toMany1->getId(), $data['entities'][0]->getId());
        $this->assertSame($toMany1, $data['entities'][0]);
        $this->assertEquals($toMany2->getId(), $data['entities'][1]->getId());
        $this->assertSame($toMany2, $data['entities'][1]);
    }

    public function testExtractOneToManyAssociationByReference(): void
    {
        // When using extraction by reference, it won't use the public API of the entity to retrieve values (getters)
        $toMany1 = new Assets\ByValueDifferentiatorEntity();
        $toMany1->setId(2);
        $toMany1->setField('foo', false);

        $toMany2 = new Assets\ByValueDifferentiatorEntity();
        $toMany2->setId(3);
        $toMany2->setField('bar', false);

        $collection = new ArrayCollection([$toMany1, $toMany2]);

        $entity = new Assets\OneToManyEntity();
        $entity->setId(4);
        $entity->addEntities($collection);

        $this->configureObjectManagerForOneToManyEntity();

        $data = $this->hydratorByReference->extract($entity);

        $this->assertEquals(4, $data['id']);
        $this->assertInstanceOf(Collection::class, $data['entities']);

        $this->assertEquals($toMany1->getId(), $data['entities'][0]->getId());
        $this->assertSame($toMany1, $data['entities'][0]);
        $this->assertEquals($toMany2->getId(), $data['entities'][1]->getId());
        $this->assertSame($toMany2, $data['entities'][1]);
    }

    /**
     * @depends testExtractOneToManyAssociationByReference
     */
    public function testExtractOneToManyArrayByReference(): void
    {
        // When using extraction by reference, it won't use the public API of the entity to retrieve values (getters)
        $toMany1 = new Assets\ByValueDifferentiatorEntity();
        $toMany1->setId(2);
        $toMany1->setField('foo', false);

        $toMany2 = new Assets\ByValueDifferentiatorEntity();
        $toMany2->setId(3);
        $toMany2->setField('bar', false);

        $collection = new ArrayCollection([$toMany1, $toMany2]);

        $entity = new Assets\OneToManyArrayEntity();
        $entity->setId(4);
        $entity->addEntities($collection);

        $this->configureObjectManagerForOneToManyArrayEntity();

        $data = $this->hydratorByReference->extract($entity);

        $this->assertEquals(4, $data['id']);
        $this->assertInstanceOf(Collection::class, $data['entities']);

        $this->assertEquals($toMany1->getId(), $data['entities'][0]->getId());
        $this->assertSame($toMany1, $data['entities'][0]);
        $this->assertEquals($toMany2->getId(), $data['entities'][1]->getId());
        $this->assertSame($toMany2, $data['entities'][1]);
    }

    public function testHydrateOneToManyAssociationByValue(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $toMany1 = new Assets\ByValueDifferentiatorEntity();
        $toMany1->setId(2);
        $toMany1->setField('foo', false);

        $toMany2 = new Assets\ByValueDifferentiatorEntity();
        $toMany2->setId(3);
        $toMany2->setField('bar', false);

        $entity = new Assets\OneToManyEntity();
        $this->configureObjectManagerForOneToManyEntity();

        $data = [
            'entities' => [$toMany1, $toMany2],
        ];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\OneToManyEntity::class, $entity);

        $entities = $entity->getEntities(false);

        foreach ($entities as $en) {
            $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $en);
            $this->assertIsInt($en->getId());
            $this->assertStringContainsString('Modified from addEntities adder', $en->getField(false));
        }

        $this->assertEquals(2, $entities[0]->getId());
        $this->assertSame($toMany1, $entities[0]);

        $this->assertEquals(3, $entities[1]->getId());
        $this->assertSame($toMany2, $entities[1]);
    }

    /**
     * @depends testHydrateOneToManyAssociationByValue
     */
    public function testHydrateOneToManyArrayByValue(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $toMany1 = new Assets\ByValueDifferentiatorEntity();
        $toMany1->setId(2);
        $toMany1->setField('foo', false);

        $toMany2 = new Assets\ByValueDifferentiatorEntity();
        $toMany2->setId(3);
        $toMany2->setField('bar', false);

        $entity = new Assets\OneToManyArrayEntity();
        $this->configureObjectManagerForOneToManyArrayEntity();

        $data = [
            'entities' => [$toMany1, $toMany2],
        ];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\OneToManyArrayEntity::class, $entity);

        $entities = $entity->getEntities(false);

        foreach ($entities as $en) {
            $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $en);
            $this->assertIsInt($en->getId());
            $this->assertStringContainsString('Modified from addEntities adder', $en->getField(false));
        }

        $this->assertEquals(2, $entities[0]->getId());
        $this->assertSame($toMany1, $entities[0]);

        $this->assertEquals(3, $entities[1]->getId());
        $this->assertSame($toMany2, $entities[1]);
    }

    public function testHydrateOneToManyAssociationByReference(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $toMany1 = new Assets\ByValueDifferentiatorEntity();
        $toMany1->setId(2);
        $toMany1->setField('foo', false);

        $toMany2 = new Assets\ByValueDifferentiatorEntity();
        $toMany2->setId(3);
        $toMany2->setField('bar', false);

        $entity = new Assets\OneToManyEntity();
        $this->configureObjectManagerForOneToManyEntity();

        $data = [
            'entities' => [$toMany1, $toMany2],
        ];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\OneToManyEntity::class, $entity);

        $entities = $entity->getEntities(false);

        foreach ($entities as $en) {
            $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $en);
            $this->assertIsInt($en->getId());
            $this->assertStringNotContainsString('Modified from addEntities adder', $en->getField(false));
        }

        $this->assertEquals(2, $entities[0]->getId());
        $this->assertSame($toMany1, $entities[0]);

        $this->assertEquals(3, $entities[1]->getId());
        $this->assertSame($toMany2, $entities[1]);
    }

    /**
     * @depends testHydrateOneToManyAssociationByReference
     */
    public function testHydrateOneToManyArrayByReference(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $toMany1 = new Assets\ByValueDifferentiatorEntity();
        $toMany1->setId(2);
        $toMany1->setField('foo', false);

        $toMany2 = new Assets\ByValueDifferentiatorEntity();
        $toMany2->setId(3);
        $toMany2->setField('bar', false);

        $entity = new Assets\OneToManyArrayEntity();
        $this->configureObjectManagerForOneToManyArrayEntity();

        $data = [
            'entities' => [$toMany1, $toMany2],
        ];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\OneToManyArrayEntity::class, $entity);

        $entities = $entity->getEntities(false);

        foreach ($entities as $en) {
            $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $en);
            $this->assertIsInt($en->getId());
            $this->assertStringNotContainsString('Modified from addEntities adder', $en->getField(false));
        }

        $this->assertEquals(2, $entities[0]->getId());
        $this->assertSame($toMany1, $entities[0]);

        $this->assertEquals(3, $entities[1]->getId());
        $this->assertSame($toMany2, $entities[1]);
    }

    public function testHydrateOneToManyAssociationByValueUsingIdentifiersForRelations(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $entity = new Assets\OneToManyEntity();
        $this->configureObjectManagerForOneToManyEntity();

        $data = [
            'entities' => [2, 3],
        ];

        $entityInDatabaseWithIdOfTwo = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfTwo->setId(2);
        $entityInDatabaseWithIdOfTwo->setField('foo', false);

        $entityInDatabaseWithIdOfThree = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfThree->setId(3);
        $entityInDatabaseWithIdOfThree->setField('bar', false);

        $this
            ->objectManager
            ->expects($this->exactly(2))
            ->method('find')
            ->with(
                Assets\ByValueDifferentiatorEntity::class,
                $this->logicalOr($this->equalTo(['id' => 2]), $this->equalTo(['id' => 3]))
            )
            ->willReturnCallback(
                static function ($target, $arg) use ($entityInDatabaseWithIdOfTwo, $entityInDatabaseWithIdOfThree) {
                    if ($arg['id'] === 2) {
                        return $entityInDatabaseWithIdOfTwo;
                    }

                    if ($arg['id'] === 3) {
                        return $entityInDatabaseWithIdOfThree;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $entity = $this->hydratorByValue->hydrate($data, $entity);
        assert($entity instanceof Assets\OneToManyEntity);

        $this->assertInstanceOf(Assets\OneToManyEntity::class, $entity);

        $entities = $entity->getEntities(false);

        foreach ($entities as $en) {
            $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $en);
            $this->assertIsInt($en->getId());
            $this->assertStringContainsString('Modified from addEntities adder', $en->getField(false));
        }

        $this->assertEquals(2, $entities[0]->getId());
        $this->assertSame($entityInDatabaseWithIdOfTwo, $entities[0]);

        $this->assertEquals(3, $entities[1]->getId());
        $this->assertSame($entityInDatabaseWithIdOfThree, $entities[1]);
    }

    public function testHydrateOneToManyAssociationByValueUsingIdentifiersArrayForRelations(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $entity = new Assets\OneToManyEntity();
        $this->configureObjectManagerForOneToManyEntity();

        $data = [
            'entities' => [
                ['id' => 2],
                ['id' => 3],
            ],
        ];

        $entityInDatabaseWithIdOfTwo = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfTwo->setId(2);
        $entityInDatabaseWithIdOfTwo->setField('foo', false);

        $entityInDatabaseWithIdOfThree = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfThree->setId(3);
        $entityInDatabaseWithIdOfThree->setField('bar', false);

        $this
            ->objectManager
            ->expects($this->exactly(2))
            ->method('find')
            ->with(
                Assets\ByValueDifferentiatorEntity::class,
                $this->logicalOr($this->equalTo(['id' => 2]), $this->equalTo(['id' => 3]))
            )
            ->willReturnCallback(
                static function ($target, $arg) use ($entityInDatabaseWithIdOfTwo, $entityInDatabaseWithIdOfThree) {
                    if ($arg['id'] === 2) {
                        return $entityInDatabaseWithIdOfTwo;
                    }

                    if ($arg['id'] === 3) {
                        return $entityInDatabaseWithIdOfThree;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $entity = $this->hydratorByValue->hydrate($data, $entity);
        assert($entity instanceof Assets\OneToManyEntity);

        $this->assertInstanceOf(Assets\OneToManyEntity::class, $entity);

        $entities = $entity->getEntities(false);

        foreach ($entities as $en) {
            $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $en);
            $this->assertIsInt($en->getId());
            $this->assertStringContainsString('Modified from addEntities adder', $en->getField(false));
        }

        $this->assertEquals(2, $entities[0]->getId());
        $this->assertSame($entityInDatabaseWithIdOfTwo, $entities[0]);

        $this->assertEquals(3, $entities[1]->getId());
        $this->assertSame($entityInDatabaseWithIdOfThree, $entities[1]);
    }

    public function testHydrateOneToManyAssociationByReferenceUsingIdentifiersArrayForRelations(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $entity = new Assets\OneToManyEntity();

        $this
            ->metadata
            ->method('getReflectionClass')
            ->willReturnOnConsecutiveCalls(
                new ReflectionClass(Assets\OneToManyEntity::class),
                new ReflectionClass(Assets\ByValueDifferentiatorEntity::class),
                new ReflectionClass(Assets\ByValueDifferentiatorEntity::class),
                new ReflectionClass(Assets\OneToManyEntity::class)
            );

        $this->configureObjectManagerForOneToManyEntity();

        $data = [
            'entities' => [
                ['id' => 2],
                ['id' => 3],
            ],
        ];

        $entityInDatabaseWithIdOfTwo = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfTwo->setId(2);
        $entityInDatabaseWithIdOfTwo->setField('foo', false);

        $entityInDatabaseWithIdOfThree = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfThree->setId(3);
        $entityInDatabaseWithIdOfThree->setField('bar', false);

        $this
            ->objectManager
            ->expects($this->exactly(2))
            ->method('find')
            ->with(
                Assets\ByValueDifferentiatorEntity::class,
                $this->logicalOr($this->equalTo(['id' => 2]), $this->equalTo(['id' => 3]))
            )
            ->willReturnCallback(
                static function ($target, $arg) use ($entityInDatabaseWithIdOfTwo, $entityInDatabaseWithIdOfThree) {
                    if ($arg['id'] === 2) {
                        return $entityInDatabaseWithIdOfTwo;
                    }

                    if ($arg['id'] === 3) {
                        return $entityInDatabaseWithIdOfThree;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\OneToManyEntity::class, $entity);

        $entities = $entity->getEntities(false);

        foreach ($entities as $en) {
            $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $en);
            $this->assertIsInt($en->getId());
            $this->assertStringNotContainsString('Modified from addEntities adder', $en->getField(false));
        }

        $this->assertEquals(2, $entities[0]->getId());
        $this->assertSame($entityInDatabaseWithIdOfTwo, $entities[0]);

        $this->assertEquals(3, $entities[1]->getId());
        $this->assertSame($entityInDatabaseWithIdOfThree, $entities[1]);
    }

    public function testHydrateOneToManyAssociationByReferenceUsingIdentifiersForRelations(): void
    {
        // When using hydration by reference, it won't use the public API of the entity to set values (setters)
        $entity = new Assets\OneToManyEntity();
        $this->configureObjectManagerForOneToManyEntity();

        $data = [
            'entities' => [2, 3],
        ];

        $entityInDatabaseWithIdOfTwo = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfTwo->setId(2);
        $entityInDatabaseWithIdOfTwo->setField('foo', false);

        $entityInDatabaseWithIdOfThree = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfThree->setId(3);
        $entityInDatabaseWithIdOfThree->setField('bar', false);

        $this
            ->objectManager
            ->method('find')
            ->with(
                Assets\ByValueDifferentiatorEntity::class,
                $this->logicalOr($this->equalTo(['id' => 2]), $this->equalTo(['id' => 3]))
            )
            ->willReturnCallback(
                static function ($target, $arg) use ($entityInDatabaseWithIdOfTwo, $entityInDatabaseWithIdOfThree) {
                    if ($arg['id'] === 2) {
                        return $entityInDatabaseWithIdOfTwo;
                    }

                    if ($arg['id'] === 3) {
                        return $entityInDatabaseWithIdOfThree;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\OneToManyEntity::class, $entity);

        $entities = $entity->getEntities(false);

        foreach ($entities as $en) {
            $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $en);
            $this->assertIsInt($en->getId());
            $this->assertStringNotContainsString('Modified from addEntities adder', $en->getField(false));
        }

        $this->assertEquals(2, $entities[0]->getId());
        $this->assertSame($entityInDatabaseWithIdOfTwo, $entities[0]);

        $this->assertEquals(3, $entities[1]->getId());
        $this->assertSame($entityInDatabaseWithIdOfThree, $entities[1]);
    }

    public function testHydrateOneToManyAssociationByValueUsingDisallowRemoveStrategy(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $toMany1 = new Assets\ByValueDifferentiatorEntity();
        $toMany1->setId(2);
        $toMany1->setField('foo', false);

        $toMany2 = new Assets\ByValueDifferentiatorEntity();
        $toMany2->setId(3);
        $toMany2->setField('bar', false);

        $toMany3 = new Assets\ByValueDifferentiatorEntity();
        $toMany3->setId(8);
        $toMany3->setField('baz', false);

        $entity = new Assets\OneToManyEntity();
        $this->configureObjectManagerForOneToManyEntity();

        // Initially add two elements
        $entity->addEntities(new ArrayCollection([$toMany1, $toMany2]));

        // The hydrated collection contains two other elements, one of them is new, and one of them is missing
        // in the new strategy
        $data = [
            'entities' => [$toMany2, $toMany3],
        ];

        // Use a DisallowRemove strategy
        $this->hydratorByValue->addStrategy('entities', new Strategy\DisallowRemoveByValue());
        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $entities = $entity->getEntities(false);

        // DisallowStrategy should not remove existing entities in Collection even if it's not in the new collection
        $this->assertCount(3, $entities);

        foreach ($entities as $en) {
            $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $en);
            $this->assertIsInt($en->getId());
        }

        $this->assertEquals(2, $entities[0]->getId());
        $this->assertSame($toMany1, $entities[0]);

        $this->assertEquals(3, $entities[1]->getId());
        $this->assertSame($toMany2, $entities[1]);

        $this->assertEquals(8, $entities[2]->getId());
        $this->assertSame($toMany3, $entities[2]);
    }

    public function testHydrateOneToManyAssociationByReferenceUsingDisallowRemoveStrategy(): void
    {
        // When using hydration by reference, it won't use the public API of the entity to set values (setters)
        $toMany1 = new Assets\ByValueDifferentiatorEntity();
        $toMany1->setId(2);
        $toMany1->setField('foo', false);

        $toMany2 = new Assets\ByValueDifferentiatorEntity();
        $toMany2->setId(3);
        $toMany2->setField('bar', false);

        $toMany3 = new Assets\ByValueDifferentiatorEntity();
        $toMany3->setId(8);
        $toMany3->setField('baz', false);

        $entity = new Assets\OneToManyEntity();
        $this->configureObjectManagerForOneToManyEntity();

        // Initially add two elements
        $entity->addEntities(new ArrayCollection([$toMany1, $toMany2]));

        // The hydrated collection contains two other elements, one of them is new, and one of them is missing
        // in the new strategy
        $data = [
            'entities' => [$toMany2, $toMany3],
        ];

        // Use a DisallowRemove strategy
        $this->hydratorByReference->addStrategy('entities', new Strategy\DisallowRemoveByReference());
        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $entities = $entity->getEntities(false);

        // DisallowStrategy should not remove existing entities in Collection even if it's not in the new collection
        $this->assertCount(3, $entities);

        foreach ($entities as $en) {
            $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $en);
            $this->assertIsInt($en->getId());

            // Only the third element is new so the adder has not been called on it
            if ($en !== $toMany3) {
                continue;
            }

            $this->assertStringNotContainsString('Modified from addEntities adder', $en->getField(false));
        }

        $this->assertEquals(2, $entities[0]->getId());
        $this->assertSame($toMany1, $entities[0]);

        $this->assertEquals(3, $entities[1]->getId());
        $this->assertSame($toMany2, $entities[1]);

        $this->assertEquals(8, $entities[2]->getId());
        $this->assertSame($toMany3, $entities[2]);
    }

    public function testHydrateOneToManyAssociationByValueWithArrayCausingDataModifications(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $data = [
            'entities' => [
                ['id' => 2, 'field' => 'Modified By Hydrate'],
                ['id' => 3, 'field' => 'Modified By Hydrate'],
            ],
        ];

        $entityInDatabaseWithIdOfTwo = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfTwo->setId(2);
        $entityInDatabaseWithIdOfTwo->setField('foo', false);

        $entityInDatabaseWithIdOfThree = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfThree->setId(3);
        $entityInDatabaseWithIdOfThree->setField('bar', false);

        $entity = new Assets\OneToManyEntityWithEntities(
            new ArrayCollection([
                $entityInDatabaseWithIdOfTwo,
                $entityInDatabaseWithIdOfThree,
            ])
        );
        $this->configureObjectManagerForOneToManyEntity();

        $this
            ->objectManager
            ->expects($this->exactly(2))
            ->method('find')
            ->with(
                Assets\ByValueDifferentiatorEntity::class,
                $this->logicalOr($this->equalTo(['id' => 2]), $this->equalTo(['id' => 3]))
            )
            ->willReturnCallback(
                static function ($target, $arg) use ($entityInDatabaseWithIdOfTwo, $entityInDatabaseWithIdOfThree) {
                    if ($arg['id'] === 2) {
                        return $entityInDatabaseWithIdOfTwo;
                    }

                    if ($arg['id'] === 3) {
                        return $entityInDatabaseWithIdOfThree;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $entity = $this->hydratorByValue->hydrate($data, $entity);
        assert($entity instanceof Assets\OneToManyEntity);

        $this->assertInstanceOf(Assets\OneToManyEntityWithEntities::class, $entity);

        $entities = $entity->getEntities(false);

        foreach ($entities as $en) {
            $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $en);
            $this->assertIsInt($en->getId());
            $this->assertIsString($en->getField());
            $this->assertStringContainsString('Modified By Hydrate', $en->getField(false));
        }

        $this->assertEquals(2, $entities[0]->getId());
        $this->assertSame($entityInDatabaseWithIdOfTwo, $entities[0]);

        $this->assertEquals(3, $entities[1]->getId());
        $this->assertSame($entityInDatabaseWithIdOfThree, $entities[1]);
    }

    public function testHydrateOneToManyAssociationByValueWithTraversableCausingDataModifications(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $data = [
            'entities' => [
                ['id' => 2, 'field' => 'Modified By Hydrate'],
                ['id' => 3, 'field' => 'Modified By Hydrate'],
            ],
        ];

        $entityInDatabaseWithIdOfTwo = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfTwo->setId(2);
        $entityInDatabaseWithIdOfTwo->setField('foo', false);

        $entityInDatabaseWithIdOfThree = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfThree->setId(3);
        $entityInDatabaseWithIdOfThree->setField('bar', false);

        $entity = new Assets\OneToManyEntityWithEntities(
            new ArrayCollection([
                $entityInDatabaseWithIdOfTwo,
                $entityInDatabaseWithIdOfThree,
            ])
        );
        $this->configureObjectManagerForOneToManyEntity();

        $this
            ->objectManager
            ->expects($this->exactly(2))
            ->method('find')
            ->with(
                Assets\ByValueDifferentiatorEntity::class,
                $this->logicalOr($this->equalTo(['id' => 2]), $this->equalTo(['id' => 3]))
            )
            ->willReturnCallback(
                static function ($target, $arg) use ($entityInDatabaseWithIdOfTwo, $entityInDatabaseWithIdOfThree) {
                    if ($arg['id'] === 2) {
                        return $entityInDatabaseWithIdOfTwo;
                    }

                    if ($arg['id'] === 3) {
                        return $entityInDatabaseWithIdOfThree;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $entity = $this->hydratorByValue->hydrate($data, $entity);
        assert($entity instanceof Assets\OneToManyEntity);

        $this->assertInstanceOf(Assets\OneToManyEntityWithEntities::class, $entity);

        $entities = $entity->getEntities(false);

        foreach ($entities as $en) {
            $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $en);
            $this->assertIsInt($en->getId());
            $this->assertIsString($en->getField());
            $this->assertStringContainsString('Modified By Hydrate', $en->getField(false));
        }

        $this->assertEquals(2, $entities[0]->getId());
        $this->assertSame($entityInDatabaseWithIdOfTwo, $entities[0]);

        $this->assertEquals(3, $entities[1]->getId());
        $this->assertSame($entityInDatabaseWithIdOfThree, $entities[1]);
    }

    public function testHydrateOneToManyAssociationByValueWithStdClass(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $stdClass1     = new stdClass();
        $stdClass1->id = 2;

        $stdClass2     = new stdClass();
        $stdClass2->id = 3;

        $data = ['entities' => [$stdClass1, $stdClass2]];

        $entityInDatabaseWithIdOfTwo = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfTwo->setId(2);
        $entityInDatabaseWithIdOfTwo->setField('foo', false);

        $entityInDatabaseWithIdOfThree = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfThree->setId(3);
        $entityInDatabaseWithIdOfThree->setField('bar', false);

        $entity = new Assets\OneToManyEntityWithEntities(
            new ArrayCollection([
                $entityInDatabaseWithIdOfTwo,
                $entityInDatabaseWithIdOfThree,
            ])
        );
        $this->configureObjectManagerForOneToManyEntity();

        $this
            ->objectManager
            ->expects($this->exactly(2))
            ->method('find')
            ->with(
                Assets\ByValueDifferentiatorEntity::class,
                $this->logicalOr($this->equalTo(['id' => 2]), $this->equalTo(['id' => 3]))
            )
            ->willReturnCallback(
                static function ($target, $arg) use ($entityInDatabaseWithIdOfTwo, $entityInDatabaseWithIdOfThree) {
                    if ($arg['id'] === 2) {
                        return $entityInDatabaseWithIdOfTwo;
                    }

                    if ($arg['id'] === 3) {
                        return $entityInDatabaseWithIdOfThree;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $entity = $this->hydratorByValue->hydrate($data, $entity);
        assert($entity instanceof Assets\OneToManyEntity);

        $this->assertInstanceOf(Assets\OneToManyEntityWithEntities::class, $entity);

        $entities = $entity->getEntities(false);

        foreach ($entities as $en) {
            $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $en);
            $this->assertIsInt($en->getId());
        }

        $this->assertEquals(2, $entities[0]->getId());
        $this->assertSame($entityInDatabaseWithIdOfTwo, $entities[0]);

        $this->assertEquals(3, $entities[1]->getId());
        $this->assertSame($entityInDatabaseWithIdOfThree, $entities[1]);
    }

    public function testHydrateOneToManyAssociationByReferenceWithArrayCausingDataModifications(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $data = [
            'entities' => [
                ['id' => 2, 'field' => 'Modified By Hydrate'],
                ['id' => 3, 'field' => 'Modified By Hydrate'],
            ],
        ];

        $entityInDatabaseWithIdOfTwo = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfTwo->setId(2);
        $entityInDatabaseWithIdOfTwo->setField('Unmodified Value', false);

        $entityInDatabaseWithIdOfThree = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfThree->setId(3);
        $entityInDatabaseWithIdOfThree->setField('Unmodified Value', false);

        $entity = new Assets\OneToManyEntityWithEntities(
            new ArrayCollection([
                $entityInDatabaseWithIdOfTwo,
                $entityInDatabaseWithIdOfThree,
            ])
        );

        $this
            ->metadata
            ->method('getReflectionClass')
            ->willReturnOnConsecutiveCalls(
                new ReflectionClass(Assets\OneToManyEntityWithEntities::class),
                new ReflectionClass(Assets\ByValueDifferentiatorEntity::class),
                new ReflectionClass(Assets\ByValueDifferentiatorEntity::class),
                new ReflectionClass(Assets\OneToManyEntityWithEntities::class)
            );

        $this->configureObjectManagerForOneToManyEntity();

        $this
            ->objectManager
            ->expects($this->exactly(2))
            ->method('find')
            ->with(
                Assets\ByValueDifferentiatorEntity::class,
                $this->logicalOr($this->equalTo(['id' => 2]), $this->equalTo(['id' => 3]))
            )
            ->willReturnCallback(
                static function ($target, $arg) use ($entityInDatabaseWithIdOfTwo, $entityInDatabaseWithIdOfThree) {
                    if ($arg['id'] === 2) {
                        return $entityInDatabaseWithIdOfTwo;
                    }

                    if ($arg['id'] === 3) {
                        return $entityInDatabaseWithIdOfThree;
                    }

                    throw new InvalidArgumentException();
                }
            );

        $entity = $this->hydratorByReference->hydrate($data, $entity);
        assert($entity instanceof Assets\OneToManyEntity);

        $this->assertInstanceOf(Assets\OneToManyEntityWithEntities::class, $entity);

        $entities = $entity->getEntities(false);

        foreach ($entities as $en) {
            $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $en);
            $this->assertIsInt($en->getId());
            $this->assertIsString($en->getField());
            $this->assertStringContainsString('Modified By Hydrate', $en->getField(false));
        }

        $this->assertEquals(2, $entities[0]->getId());
        $this->assertSame($entityInDatabaseWithIdOfTwo, $entities[0]);

        $this->assertEquals(3, $entities[1]->getId());
        $this->assertSame($entityInDatabaseWithIdOfThree, $entities[1]);
    }

    public function testAssertCollectionsAreNotSwappedDuringHydration(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $entity = new Assets\OneToManyEntity();
        $this->configureObjectManagerForOneToManyEntity();

        $toMany1 = new Assets\ByValueDifferentiatorEntity();
        $toMany1->setId(2);
        $toMany1->setField('foo', false);

        $toMany2 = new Assets\ByValueDifferentiatorEntity();
        $toMany2->setId(3);
        $toMany2->setField('bar', false);

        $data = [
            'entities' => [$toMany1, $toMany2],
        ];

        // Set the initial collection
        $entity->addEntities(new ArrayCollection([$toMany1, $toMany2]));
        $initialCollection = $entity->getEntities(false);

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $modifiedCollection = $entity->getEntities(false);
        $this->assertSame($initialCollection, $modifiedCollection);
    }

    public function testAssertCollectionsAreNotSwappedDuringHydrationUsingIdentifiersForRelations(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $entity = new Assets\OneToManyEntity();
        $this->configureObjectManagerForOneToManyEntity();

        $data = [
            'entities' => [2, 3],
        ];

        $entityInDatabaseWithIdOfTwo = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfTwo->setId(2);
        $entityInDatabaseWithIdOfTwo->setField('foo', false);

        $entityInDatabaseWithIdOfThree = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithIdOfThree->setId(3);
        $entityInDatabaseWithIdOfThree->setField('bar', false);

        // Set the initial collection
        $entity->addEntities(new ArrayCollection([$entityInDatabaseWithIdOfTwo, $entityInDatabaseWithIdOfThree]));
        $initialCollection = $entity->getEntities(false);

        $this
            ->objectManager
            ->method('find')
            ->with(
                Assets\ByValueDifferentiatorEntity::class,
                $this->logicalOr($this->equalTo(['id' => 2]), $this->equalTo(['id' => 3]))
            )
            ->willReturnCallback(
                static function ($target, $arg) use ($entityInDatabaseWithIdOfTwo, $entityInDatabaseWithIdOfThree) {
                    if ($arg['id'] === 2) {
                        return $entityInDatabaseWithIdOfTwo;
                    }

                    return $entityInDatabaseWithIdOfThree;
                }
            );

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $modifiedCollection = $entity->getEntities(false);
        $this->assertSame($initialCollection, $modifiedCollection);
    }

    public function testCanLookupsForEmptyIdentifiers(): void
    {
        // When using hydration by reference, it won't use the public API of the entity to set values (setters)
        $entity = new Assets\OneToManyEntity();
        $this->configureObjectManagerForOneToManyEntity();

        $data = [
            'entities' => [''],
        ];

        $entityInDatabaseWithEmptyId = new Assets\ByValueDifferentiatorEntity();
        $entityInDatabaseWithEmptyId->setId('');
        $entityInDatabaseWithEmptyId->setField('baz', false);

        $this
            ->objectManager
            ->method('find')
            ->with(Assets\ByValueDifferentiatorEntity::class, '')
            ->will($this->returnValue($entityInDatabaseWithEmptyId));

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\OneToManyEntity::class, $entity);

        $entities = $entity->getEntities(false);
        $entity   = $entities[0];

        $this->assertCount(1, $entities);

        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity);
        $this->assertSame($entityInDatabaseWithEmptyId, $entity);
    }

    public function testHandleDateTimeConversionUsingByValue(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $entity = new Assets\SimpleEntityWithDateTime();
        $this->configureObjectManagerForSimpleEntityWithDateTime();

        $now  = time();
        $data = ['date' => $now];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getDate());
        $this->assertEquals($now, $entity->getDate()->getTimestamp());
    }

    public function testEmptyStringIsNotConvertedToDateTime(): void
    {
        $entity = new Assets\SimpleEntityWithDateTime();
        $this->configureObjectManagerForSimpleEntityWithDateTime();

        $data = ['date' => ''];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertNull($entity->getDate());
    }

    public function testAssertNullValueHydratedForOneToOneWithOptionalMethodSignature(): void
    {
        $entity = new Assets\OneToOneEntity();

        $this->configureObjectManagerForOneToOneEntity();
        $this->objectManager->expects($this->never())->method('find');

        $data = ['toOne' => null];

        $object = $this->hydratorByValue->hydrate($data, $entity);
        $this->assertNull($object->getToOne(false));
    }

    public function testAssertNullValueNotUsedAsIdentifierForOneToOneWithNonOptionalMethodSignature(): void
    {
        $entity = new Assets\OneToOneEntityNotNullable();

        $entity->setToOne(new Assets\ByValueDifferentiatorEntity());
        $this->configureObjectManagerForOneToOneEntityNotNullable();
        $this->objectManager->expects($this->never())->method('find');

        $data = ['toOne' => null];

        $object = $this->hydratorByValue->hydrate($data, $entity);
        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $object->getToOne(false));
    }

    public function testUsesStrategyOnSimpleFieldsWhenHydratingByValue(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $entity = new Assets\ByValueDifferentiatorEntity();
        $this->configureObjectManagerForByValueDifferentiatorEntity();
        $data = ['field' => 'foo'];

        $this->hydratorByValue->addStrategy('field', new Assets\SimpleStrategy());
        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity);
        $this->assertEquals('From setter: modified while hydrating', $entity->getField(false));
    }

    public function testUsesStrategyOnSimpleFieldsWhenHydratingByReference(): void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $entity = new Assets\ByValueDifferentiatorEntity();
        $this->configureObjectManagerForByValueDifferentiatorEntity();
        $data = ['field' => 'foo'];

        $this->hydratorByReference->addStrategy('field', new Assets\SimpleStrategy());
        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity);
        $this->assertEquals('modified while hydrating', $entity->getField(false));
    }

    public function testUsesStrategyOnSimpleFieldsWhenExtractingByValue(): void
    {
        $entity = new Assets\ByValueDifferentiatorEntity();
        $entity->setId(2);
        $entity->setField('foo', false);

        $this->configureObjectManagerForByValueDifferentiatorEntity();

        $this->hydratorByValue->addStrategy('field', new Assets\SimpleStrategy());
        $data = $this->hydratorByValue->extract($entity);
        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity);
        $this->assertEquals(['id' => 2, 'field' => 'modified while extracting'], $data);
    }

    public function testUsesStrategyOnSimpleFieldsWhenExtractingByReference(): void
    {
        $entity = new Assets\ByValueDifferentiatorEntity();
        $entity->setId(2);
        $entity->setField('foo', false);

        $this->configureObjectManagerForByValueDifferentiatorEntity();

        $this->hydratorByReference->addStrategy('field', new Assets\SimpleStrategy());
        $data = $this->hydratorByReference->extract($entity);
        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity);
        $this->assertEquals(['id' => 2, 'field' => 'modified while extracting'], $data);
    }

    public function testCanExtractIsserByValue(): void
    {
        $entity = new Assets\SimpleIsEntity();
        $entity->setId(2);
        $entity->setDone(true);

        $this->configureObjectManagerForSimpleIsEntity();

        $data = $this->hydratorByValue->extract($entity);
        $this->assertInstanceOf(Assets\SimpleIsEntity::class, $entity);
        $this->assertEquals(['id' => 2, 'done' => true], $data);
    }

    public function testCanExtractIsserThatStartsWithIsByValue(): void
    {
        $entity = new Assets\SimpleEntityWithIsBoolean();
        $entity->setId(2);
        $entity->setIsActive(true);

        $this->configureObjectManagerForSimpleEntityWithIsBoolean();

        $data = $this->hydratorByValue->extract($entity);
        $this->assertInstanceOf(Assets\SimpleEntityWithIsBoolean::class, $entity);
        $this->assertEquals(['id' => 2, 'isActive' => true], $data);
    }

    public function testExtractWithPropertyNameFilterByValue(): void
    {
        $entity = new Assets\ByValueDifferentiatorEntity();
        $entity->setId(2);
        $entity->setField('foo', false);

        $filter = new Filter\PropertyName(['id'], false);

        $this->configureObjectManagerForByValueDifferentiatorEntity();

        $this->hydratorByValue->addFilter('propertyname', $filter);
        $data = $this->hydratorByValue->extract($entity);

        $this->assertEquals(2, $data['id']);
        $this->assertEquals(['id'], array_keys($data), 'Only the "id" field should have been extracted.');
    }

    public function testExtractWithPropertyNameFilterByReference(): void
    {
        $entity = new Assets\ByValueDifferentiatorEntity();
        $entity->setId(2);
        $entity->setField('foo', false);

        $filter = new Filter\PropertyName(['id'], false);

        $this->configureObjectManagerForByValueDifferentiatorEntity();

        $this->hydratorByReference->addFilter('propertyname', $filter);
        $data = $this->hydratorByReference->extract($entity);

        $this->assertEquals(2, $data['id']);
        $this->assertEquals(['id'], array_keys($data), 'Only the "id" field should have been extracted.');
    }

    public function testExtractByReferenceUsesNamingStrategy(): void
    {
        $this->configureObjectManagerForNamingStrategyEntity();
        $name = 'Foo';
        $this->hydratorByReference->setNamingStrategy(new UnderscoreNamingStrategy());
        $data = $this->hydratorByReference->extract(new Assets\NamingStrategyEntity($name));
        $this->assertEquals($name, $data['camel_case']);
    }

    public function testExtractByValueUsesNamingStrategy(): void
    {
        $this->configureObjectManagerForNamingStrategyEntity();
        $name = 'Bar';
        $this->hydratorByValue->setNamingStrategy(new UnderscoreNamingStrategy());
        $data = $this->hydratorByValue->extract(new Assets\NamingStrategyEntity($name));
        $this->assertEquals($name, $data['camel_case']);
    }

    public function testHydrateByReferenceUsesNamingStrategy(): void
    {
        $this->configureObjectManagerForNamingStrategyEntity();
        $name = 'Baz';
        $this->hydratorByReference->setNamingStrategy(new UnderscoreNamingStrategy());
        $entity = $this->hydratorByReference->hydrate(['camel_case' => $name], new Assets\NamingStrategyEntity());
        $this->assertEquals($name, $entity->getCamelCase());
    }

    public function testHydrateByValueUsesNamingStrategy(): void
    {
        $this->configureObjectManagerForNamingStrategyEntity();
        $name = 'Qux';
        $this->hydratorByValue->setNamingStrategy(new UnderscoreNamingStrategy());
        $entity = $this->hydratorByValue->hydrate(['camel_case' => $name], new Assets\NamingStrategyEntity());
        $this->assertEquals($name, $entity->getCamelCase());
    }

    public function configureObjectManagerForSimplePrivateEntity(): void
    {
        $refl = new ReflectionClass(Assets\SimplePrivateEntity::class);

        $this
            ->metadata
            ->method('getName')
            ->will($this->returnValue(Assets\SimplePrivateEntity::class));
        $this
            ->metadata
            ->method('getAssociationNames')
            ->will($this->returnValue([]));

        $this
            ->metadata
            ->method('getFieldNames')
            ->will($this->returnValue(['private', 'protected']));

        $this
            ->metadata
            ->method('getTypeOfField')
            ->with($this->logicalOr($this->equalTo('private'), $this->equalTo('protected')))
            ->will($this->returnValue('integer'));

        $this
            ->metadata
            ->method('hasAssociation')
            ->will($this->returnValue(false));

        $this
            ->metadata
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(['private']));

        $this
            ->metadata
            ->method('getReflectionClass')
            ->will($this->returnValue($refl));

        $this->hydratorByValue     = new DoctrineObjectHydrator(
            $this->objectManager,
            true
        );
        $this->hydratorByReference = new DoctrineObjectHydrator(
            $this->objectManager,
            false
        );
    }

    public function testCannotHydratePrivateByValue(): void
    {
        $entity = new Assets\SimplePrivateEntity();
        $this->configureObjectManagerForSimplePrivateEntity();
        $data = ['private' => 123, 'protected' => 456];

        $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf(Assets\SimplePrivateEntity::class, $entity);
    }

    public function testDefaultStrategy(): void
    {
        $this->configureObjectManagerForOneToManyEntity();

        $entity = new Assets\OneToManyEntity();

        $this->hydratorByValue->hydrate([], $entity);

        $this->assertEquals(
            Strategy\AllowRemoveByValue::class,
            $this->hydratorByValue->getDefaultByValueStrategy()
        );

        $this->assertInstanceOf(
            Strategy\AllowRemoveByValue::class,
            $this->hydratorByValue->getStrategy('entities')
        );

        $this->hydratorByReference->hydrate([], $entity);

        $this->assertEquals(
            Strategy\AllowRemoveByReference::class,
            $this->hydratorByReference->getDefaultByReferenceStrategy()
        );

        $this->assertInstanceOf(
            Strategy\AllowRemoveByReference::class,
            $this->hydratorByReference->getStrategy('entities')
        );
    }

    /**
     * @depends testDefaultStrategy
     */
    public function testOverrideDefaultStrategy(): void
    {
        $this->configureObjectManagerForOneToManyEntity();

        $this->hydratorByValue->setDefaultByValueStrategy(Assets\DifferentAllowRemoveByValue::class);
        $this->hydratorByReference->setDefaultByReferenceStrategy(Assets\DifferentAllowRemoveByReference::class);

        $entity = new Assets\OneToManyEntity();

        $this->hydratorByValue->hydrate([], $entity);

        $this->assertEquals(
            Assets\DifferentAllowRemoveByValue::class,
            $this->hydratorByValue->getDefaultByValueStrategy()
        );

        $this->assertInstanceOf(
            Assets\DifferentAllowRemoveByValue::class,
            $this->hydratorByValue->getStrategy('entities')
        );

        $this->hydratorByReference->hydrate([], $entity);

        $this->assertEquals(
            Assets\DifferentAllowRemoveByReference::class,
            $this->hydratorByReference->getDefaultByReferenceStrategy()
        );

        $this->assertInstanceOf(
            Assets\DifferentAllowRemoveByReference::class,
            $this->hydratorByReference->getStrategy('entities')
        );
    }

    /**
     * https://github.com/doctrine/DoctrineModule/issues/639
     */
    public function testStrategyWithArrayByValue(): void
    {
        $entity = new Assets\SimpleEntity();

        $data = ['field' => ['complex', 'value']];
        $this->configureObjectManagerForSimpleEntity();
        $this->hydratorByValue->addStrategy('field', new class implements StrategyInterface {
            /**
             * @param mixed $value
             *
             * @return string[]
             */
            public function extract($value, ?object $object = null): array
            {
                return explode(',', $value);
            }

            /**
             * @param mixed    $value
             * @param string[] $data
             */
            public function hydrate($value, ?array $data): string
            {
                return implode(',', $value);
            }
        });

        $this->hydratorByValue->hydrate($data, $entity);

        $this->assertEquals('complex,value', $entity->getField());
    }

    public function testStrategyWithArrayByReference(): void
    {
        $entity = new Assets\SimpleEntity();

        $data = ['field' => ['complex', 'value']];
        $this->configureObjectManagerForSimpleEntity();
        $this->hydratorByReference->addStrategy('field', new class implements StrategyInterface {
            /**
             * @param mixed $value
             *
             * @return string[]
             */
            public function extract($value, ?object $object = null): array
            {
                return explode(',', $value);
            }

            /**
             * @param mixed    $value
             * @param string[] $data
             */
            public function hydrate($value, ?array $data): string
            {
                return implode(',', $value);
            }
        });

        $this->hydratorByReference->hydrate($data, $entity);

        $this->assertSame('complex,value', $entity->getField());
    }

    private function getObjectManagerForNestedHydration(): ObjectManager
    {
        $oneToOneMetadata = $this->prophesize(ClassMetadata::class);
        $oneToOneMetadata->getName()->willReturn(Assets\OneToOneEntity::class);
        $oneToOneMetadata->getFieldNames()->willReturn(['id', 'toOne', 'createdAt']);
        $oneToOneMetadata->getAssociationNames()->willReturn(['toOne']);
        $oneToOneMetadata->getTypeOfField('id')->willReturn('integer');
        $oneToOneMetadata->getTypeOfField('toOne')->willReturn(Assets\ByValueDifferentiatorEntity::class);
        $oneToOneMetadata->getTypeOfField('createdAt')->willReturn('datetime');
        $oneToOneMetadata->hasAssociation('id')->willReturn(false);
        $oneToOneMetadata->hasAssociation('toOne')->willReturn(true);
        $oneToOneMetadata->hasAssociation('createdAt')->willReturn(false);
        $oneToOneMetadata->isSingleValuedAssociation('toOne')->willReturn(true);
        $oneToOneMetadata->isCollectionValuedAssociation('toOne')->willReturn(false);
        $oneToOneMetadata->getAssociationTargetClass('toOne')->willReturn(Assets\ByValueDifferentiatorEntity::class);
        $oneToOneMetadata->getReflectionClass()->willReturn(new ReflectionClass(Assets\OneToOneEntity::class));
        $oneToOneMetadata->getIdentifier()->willReturn(['id']);
        $oneToOneMetadata->getIdentifierFieldNames()->willReturn(['id']);

        $byValueDifferentiatorEntity = $this->prophesize(ClassMetadata::class);
        $byValueDifferentiatorEntity->getName()->willReturn(Assets\ByValueDifferentiatorEntity::class);
        $byValueDifferentiatorEntity->getAssociationNames()->willReturn([]);
        $byValueDifferentiatorEntity->getFieldNames()->willReturn(['id', 'field']);
        $byValueDifferentiatorEntity->getTypeOfField('id')->willReturn('integer');
        $byValueDifferentiatorEntity->getTypeOfField('field')->willReturn('string');
        $byValueDifferentiatorEntity->hasAssociation(Argument::any())->willReturn(false);
        $byValueDifferentiatorEntity->getIdentifier()->willReturn(['id']);
        $byValueDifferentiatorEntity->getIdentifierFieldNames()->willReturn(['id']);
        $byValueDifferentiatorEntity
            ->getReflectionClass()
            ->willReturn(new ReflectionClass(Assets\ByValueDifferentiatorEntity::class));

        $objectManager = $this->prophesize(ObjectManager::class);
        $objectManager
            ->getClassMetadata(Assets\OneToOneEntity::class)
            ->will([$oneToOneMetadata, 'reveal']);
        $objectManager
            ->getClassMetadata(Assets\ByValueDifferentiatorEntity::class)
            ->will([$byValueDifferentiatorEntity, 'reveal']);
        $objectManager->find(Assets\OneToOneEntity::class, ['id' => 12])->willReturn(null);
        $objectManager->find(Assets\ByValueDifferentiatorEntity::class, ['id' => 13])->willReturn(null);

        return $objectManager->reveal();
    }

    public function testNestedHydrationByValue(): void
    {
        $objectManager = $this->getObjectManagerForNestedHydration();
        $hydrator      = new DoctrineObjectHydrator($objectManager, true);
        $entity        = new Assets\OneToOneEntity();

        $data = [
            'id'        => 12,
            'toOne'     => [
                'id'    => 13,
                'field' => 'value',
            ],
            'createdAt' => '2019-01-24 12:00:00',
        ];

        $hydrator->hydrate($data, $entity);

        $this->assertSame(12, $entity->getId());
        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity->getToOne(false));
        $this->assertSame(13, $entity->getToOne(false)->getId());
        $this->assertSame('Modified from setToOne setter', $entity->getToOne(false)->getField(false));
        $this->assertSame('2019-01-24 12:00:00', $entity->getCreatedAt()->format('Y-m-d H:i:s'));
    }

    public function testNestedHydrationByReference(): void
    {
        $objectManager = $this->getObjectManagerForNestedHydration();
        $hydrator      = new DoctrineObjectHydrator($objectManager, false);
        $entity        = new Assets\OneToOneEntity();

        $data = [
            'id'        => 12,
            'toOne'     => [
                'id'    => 13,
                'field' => 'value',
            ],
            'createdAt' => '2019-01-24 12:00:00',
        ];

        $hydrator->hydrate($data, $entity);

        $this->assertSame(12, $entity->getId());
        $this->assertInstanceOf(Assets\ByValueDifferentiatorEntity::class, $entity->getToOne(false));
        $this->assertSame(13, $entity->getToOne(false)->getId());
        $this->assertSame('value', $entity->getToOne(false)->getField(false));
        $this->assertSame('2019-01-24 12:00:00', $entity->getCreatedAt()->format('Y-m-d H:i:s'));
    }

    public function testHandleEnumConversionUsingByValue(): void
    {
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('PHP 8.1 required for enum compatibility');
        }

        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $entity = new Assets\SimpleEntityWithEnum();
        $this->configureObjectManagerForSimpleEntityWithEnum();

        $value = 1;
        $data  = ['enum' => $value];

        $this->hydratorByValue->addStrategy('enum', new Assets\SimpleEnumStrategy());
        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf(SimpleEnum::class, $entity->getEnum());
        $this->assertEquals(SimpleEnum::tryFrom($value), $entity->getEnum());
    }

    public function testNullValueIsNotConvertedToEnum(): void
    {
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('PHP 8.1 required for enum compatibility');
        }

        $entity = new Assets\SimpleEntityWithEnum();
        $this->configureObjectManagerForSimpleEntityWithEnum();

        $data = ['enum' => null];

        $this->hydratorByValue->addStrategy('enum', new Assets\SimpleEnumStrategy());
        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertNull($entity->getEnum());
    }

    public function testWrongEnumBackedValueThrowsException(): void
    {
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('PHP 8.1 required for enum compatibility');
        }

        $entity = new Assets\SimpleEntityWithEnum();
        $this->configureObjectManagerForSimpleEntityWithEnum();

        $data = ['enum' => 'string'];

        $this->expectException(TypeError::class);

        $this->hydratorByValue->addStrategy('enum', new Assets\SimpleEnumStrategy());
        $this->hydratorByValue->hydrate($data, $entity);
    }
}
