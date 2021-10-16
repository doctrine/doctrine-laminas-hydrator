<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator;

use DateTime;
use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineObjectHydrator;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function is_bool;
use function is_float;
use function is_int;
use function is_string;

class DoctrineObjectTypeConversionsTest extends TestCase
{
    /** @var DoctrineObjectHydrator */
    protected $hydratorByValue;

    /** @var DoctrineObjectHydrator */
    protected $hydratorByReference;

    /** @var ClassMetadata */
    protected $metadata;

    /** @var ObjectManager */
    protected $objectManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metadata      = $this->createMock(ClassMetadata::class);
        $this->objectManager = $this->createMock(ObjectManager::class);

        $this->objectManager->expects($this->any())
            ->method('getClassMetadata')
            ->will($this->returnValue($this->metadata));
    }

    /**
     * @param string $genericFieldType
     */
    public function configureObjectManagerForSimpleEntityWithGenericField($genericFieldType)
    {
        $refl = new ReflectionClass(Assets\SimpleEntityWithGenericField::class);

        $this
            ->metadata
            ->method('getName')
            ->will($this->returnValue(Assets\SimpleEntityWithGenericField::class));
        $this
            ->metadata
            ->method('getAssociationNames')
            ->will($this->returnValue([]));

        $this
            ->metadata
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
                    function ($arg) use ($genericFieldType) {
                        if ('id' === $arg) {
                            return 'integer';
                        } elseif ('genericField' === $arg) {
                            return $genericFieldType;
                        }

                        throw new InvalidArgumentException();
                    }
                )
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

    public function configureObjectManagerForOneToOneEntity()
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
            ->will(
                $this->returnCallback(
                    function ($arg) {
                        if ($arg === 'id') {
                            return 'integer';
                        } elseif ($arg === 'toOne') {
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
                    function ($arg) {
                        if ($arg === 'id') {
                            return false;
                        } elseif ($arg === 'toOne') {
                            return true;
                        }

                        throw new InvalidArgumentException();
                    }
                )
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
            ->will($this->returnValue(["id"]));

        $this->hydratorByValue     = new DoctrineObjectHydrator(
            $this->objectManager,
            true
        );
        $this->hydratorByReference = new DoctrineObjectHydrator(
            $this->objectManager,
            false
        );
    }

    public function testHandleTypeConversionsDatetime()
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

    public function testHandleTypeConversionsDatetimetz()
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

    public function testHandleTypeConversionsTime()
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

    public function testHandleTypeConversionsDate()
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

    public function testHandleTypeConversionsInteger()
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('integer');

        $entity = new Assets\SimpleEntityWithGenericField();
        $value  = 123465;
        $data   = ['genericField' => '123465'];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_int($entity->getGenericField()));
        $this->assertEquals($value, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $value  = 123465;
        $data   = ['genericField' => '123465'];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_int($entity->getGenericField()));
        $this->assertEquals($value, $entity->getGenericField());
    }

    public function testHandleTypeConversionsSmallint()
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField('smallint');

        $entity = new Assets\SimpleEntityWithGenericField();
        $value  = 123465;
        $data   = ['genericField' => '123465'];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertTrue(is_int($entity->getGenericField()));
        $this->assertEquals($value, $entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $value  = 123465;
        $data   = ['genericField' => '123465'];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertTrue(is_int($entity->getGenericField()));
        $this->assertEquals($value, $entity->getGenericField());
    }

    public function testHandleTypeConversionsFloat()
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

    public function testHandleTypeConversionsBoolean()
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

    public function testHandleTypeConversionsString()
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

    public function testHandleTypeConversionsText()
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

    public function testHandleTypeConversionsBigint()
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

    public function testHandleTypeConversionsDecimal()
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

    public function testHandleTypeConversionsNullable()
    {
        // When using hydration by value, it will use the public API of the entity to set values (setters)
        $this->configureObjectManagerForSimpleEntityWithGenericField(null);

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => null];

        $entity = $this->hydratorByValue->hydrate($data, $entity);

        $this->assertNull($entity->getGenericField());

        $entity = new Assets\SimpleEntityWithGenericField();
        $data   = ['genericField' => null];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertNull($entity->getGenericField());
    }

    public function testHandleTypeConversionsNullableForAssociatedFields()
    {
        $this->configureObjectManagerForOneToOneEntity();

        $entity = new Assets\OneToOneEntity();
        $data   = ['toOne' => null];

        $entity = $this->hydratorByReference->hydrate($data, $entity);

        $this->assertNull($entity->getToOne(false));
    }
}
