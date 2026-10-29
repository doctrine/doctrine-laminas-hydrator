Upgrading to v4
===============

This page lists the changes that may require adjustments when upgrading
from version 3 to version 4, and how to adapt to them.

Requirements
------------

- PHP 8.3 or higher is required, support for PHP 8.2 has been dropped.
- ``doctrine/collections`` 2.6 or higher is required, version 3.1 is
  supported as well. Note that version 3 requires PHP 8.4.
- ``laminas/laminas-hydrator`` 5 is supported in addition to 4.13 or
  higher. When upgrading laminas-hydrator to version 5, your own strategies,
  filters and naming strategies need to follow its interfaces, which use
  native types, see the `migration guide of laminas-hydrator
  <https://docs.laminas.dev/laminas-hydrator/v5/migration/v4-to-v5/>`__.

DoctrineObject is Final
-----------------------

``DoctrineObject`` is ``final`` and cannot be extended anymore. All its
protected properties and methods are ``private`` now, e.g.
``getClassMetadata()``, ``extractByValue()``, ``hydrateByValue()``,
``toOne()``, ``toMany()``, ``find()`` and ``handleTypeConversions()``. The
public method ``getFieldNames()`` is ``private`` as well.

Use cases that required extending the hydrator are covered as follows:

=============================================== =====================================================================================================
Use case                                        Replacement
=============================================== =====================================================================================================
Converting values, e.g. of custom DBAL types    A type converter, see :doc:`Converting Custom DBAL Types <type-conversion>`
Creating new associated entities differently    The instantiator, see :ref:`Using a Custom Inflector or Instantiator <custom-inflector-instantiator>`
Configuring the handling of associated entities Association hydrators, see :ref:`Configuring Associated Entities <association-hydrators>`
Converting single fields                        Strategies, see :doc:`Basic Usage <basic-usage>`
Adjusting the data before or after hydrating    A decorator, see below
=============================================== =====================================================================================================

To adjust the data before hydrating or after extracting, wrap the
hydrator in a decorator implementing ``Laminas\Hydrator\HydratorInterface``:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject;
   use Laminas\Hydrator\HydratorInterface;

   final class MyHydrator implements HydratorInterface
   {
       public function __construct(private DoctrineObject $hydrator)
       {
       }

       public function extract(object $object): array
       {
           $data = $this->hydrator->extract($object);
           // … adjust $data …

           return $data;
       }

       public function hydrate(array $data, object $object): object
       {
           // … adjust $data …

           return $this->hydrator->hydrate($data, $object);
       }
   }

Laminas forms only require a ``HydratorInterface``, so the decorator can
be passed to ``setHydrator()`` of a form or fieldset.

Type Conversion
---------------

The conversion of values has moved from ``DoctrineObject::handleTypeConversions()``
to ``Doctrine\Laminas\Hydrator\DoctrineTypeConverter``, which can be
replaced or extended by converters for custom types:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject;
   use Doctrine\Laminas\Hydrator\DoctrineTypeConverter;

   $hydrator = new DoctrineObject(
       $entityManager,
       typeConverter: new DoctrineTypeConverter(typeConverters: ['uuid' => new UuidTypeConverter()]),
   );

See :doc:`Converting Custom DBAL Types <type-conversion>` for details.

Additionally, more values are converted than before:

- Fields mapped with ``enumType`` are converted to enums from their
  backing values. Previously, this required a strategy, otherwise the
  backing value was passed to the entity, causing a ``TypeError`` for
  typed setters and properties. Existing strategies for enums keep
  working, as enums returned by a strategy are kept as they are.
- Values of the types ``smallfloat``, ``ascii_string``, ``guid``, ``enum``,
  ``number``, ``dateinterval``, ``datetime_utc`` and
  ``datetime_utc_immutable`` are converted to their PHP types, e.g. a
  ``DateInterval`` for ``dateinterval``. Previously, they were passed as
  they are. If your entities expect other values for fields of these
  types, add a strategy for the fields or register a converter for the
  type.

Collection Strategies
---------------------

The methods of the collection strategies use native types now. If you
implemented ``CollectionStrategyInterface`` or extended
``AbstractCollectionStrategy``, adapt the signatures of your methods:

.. code:: php

   // Before
   public function hydrate($value, ?array $data)
   public function extract($value, ?object $object = null)

   // After
   public function hydrate(mixed $value, ?array $data = null): mixed
   public function extract(mixed $value, ?object $object = null): mixed

Associated Entities
-------------------

Associated entities given as nested arrays were hydrated by the same
hydrator as the entity passed to ``hydrate()``. Therefore, its strategies
and filters also applied to fields of associated entities that happened to
have the same name. Associated entities are now hydrated by separate
hydrators, so strategies and filters added to your hydrator only apply to
the entity passed to ``hydrate()``. To configure strategies and filters
for associated entities, add them to their hydrators:

.. code:: php

   $hydrator->getAssociationHydrator(User::class)->addStrategy('name', new NameStrategy());

The naming strategy of your hydrator still applies to associated entities.
See :ref:`Configuring Associated Entities <association-hydrators>` for
details. Laminas forms using fieldsets bound to objects, as described in
:doc:`Example Usage with Laminas Form <laminas-form>`, are not affected,
as nested fieldsets hydrate their objects with their own hydrators.

Null Values for Associations
----------------------------

When hydrating by reference, ``null`` given for a OneToOne or ManyToOne
association is ignored now if the property does not accept ``null``, like
it is when hydrating by value and the setter does not accept ``null``.
Previously, ``null`` was written to the property, causing a ``TypeError``.

Embeddables
-----------

An embeddable is extracted once now. Previously, it was extracted once per
field of the embeddable, so strategies and filters for the embeddable were
called several times. The extracted data is the same.

New Features
------------

The following features have been added and do not require adjustments:

- Extracting associations as identifiers or arrays, see
  :ref:`Extracting Associations <extracting-associations>`.
- Extracting and hydrating embeddables as arrays, see
  :ref:`Embeddables as Arrays <embeddable-extraction>`.
- Configuring the hydrators of associated entities, see
  :ref:`Configuring Associated Entities <association-hydrators>`.
- Extracting enums and dates as scalar values, and converters for custom
  types, see :doc:`Converting Custom DBAL Types <type-conversion>`.
- Creating new associated entities with a custom instantiator, see
  :ref:`Creating New Associated Entities <creating-new-associated-entities>`.
