Working with Embeddables
========================

Doctrine provides so-called embeddables as a layer of abstraction that
allows reusing partial objects across entities. For example, one might
have an ``Address`` which is not only used for a ``Person``, but
also for an ``Organization``. This page shows how embeddables are
hydrated and extracted.

Embeddables as Objects
----------------------

First, we have an ``Address`` class, which will be our embeddable:

.. code:: php

   namespace Application\Entity;

   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Embeddable]
   class Address
   {
       #[ORM\Column(type: 'string', nullable: true)]
       private ?string $postalCode = null;

       #[ORM\Column(type: 'string', nullable: true)]
       private ?string $city = null;

       public function getPostalCode(): ?string
       {
           return $this->postalCode;
       }

       public function setPostalCode(?string $postalCode): void
       {
           $this->postalCode = $postalCode;
       }

       public function getCity(): ?string
       {
           return $this->city;
       }

       public function setCity(?string $city): void
       {
           $this->city = $city;
       }
   }

Then we have a corresponding ``Person`` entity, where the above
embeddable is used:

.. code:: php

   namespace Application\Entity;

   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Entity]
   class Person
   {
       #[ORM\Id]
       #[ORM\Column(type: 'integer')]
       #[ORM\GeneratedValue]
       private ?int $id = null;

       #[ORM\Column(type: 'string', nullable: true)]
       private ?string $name = null;

       #[ORM\Embedded(class: Address::class)]
       private Address $address;

       /**
        * Similar to collections you should initialize embeddables in the constructor!
        */
       public function __construct()
       {
           $this->address = new Address();
       }

       public function getId(): ?int
       {
           return $this->id;
       }

       public function getName(): ?string
       {
           return $this->name;
       }

       public function setName(?string $name): void
       {
           $this->name = $name;
       }

       public function getAddress(): Address
       {
           return $this->address;
       }

       public function setAddress(Address $address): void
       {
           $this->address = $address;
       }
   }

By default, the hydrator handles an embeddable as a single field holding
the embeddable object. To hydrate it, provide an instance of it:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($entityManager);
   $person = new Person();

   $address = new Address();
   $address->setPostalCode('48149');
   $address->setCity('Münster');

   $data = [
       'name'    => 'Mr. Example',
       'address' => $address,
   ];

   $person = $hydrator->hydrate($data, $person);

   echo $person->getAddress()->getPostalCode(); // prints "48149"
   echo $person->getAddress()->getCity();       // prints "Münster"

Likewise, extracting a ``Person`` returns the ``Address`` object under
the ``address`` key.

.. _embeddable-extraction:

Embeddables as Arrays
---------------------

To work with plain data instead of embeddable objects, e.g. for a JSON
API or a form, you can change how embeddables are represented. The setting
applies to both extracting and hydrating:

.. code:: php

   use Doctrine\Laminas\Hydrator\EmbeddableExtraction;

   $hydrator->setEmbeddableExtraction(EmbeddableExtraction::Array);

The following modes are available:

========================================== ==================================================
Mode                                       Embeddables in the data
========================================== ==================================================
``EmbeddableExtraction::Object``           Embeddable objects (default).
``EmbeddableExtraction::Array``            Nested arrays of the fields of the embeddables.
``EmbeddableExtraction::Flattened``        Fields of the embeddables in the data of the entity.
========================================== ==================================================

With ``EmbeddableExtraction::Array``, the ``Person`` above is extracted
and hydrated as follows:

.. code:: php

   [
       'id'      => 1,
       'name'    => 'Mr. Example',
       'address' => [
           'postalCode' => '48149',
           'city'       => 'Münster',
       ],
   ]

With ``EmbeddableExtraction::Flattened``, the name of the embeddable and
the name of the field are joined by a separator, which is ``__`` by default:

.. code:: php

   [
       'id'                  => 1,
       'name'                => 'Mr. Example',
       'address__postalCode' => '48149',
       'address__city'       => 'Münster',
   ]

The separator can be passed as second argument, e.g.
``$hydrator->setEmbeddableExtraction(EmbeddableExtraction::Flattened, '.')``
for ``address.postalCode``. Note that a dot does not work for HTML forms,
as PHP converts dots in the names of submitted fields to underscores, and
that a single underscore easily collides with the names of other fields
when using the ``UnderscoreNamingStrategy``. If a flattened key equals the
key of another field, a ``LogicException`` is thrown.

When hydrating:

- Nested arrays are hydrated with ``EmbeddableExtraction::Array`` only,
  flattened keys with ``EmbeddableExtraction::Flattened`` only.
  Embeddable objects are hydrated in all modes.
- Only the fields contained in the data are written. If the entity has no
  embeddable yet, a new one is created, using the instantiator if one is
  configured (see
  :ref:`Creating New Associated Entities <creating-new-associated-entities>`).
- The fields of embeddables are read and written like the fields of
  entities: by value using getters and setters, by reference using their
  properties. Embeddables without setters can therefore only be hydrated
  by reference, ``readonly`` properties cannot be hydrated.

The fields of embeddables are converted by their own Doctrine types, see
:doc:`Converting Custom DBAL Types <type-conversion>`. Strategies can be
added for them by their full name, as Doctrine lists them in the metadata:

.. code:: php

   $hydrator->addStrategy('address.postalCode', new PostalCodeStrategy());

Filters for the embeddable itself, e.g. for ``address``, apply to the
embeddable as a whole. A strategy for the embeddable itself is applied
when extracting, before the embeddable is converted; if the strategy
converts it into something else than an object, the result is kept as it
is. Nested arrays and flattened keys are hydrated without that strategy.
An entity without an embeddable is extracted with ``null`` under the name
of the embeddable. The naming strategy is applied to each part of the
name, e.g. ``address__postal_code`` with the ``UnderscoreNamingStrategy``.

Embeddables within embeddables are supported as well, e.g. as
``address__geo__lat``. Like the extraction of associations, the setting of
the hydrator ``hydrate()`` or ``extract()`` is called on also applies to the
associated entities. Embeddables are recognized by the field names of the
Doctrine ORM metadata, e.g. ``address.postalCode``.

Embeddables in Forms
--------------------

With Laminas forms, the fields of an embeddable can either be placed in a
fieldset bound to the embeddable object, or directly in the fieldset of the
entity using ``EmbeddableExtraction::Flattened``, see
:doc:`Example Usage with Laminas Form <laminas-form>`.

