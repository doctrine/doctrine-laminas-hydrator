<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use DateTime;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineObjectHydrator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use ReflectionClass;
use function is_bool;
use function is_float;
use function is_integer;
use function is_string;

class DoctrineObjectTypeConversionsTest extends TestCase
{
    protected DoctrineObjectHydrator $hydratorByValue;

    protected DoctrineObjectHydrator $hydratorByReference;

    /** @var ClassMetadata|PHPUnit_Framework_MockObject_MockObject */
    protected $metadata;

    /** @var ObjectManager|PHPUnit_Framework_MockObject_MockObject */
    protected $objectManager;

    protected function setUp() : void
    {
        parent::setUp();

        $this->metadata      = $this->createMock(ClassMetadata::class);
        $this->objectManager = $this->createMock(ObjectManager::class);

        $this->objectManager->expects($this->any())
            ->method('getClassMetadata')
            ->will($this->returnValue($this->metadata));
    }

    public function configureObjectManagerForSimpleEntityWithGenericField(string $genericFieldType) : void
    {
        $refl = new ReflectionClass(Assets\SimpleEntityWithGenericField::class);

        $this
            ->metadata
            ->expects($this->any())
            ->method('getName')
            ->will($this->returnValue(Assets\SimpleEntityWithGenericField::class));
        $this
            ->metadata
            ->expects($this->any())
            ->method('getAssociationNames')
            ->will($this->returnValue([]));

        $this
            ->metadata
            ->expects($this->any())
            ->method('getFieldNames')
            ->will($this->returnValue(['id', 'genericField']));

        $this
            ->metadata
            ->expects($this->any())
            ->method('getTypeOfField')
            ->with($this->logicalOr($this->equalTo('id'), $this->equalTo('genericField')))
            ->will(
                $this->returnCallback(
                    /**
                     * @param string $arg
                     */
                    static function ($arg) use ($genericFieldType) {
                        if ($arg === 'id') {
                            return 'integer';
                        }

                        if ($arg === 'genericField') {
                            return $genericFieldType;
                        }

                        throw new InvalidArgumentException();
                    }
                )
            );

        $this
            ->metadata
            ->expects($this->any())
            ->method('hasAssociation')
            ->will($this->returnValue(false));

        $this
            ->metadata
            ->expects($this->any())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(['id']));

        $this
            ->metadata
            ->expects($this->any())
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

    public function configureObjectManagerForOneToOneEntity() : void
    {
        $refl = new ReflectionClass(Assets\OneToOneEntity::class);

        $this
            ->metadata
            ->expects($this->any())
            ->method('getFieldNames')
            ->will($this->returnValue(['id']));

        $this
            ->metadata
            ->expects($this->any())
            ->method('getAssociationNames')
            ->will($this->returnValue(['toOne']));

        $this
            ->metadata
            ->expects($this->any())
            ->method('getTypeOfField')
            ->with($this->logicalOr($this->equalTo('id'), $this->equalTo('toOne')))
            ->will(
                $this->returnCallback(
                    static function ($arg) {
                        if ($arg === 'id') {
                            return 'integer';
                        }

                        if ($arg === 'toOne') {
                            return Assets\ByValueDifferentiatorEntity::class;
                        }

                        throw new InvalidArgumentException();
                    }
                )
            );

        $this
            ->metadata
            ->expects($this->any())
            ->method('hasAssociation')
            ->with($this->logicalOr($this->equalTo('id'), $this->equalTo('toOne')))
            ->will(
                $this->returnCallback(
                    static function ($arg) {
                        if ($arg === 'id') {
                            return false;
                        }

                        if ($arg === 'toOne') {
                            return true;
                        }

                        throw new InvalidArgumentException();
                    }
                )
            );

        $this
            ->metadata
            ->expects($this->any())
            ->method('isSingleValuedAssociation')
            ->with('toOne')
            ->will($this->returnValue(true));

        $this
            ->metadata
            ->expects($this->any())
            ->method('getAssociationTargetClass')
            ->with('toOne')
            ->will($this->returnValue(Assets\ByValueDifferentiatorEntity::class));

        $this
            ->metadata
            ->expects($this->any())
            ->method('getReflectionClass')
            ->will($this->returnValue($refl));

        $this
            ->metadata
            ->expects($this->any())
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

    public function testHandleTypeConversionsDatetime() : void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('datetime');

        $entity = new Assets\SimpleEntityWithGenericField();
        $now    = new DateTime();
        $now->setTimestamp(1522353676);
        $data = ['genericField' => 1522353676];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $now    = new DateTime();
        $data   = ['genericField' => $now->format('Y-m-d\TH:i:s\.u')];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $now    = new DateTime();
        $data   = ['genericField' => clone $now];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());
    }

    public function testHandleTypeConversionsDatetimetz() : void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('datetimetz');

        $entity = new Assets\SimpleEntityWithGenericField();
        $now    = new DateTime();
        $now->setTimestamp(1522353676);
        $data = ['genericField' => 1522353676];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $now    = new DateTime();
        $data   = ['genericField' => $now->format('Y-m-d\TH:i:s\.u')];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $now    = new DateTime();
        $data   = ['genericField' => clone $now];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());
    }

    public function testHandleTypeConversionsTime() : void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('time');

        $entity = new Assets\SimpleEntityWithGenericField();
        $now    = new DateTime();
        $now->setTimestamp(1522353676);
        $data = ['genericField' => 1522353676];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $now    = new DateTime();
        $data   = ['genericField' => $now->format('Y-m-d\TH:i:s\.u')];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $now    = new DateTime();
        $data   = ['genericField' => clone $now];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());
    }

    public function testHandleTypeConversionsDate() : void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('date');

        $entity = new Assets\SimpleEntityWithGenericField();
        $now    = new DateTime();
        $now->setTimestamp(1522353676);
        $data = ['genericField' => 1522353676];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $now    = new DateTime();
        $data   = ['genericField' => $now->format('Y-m-d\TH:i:s\.u')];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $now    = new DateTime();
        $data   = ['genericField' => clone $now];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertInstanceOf('DateTime', $entity->getGenericField());
        $this->assertEquals($now, $entity->getGenericField());
    }

    public function testHandleTypeConversionsInteger() : void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('integer');

        $entity = new Assets\SimpleEntityWithGenericField();
        $value  = 123465;
        $data   = ['genericField' => '123465'];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_integer($entity->getGenericField()));
        $this->assertEquals($value, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $value  = 123465;
        $data   = ['genericField' => '123465'];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_integer($entity->getGenericField()));
        $this->assertEquals($value, $entity->getGenericField());
    }

    public function testHandleTypeConversionsSmallint() : void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('smallint');

        $entity = new Assets\SimpleEntityWithGenericField();
        $value  = 123465;
        $data   = ['genericField' => '123465'];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_integer($entity->getGenericField()));
        $this->assertEquals($value, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $value  = 123465;
        $data   = ['genericField' => '123465'];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_integer($entity->getGenericField()));
        $this->assertEquals($value, $entity->getGenericField());
    }

    public function testHandleTypeConversionsFloat() : void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('float');

        $entity = new Assets\SimpleEntityWithGenericField();
        $value  = 123.465;
        $data   = ['genericField' => '123.465'];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_float($entity->getGenericField()));
        $this->assertEquals($value, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $value  = 123.465;
        $data   = ['genericField' => '123.465'];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_float($entity->getGenericField()));
        $this->assertEquals($value, $entity->getGenericField());
    }

    public function testHandleTypeConversionsBoolean() : void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('boolean');

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => true];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_bool($entity->getGenericField()));
        $this->assertEquals(true, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => true];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_bool($entity->getGenericField()));
        $this->assertEquals(true, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 1];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_bool($entity->getGenericField()));
        $this->assertEquals(true, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 1];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_bool($entity->getGenericField()));
        $this->assertEquals(true, $entity->getGenericField());
    }

    public function testHandleTypeConversionsString() : void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('string');

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 'stringvalue'];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('stringvalue', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 'stringvalue'];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('stringvalue', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 'stringvalue'];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('stringvalue', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 'stringvalue'];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('stringvalue', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 12345];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('12345', $entity->getGenericField());
    }

    public function testHandleTypeConversionsText() : void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('text');

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 'stringvalue'];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('stringvalue', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 'stringvalue'];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('stringvalue', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 'stringvalue'];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('stringvalue', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 'stringvalue'];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('stringvalue', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 12345];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('12345', $entity->getGenericField());
    }

    public function testHandleTypeConversionsBigint() : void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('bigint');

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 'stringvalue'];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('stringvalue', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 'stringvalue'];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('stringvalue', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 'stringvalue'];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('stringvalue', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 'stringvalue'];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('stringvalue', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 12345];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('12345', $entity->getGenericField());
    }

    public function testHandleTypeConversionsDecimal() : void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('decimal');

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => '123.45'];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('123.45', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => '123.45'];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('123.45', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => '123.45'];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('123.45', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => '123.45'];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('123.45', $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => 12345];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_string($entity->getGenericField()));
        $this->assertEquals('12345', $entity->getGenericField());
    }

    public function testHandleTypeConversionsNullable() : void
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('string');

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => null];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertNull($entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => null];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertNull($entity->getGenericField());
    }

    public function testHandleTypeConversionsNullableForAssociatedFields() : void
    {
        $this->configureObjectManagerForOneToOneEntity();

        $entity = new Assets\OneToOneEntity();
        $data   = ['toOne' => null];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertNull($entity->getToOne(false));
    }
}
