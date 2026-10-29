Type Conversion
===============

When hydrating, the hydrator converts the values of mapped fields into the
PHP type of the field, based on the Doctrine mapping. For instance, a
timestamp or a string is converted to a ``DateTime`` instance for a field
of type ``datetime``, and a string is converted to an enum for a field
mapped with ``enumType``.

The conversion is applied after the strategy of a field, if there is one.
Values that already have the expected PHP type are kept as they are.

Default Conversions
-------------------

==================================================================================================================== ====================================================================================
Doctrine type                                                                                                        Hydrated value
==================================================================================================================== ====================================================================================
``integer``, ``smallint``                                                                                            ``int``
``float``, ``smallfloat``                                                                                            ``float``
``boolean``                                                                                                          ``bool``
``string``, ``ascii_string``, ``text``, ``guid``, ``enum``, ``bigint``, ``decimal``                                  ``string``
``number``                                                                                                           ``BCMath\Number`` (PHP 8.4 with ``bcmath``)
``date``, ``time``, ``datetime``, ``datetime_utc``, ``datetimetz``                                                   ``DateTime`` from a timestamp, a string or a ``DateTimeImmutable``
``date_immutable``, ``time_immutable``, ``datetime_immutable``, ``datetime_utc_immutable``, ``datetimetz_immutable`` ``DateTimeImmutable`` from a timestamp, a string or a ``DateTime``
``dateinterval``                                                                                                     ``DateInterval`` from an ISO 8601 duration, optionally with a leading ``+`` or ``-``
==================================================================================================================== ====================================================================================

``null`` is always kept, an empty string is converted to ``null`` for date,
time and date interval fields. Values of other types, like ``json``,
``simple_array``, ``binary``, ``blob`` or custom types, are not converted.

Enums
-----

Doctrine ORM supports `backed enums <https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/basic-mapping.html#mapping-php-enums>`__
through the ``enumType`` option of a column. The hydrator uses this option
to convert backing values into enums, no further configuration is needed:

.. code:: php

    enum Suit: string {
        case Hearts = 'H';
        case Diamonds = 'D';
        case Clubs = 'C';
        case Spades = 'S';
    }

    #[Entity]
    class Card
    {
        /** ... */

        #[Column(type: 'string', enumType: Suit::class)]
        private Suit $suit;

        public function getSuit(): Suit
        {
            return $this->suit;
        }

        public function setSuit(Suit $suit): void
        {
            $this->suit = $suit;
        }
    }

    $hydrator = new DoctrineHydrator($entityManager);

    $card = $hydrator->hydrate(['suit' => 'H'], new Card());
    echo $card->getSuit()->name; // prints "Hearts"

Backing values from forms are strings, they are converted to integers for
enums backed by integers. An invalid backing value causes a ``ValueError``.

For fields without ``enumType``, add the ``BackedEnumStrategy`` of
laminas-hydrator to the field:

.. code:: php

    use Laminas\Hydrator\Strategy\BackedEnumStrategy;

    $hydrator->addStrategy('suit', new BackedEnumStrategy(Suit::class));

Converting Values when Extracting
---------------------------------

By default, ``extract()`` returns enums, date and time values and date
intervals as they are, which is what Laminas forms expect. To extract them
as scalar values instead, for instance to serialize an entity to JSON,
pass a configured type converter to the hydrator:

.. code:: php

    use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
    use Doctrine\Laminas\Hydrator\DoctrineTypeConverter;

    $hydrator = new DoctrineHydrator(
        $entityManager,
        typeConverter: new DoctrineTypeConverter(extractEnums: true, extractDateTimes: true),
    );

    $data = $hydrator->extract($card);
    echo $data['suit']; // prints "H"

With ``extractEnums``, enums are extracted as their backing value. With
``extractDateTimes``, date and time values are extracted as strings: fields
of type ``date`` as ``Y-m-d``, fields of type ``time`` as ``H:i:s``, all
other fields in the ``DateTimeInterface::ATOM`` format, and date intervals
in the format of Doctrine DBAL, e.g. ``+P01Y02M03DT04H05M06S``. The
extracted values can be hydrated again.

As when hydrating, the conversion is applied after the strategy of a field,
and only to values of the type that is converted.

Custom Conversions
------------------

Values of custom Doctrine types, like a custom DBAL type ``uuid``, are not
converted by default. To convert them, implement
``Doctrine\Laminas\Hydrator\TypeConverterInterface`` for the type:

.. code:: php

    use Doctrine\Laminas\Hydrator\TypeConverterInterface;
    use Doctrine\Persistence\Mapping\ClassMetadata;

    final class UuidTypeConverter implements TypeConverterInterface
    {
        public function hydrate(mixed $value, ClassMetadata $metadata, string $fieldName): mixed
        {
            return is_string($value) && $value !== '' ? Uuid::fromString($value) : $value;
        }

        public function extract(mixed $value, ClassMetadata $metadata, string $fieldName): mixed
        {
            return $value;
        }
    }

Then register it by the name of the Doctrine type:

.. code:: php

    use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
    use Doctrine\Laminas\Hydrator\DoctrineTypeConverter;

    $hydrator = new DoctrineHydrator(
        $entityManager,
        typeConverter: new DoctrineTypeConverter(typeConverters: [
            'uuid' => new UuidTypeConverter(),
        ]),
    );

A registered converter is used for all fields of its type, in both
directions, and receives all values including ``null``. It replaces the
built-in conversion of the type, so you can also register converters for
types like ``datetime`` to change how they are converted. Fields mapped
with ``enumType`` are always converted to enums.

If your custom DBAL type is able to convert the values your application
receives, you can use it in the converter, e.g.
``Type::getType('uuid')->convertToPHPValue($value, $platform)``. Note that
DBAL types convert values in the format of the database, which may differ
from the format of form data.

For conversions that do not depend on the type of a field only, implement
the interface for all fields and decorate the default converter:

.. code:: php

    use Doctrine\Laminas\Hydrator\DoctrineTypeConverter;
    use Doctrine\Laminas\Hydrator\TypeConverterInterface;
    use Doctrine\Persistence\Mapping\ClassMetadata;

    final class AppTypeConverter implements TypeConverterInterface
    {
        public function __construct(
            private TypeConverterInterface $inner = new DoctrineTypeConverter(),
        ) {
        }

        public function hydrate(mixed $value, ClassMetadata $metadata, string $fieldName): mixed
        {
            if ($fieldName === 'slug' && is_string($value)) {
                return strtolower($value);
            }

            return $this->inner->hydrate($value, $metadata, $fieldName);
        }

        public function extract(mixed $value, ClassMetadata $metadata, string $fieldName): mixed
        {
            return $this->inner->extract($value, $metadata, $fieldName);
        }
    }

    $hydrator = new DoctrineHydrator($entityManager, typeConverter: new AppTypeConverter());

To change the conversion of a single field of a single entity only, add a
strategy to that field instead.
