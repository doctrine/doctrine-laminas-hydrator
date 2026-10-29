Basic Usage
===========

This library ships with a powerful hydrator that allows almost any use-case.
This page introduces the hydrator using a simple entity. Associations and
embeddables are covered on their own pages.

Create a Hydrator
-----------------

Creating a Doctrine hydrator requires an object manager
(also named entity manager in Doctrine ORM or document manager
in Doctrine ODM):

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($objectManager);

The hydrator constructor has an optional second parameter, ``byValue``,
which is true by default. This allows changing the hydrator's way to
get/set data by either accessing the public API of your entity
(getters/setters) or directly get/set data through reflection, hence
bypassing any of your custom logic. See `By Value and By Reference`_
below for details.

Further optional parameters are ``inflector`` and ``instantiator`` (see
`Using a Custom Inflector or Instantiator`_), and ``typeConverter``, which
converts values based on the Doctrine mapping (see
:doc:`Converting Custom DBAL Types <type-conversion>`).

Hydrating and Extracting an Entity
----------------------------------

.. code:: php

   namespace Application\Entity;

   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Entity]
   class City
   {
       #[ORM\Id]
       #[ORM\Column(type: 'integer')]
       #[ORM\GeneratedValue(strategy: 'AUTO')]
       private ?int $id = null;

       #[ORM\Column(type: 'string', length: 48)]
       private ?string $name = null;

       public function getId(): ?int
       {
           return $this->id;
       }

       public function setName(string $name): void
       {
           $this->name = $name;
       }

       public function getName(): ?string
       {
           return $this->name;
       }
   }

Now, let’s use the Doctrine hydrator:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($entityManager);
   $city = new City();
   $data = [
       'name' => 'Paris',
   ];

   $city = $hydrator->hydrate($data, $city);

   echo $city->getName(); // prints "Paris"

   $dataArray = $hydrator->extract($city);
   echo $dataArray['name']; // prints "Paris"

As shown in this simple example, the Doctrine hydrator
provides nearly no benefits over a simpler hydrator like ``ClassMethodsHydrator``.
However, even in those cases, it provides benefits such as
automatic conversion between types. For instance, it can convert
a timestamp to a ``DateTime`` instance:

.. code:: php

   namespace Application\Entity;

   use DateTime;
   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Entity]
   class Appointment
   {
       #[ORM\Id]
       #[ORM\Column(type: 'integer')]
       #[ORM\GeneratedValue(strategy: 'AUTO')]
       private ?int $id = null;

       #[ORM\Column(type: 'datetime')]
       private ?DateTime $time = null;

       public function getId(): ?int
       {
           return $this->id;
       }

       public function setTime(DateTime $time): void
       {
           $this->time = $time;
       }

       public function getTime(): ?DateTime
       {
           return $this->time;
       }
   }

Let’s use the hydrator:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($entityManager);
   $appointment = new Appointment();
   $data = [
       'time' => '1357057334',
   ];

   $appointment = $hydrator->hydrate($data, $appointment);

   echo get_class($appointment->getTime()); // prints "DateTime"

As you can see, the hydrator automatically converted the timestamp to a
DateTime object during the hydration, hence allowing us to have a clean
API in our entity with a correct typehint. See
:doc:`Converting Custom DBAL Types <type-conversion>` for all conversions.

By Value and By Reference
-------------------------

By default, Doctrine hydrator works by value. This means that the
hydrator will access and modify properties through the public API
of an entity (that is, with getters and setters).
You can override this behaviour to work by reference
(the hydrator will access the properties through the Reflection API and
bypass your getters and setters).

To change the behaviour from by value to by reference, set the second
parameter of the constructor to false:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($objectManager, false);

The setting also applies to associated entities. To handle them
differently, see :ref:`Configuring Associated Entities <association-hydrators>`.

To illustrate the difference between the two, consider an extraction
with the given entity, whose getter modifies the value:

.. code:: php

   namespace Application\Entity;

   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Entity]
   class SimpleEntity
   {
       #[ORM\Id]
       #[ORM\Column(type: 'integer')]
       #[ORM\GeneratedValue]
       private ?int $id = null;

       #[ORM\Column(type: 'string')]
       private ?string $foo = null;

       public function setFoo(string $foo): void
       {
           $this->foo = $foo;
       }

       public function getFoo(): string
       {
           return strtoupper($this->foo);
       }

       /** ... */
   }

Using the hydrator by value:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($objectManager);
   $object   = new SimpleEntity();
   $object->setFoo('bar');

   $data = $hydrator->extract($object);

   echo $data['foo']; // prints 'BAR'

The hydrator used the public API ``getFoo()`` to
retrieve the value.

Using the hydrator by reference:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($objectManager, false);
   $object   = new SimpleEntity();
   $object->setFoo('bar');

   $data = $hydrator->extract($object);

   echo $data['foo']; // prints 'bar'

It prints ``bar``, showing that the getter was not called.

The same applies to hydration: by value, the hydrator calls setters
(and adders and removers for collections, see
:doc:`Working with Associations <associations>`), by reference it writes
the properties directly. Note that a by-reference hydrator cannot hydrate
``readonly`` properties and cannot extract ``readonly`` classes; when
extracting, ``readonly`` properties are skipped.

Strategies, Filters and Naming Strategies
-----------------------------------------

Like all hydrators of laminas-hydrator, the Doctrine hydrator supports
`strategies, filters and naming strategies
<https://docs.laminas.dev/laminas-hydrator/>`__:

- A strategy converts the value of a single field, when hydrating and
  extracting. It is applied before the values are converted based on the
  Doctrine mapping:

  .. code:: php

     use Laminas\Hydrator\Strategy\ClosureStrategy;

     // Trims the name when hydrating, keeps it as it is when extracting
     $hydrator->addStrategy('name', new ClosureStrategy(null, static fn (string $name): string => trim($name)));

- A filter decides which fields are extracted. Filters do not apply to
  hydration. This library provides a filter to include or exclude fields
  by their name:

  .. code:: php

     use Doctrine\Laminas\Hydrator\Filter\PropertyName;

     // Excludes the field "password"
     $hydrator->addFilter('password', new PropertyName(['password']));

     // Only extracts the fields "id" and "name"
     $hydrator->addFilter('public', new PropertyName(['id', 'name'], false));

- A naming strategy maps the names of fields to the keys of the data:

  .. code:: php

     use Laminas\Hydrator\NamingStrategy\UnderscoreNamingStrategy;

     // Uses the key "created_at" for the field "createdAt"
     $hydrator->setNamingStrategy(new UnderscoreNamingStrategy());

Strategies and filters apply to the entity passed to ``hydrate()`` or
``extract()``, the naming strategy also applies to its associated entities.
How to configure associated entities is described in
:ref:`Configuring Associated Entities <association-hydrators>`.

.. _custom-inflector-instantiator:

Using a Custom Inflector or Instantiator
----------------------------------------

The inflector derives the names of methods from the names of fields: for
a field ``createdAt`` or ``created_at``, the hydrator calls ``getCreatedAt()``
and ``setCreatedAt()`` when working by value. By default, the inflector of
`doctrine/inflector <https://www.doctrine-project.org/projects/inflector.html>`__
is used. A custom inflector is only needed if the methods of your entities
follow a different naming convention. As the hydrator uses the
``classify()`` method of the inflector, extend the inflector and override
that method:

.. code:: php

   use Doctrine\Inflector\Inflector;
   use Doctrine\Inflector\NoopWordInflector;
   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   // Fields are prefixed with "m_", e.g. "m_name", but the methods are called getName() and setName()
   final class PrefixInflector extends Inflector
   {
       public function classify(string $word): string
       {
           return parent::classify(preg_replace('/^m_/', '', $word));
       }
   }

   $hydrator = new DoctrineHydrator(
       $entityManager,
       inflector: new PrefixInflector(new NoopWordInflector(), new NoopWordInflector()),
   );

The inflector is inherited by the hydrators of associated entities. Note
that the strategies of collections derive the names of adders and removers
with their own inflector, which can be passed to their constructor, see
:ref:`Collection Strategies <collection-strategies>`.

The instantiator creates new entities when hydrating associations, e.g. a
user given as nested array that does not exist yet, as well as new
embeddables. By default, the constructor is called without arguments. If
the constructor of an entity requires arguments, pass a closure receiving
the class name and the data the object will be hydrated with:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator(
       $entityManager,
       instantiator: static fn (string $className, array $data): object => match ($className) {
           User::class => new User($data['username']),
           default => new $className(),
       },
   );

See :ref:`Creating New Associated Entities <creating-new-associated-entities>`
for details. The entity passed to ``hydrate()`` is always created by
yourself, so the instantiator is not used for it.

Next Steps
----------

- :doc:`Working with Associations <associations>` shows how associations
  are hydrated and extracted.
- :doc:`Working with Embeddables <embeddables>` shows how embeddables are
  hydrated and extracted.
- :doc:`Converting Custom DBAL Types <type-conversion>` explains the
  conversion of values based on the Doctrine mapping.
- :doc:`Example Usage with Laminas Form <laminas-form>` and
  :doc:`Example Usage in an API Context <api-usage>` show complete examples.
- :doc:`Upgrading to v4 <upgrading>` lists the changes when upgrading from
  version 3.
