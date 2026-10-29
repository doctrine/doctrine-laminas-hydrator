Basic Usage
===========

This library ships with a powerful hydrator that allows almost any use-case.

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
bypassing any of your custom logic. See
:doc:`By Value and By Reference <by-value-by-reference>` for details.

Example 1: Simple Entity with no Associations
---------------------------------------------

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
:doc:`Type Conversion <type-conversion>` for all conversions.

Example 2: OneToOne/ManyToOne Associations
------------------------------------------

Doctrine hydrator is especially useful when dealing with associations
(OneToOne, OneToMany, ManyToOne) and integrates nicely with the
Form/Fieldset logic (`learn more about this
here <https://docs.laminas.dev/laminas-form/collections/>`__).

A simple example with BlogPost and User entities to
illustrate a ManyToOne association:

.. code:: php

   namespace Application\Entity;

   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Entity]
   class User
   {
       #[ORM\Id]
       #[ORM\Column(type: 'integer')]
       #[ORM\GeneratedValue(strategy: 'AUTO')]
       private ?int $id = null;

       #[ORM\Column(type: 'string', length: 48)]
       private ?string $username = null;

       #[ORM\Column(type: 'string')]
       private ?string $password = null;

       public function getId(): ?int
       {
           return $this->id;
       }

       public function setUsername(string $username): void
       {
           $this->username = $username;
       }

       public function getUsername(): ?string
       {
           return $this->username;
       }

       public function setPassword(string $password): void
       {
           $this->password = $password;
       }

       public function getPassword(): ?string
       {
           return $this->password;
       }
   }

And the BlogPost entity, with a ManyToOne association:

.. code:: php

   namespace Application\Entity;

   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Entity]
   class BlogPost
   {
       #[ORM\Id]
       #[ORM\Column(type: 'integer')]
       #[ORM\GeneratedValue(strategy: 'AUTO')]
       private ?int $id = null;

       #[ORM\ManyToOne(targetEntity: User::class)]
       private ?User $user = null;

       #[ORM\Column(type: 'string')]
       private ?string $title = null;

       public function getId(): ?int
       {
           return $this->id;
       }

       public function setUser(User $user): void
       {
           $this->user = $user;
       }

       public function getUser(): ?User
       {
           return $this->user;
       }

       public function setTitle(string $title): void
       {
           $this->title = $title;
       }

       public function getTitle(): ?string
       {
           return $this->title;
       }
   }

There are two use cases that can arise when using OneToOne associations:
the toOne entity (in this case, the User) may already exist (which will
often be the case with a User and BlogPost example), or it needs to be
created. The Doctrine hydrator natively supports both cases.

Existing Entity in the Association
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

When the association’s entity already exists, all you need to do is
give the identifier of the association:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($entityManager);
   $blogPost = new BlogPost();
   $data = [
       'title' => 'The best blog post in the world!',
       'user'  => [
           'id' => 2, // Written by user 2
       ],
   ];

   $blogPost = $hydrator->hydrate($data, $blogPost);

   echo $blogPost->getTitle(); // prints "The best blog post in the world!"
   echo $blogPost->getUser()->getId(); // prints 2

**NOTE** : when using association whose primary key is not compound, you
can rewrite the following more succinctly:

.. code:: php

   $data = [
       'title' => 'The best blog post in the world!',
       'user'  => [
           'id' => 2, // Written by user 2
       ],
   ];

to:

.. code:: php

   $data = [
       'title' => 'The best blog post in the world!',
       'user'  => 2,
   ];

Non-existing Entity in the Association
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

If the association’s entity does not exist, you just need to provide
the actual object to the hydrator:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($entityManager);
   $blogPost = new BlogPost();
   $user = new User();
   $user->setUsername('bakura');
   $user->setPassword('p@$$w0rd');

   $data = [
       'title' => 'The best blog post in the world!',
       'user'  => $user,
   ];

   $blogPost = $hydrator->hydrate($data, $blogPost);

   echo $blogPost->getTitle(); // prints "The best blog post in the world!"
   echo $blogPost->getUser()->getUsername(); // prints "bakura"

For this to work, you must also slightly change your mapping, so that
Doctrine can persist new entities on associations (note the cascade
options on the ManyToOne association):

.. code:: php

   namespace Application\Entity;

   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Entity]
   class BlogPost
   {
       /** … */

       #[ORM\ManyToOne(targetEntity: User::class, cascade: ['persist'])]
       private ?User $user = null;

       /** … */
   }

It’s also possible to provide the User data as a nested array. The
hydrator will use the mapping data to determine the identifiers for the
toOne relation and either attempt to find the existing record or
instantiate a new target instance which will be hydrated before it is
passed to the BlogPost entity. The nested data is hydrated by a separate
hydrator, see :ref:`Configuring Associated Entities <association-hydrators>`.

.. note::

   Adding users via a blog post is not recommended.

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($entityManager);
   $blogPost = new BlogPost();

   $data = [
       'title' => 'Art thou mad?',
       'user' => [
           'id' => '',
           'username' => 'willshakes',
           'password' => '2BorN0t2B',
       ],
   ];

   $blogPost = $hydrator->hydrate($data, $blogPost);

   echo $blogPost->getUser()->getUsername(); // prints willshakes
   echo $blogPost->getUser()->getPassword(); // prints 2BorN0t2B

Creating New Associated Entities
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

By default, new associated entities are created by calling their
constructor without arguments, before they are hydrated with the data.
This applies to OneToOne and ManyToOne associations as well as to
collections. If the constructor of an entity requires arguments, pass a
closure creating the entities to the hydrator. The closure receives the
class name and the data the entity will be hydrated with:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator(
       $entityManager,
       instantiator: static fn (string $className, array $data): object => match ($className) {
           User::class => new User($data['username']),
           default => new $className(),
       },
   );

The closure must return an instance of the given class. To create entities
without calling their constructor, as Doctrine does when loading entities,
you can use `doctrine/instantiator <https://github.com/doctrine/instantiator>`__:

.. code:: php

   use Doctrine\Instantiator\Instantiator;

   $instantiator = new Instantiator();
   $hydrator     = new DoctrineHydrator(
       $entityManager,
       instantiator: static fn (string $className): object => $instantiator->instantiate($className),
   );

Note that collections and other properties initialized in the constructor
are not initialized then. The instantiator is inherited by the hydrators
of associated entities, see
:ref:`Configuring Associated Entities <association-hydrators>`.

Handling of Null Values
~~~~~~~~~~~~~~~~~~~~~~~

When a null value is passed to a OneToOne or ManyToOne field, for
example:

.. code:: php

   $data = [
       'user' => null,
   ];

The hydrator will check whether the ``setUser()`` method on the entity
allows null values and act accordingly. The following describes the
process that happens when a null value is received:

1. If the ``setUser()`` method DOES NOT allow null values
   i.e. ``function setUser(User $user)``, the null is silently ignored
   and will not be hydrated.
2. If the ``setUser()`` method DOES allow null values
   i.e. ``function setUser(?User $user)``, the null value will be
   hydrated.

When hydrating by reference, the type of the ``$user`` property is
checked the same way: for ``private User $user``, the null is silently
ignored, while for ``private ?User $user`` (or an untyped property) the
null value will be hydrated.

Example 3: OneToMany Association
--------------------------------

Doctrine hydrator can also handle OneToMany relationships (when using a
``Laminas\Form\Element\Collection`` element). Please refer to the
`Laminas documentation
<https://docs.laminas.dev/laminas-form/collections/>`__ to learn more
about Collection elements.

.. note::

   Internally, for a given collection, if an array contains
   identifiers, the hydrator automatically fetches the objects through
   the Doctrine ``find`` function. However, this may cause problems if
   one of the values of the collection is the empty string ’’ (as the
   ``find`` will most likely fail). In order to solve this problem,
   empty string identifiers are simply ignored during the hydration
   phase. Therefore, if your database contains an empty string value as
   a primary key, the hydrator may not work correctly (the simplest way
   to avoid that is simply to not have an empty string primary key,
   which should not happen if you use auto-increment primary keys,
   anyway).

For example, consider the following BlogPost and Tag entities:

.. code:: php

   namespace Application\Entity;

   use Doctrine\Common\Collections\ArrayCollection;
   use Doctrine\Common\Collections\Collection;
   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Entity]
   class BlogPost
   {
       #[ORM\Id]
       #[ORM\Column(type: 'integer')]
       #[ORM\GeneratedValue(strategy: 'AUTO')]
       private ?int $id = null;

       #[ORM\OneToMany(targetEntity: Tag::class, mappedBy: 'blogPost')]
       private Collection $tags;

       /**
        * Never forget to initialize your collections!
        */
       public function __construct()
       {
           $this->tags = new ArrayCollection();
       }

       public function getId(): ?int
       {
           return $this->id;
       }

       public function addTags(Collection $tags): void
       {
           foreach ($tags as $tag) {
               $tag->setBlogPost($this);
               $this->tags->add($tag);
           }
       }

       public function removeTags(Collection $tags): void
       {
           foreach ($tags as $tag) {
               $tag->setBlogPost(null);
               $this->tags->removeElement($tag);
           }
       }

       public function getTags(): Collection
       {
           return $this->tags;
       }
   }

And the Tag entity:

.. code:: php

   namespace Application\Entity;

   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Entity]
   class Tag
   {
       #[ORM\Id]
       #[ORM\Column(type: 'integer')]
       #[ORM\GeneratedValue(strategy: 'AUTO')]
       private ?int $id = null;

       #[ORM\ManyToOne(targetEntity: BlogPost::class, inversedBy: 'tags')]
       private ?BlogPost $blogPost = null;

       #[ORM\Column(type: 'string')]
       private ?string $name = null;

       public function getId(): ?int
       {
           return $this->id;
       }

       /**
        * Allow null to remove association
        */
       public function setBlogPost(?BlogPost $blogPost = null): void
       {
           $this->blogPost = $blogPost;
       }

       public function getBlogPost(): ?BlogPost
       {
           return $this->blogPost;
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

Please note some interesting things in the BlogPost entity. There are defined
two functions: addTags and removeTags. Those functions must be always
defined and are called automatically by the Doctrine hydrator when dealing
with collections. This is not overkill and is preferred to just a
``setTags`` function to replace the old collection with a new one:

.. code:: php

   public function setTags(Collection $tags): void
   {
       $this->tags = $tags;
   }

This is considered a bad design because Doctrine collections
should not be swapped; mostly because collections are managed by an
object manager and must not be replaced by a new instance. See
:doc:`Collections Strategy <collections-strategy>` for how adders and
removers are used.

Once again, two cases may arise: the tags already exist or they do not.

Existing Entities in the Association
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

When the association’s entities already exist, just
provide the identifiers of these entities:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($entityManager);
   $blogPost = new BlogPost();
   $data = [
       'tags'  => [
           ['id' => 3], // add tag whose id is 3
           ['id' => 8], // also add tag whose id is 8
       ],
   ];

   $blogPost = $hydrator->hydrate($data, $blogPost);

   echo count($blogPost->getTags()); // prints 2

Note, again, that

.. code:: php

   $data = [
       'tags'  => [
           ['id' => 3], // add tag whose id is 3
           ['id' => 8], // also add tag whose id is 8
       ],
   ];

can be written:

.. code:: php

   $data = [
       'tags'  => [3, 8],
   ];

Non-existing Entities in the Association
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

If the association’s entities do not exist, you need to provide
the actual objects:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($entityManager);
   $blogPost = new BlogPost();

   $tags = [];

   $tag1 = new Tag();
   $tag1->setName('PHP');
   $tags[] = $tag1;

   $tag2 = new Tag();
   $tag2->setName('STL');
   $tags[] = $tag2;

   $data = [
       'tags'  => $tags, // Note that you can mix integers and entities without any problem
   ];

   $blogPost = $hydrator->hydrate($data, $blogPost);

   echo count($blogPost->getTags()); // prints 2

For this to work, you must also slightly change your mapping, so that
Doctrine can persist new entities on associations (note the cascade
options on the OneToMany association):

.. code:: php

   namespace Application\Entity;

   use Doctrine\Common\Collections\Collection;
   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Entity]
   class BlogPost
   {
       /** … */

       #[ORM\OneToMany(targetEntity: Tag::class, mappedBy: 'blogPost', cascade: ['persist'])]
       private Collection $tags;

       /** … */
   }

Example 4: Embeddables
----------------------

Doctrine provides so-called embeddables as a layer of abstraction that
allows reusing partial objects across entities. For example, one might
have an ``Address`` which is not only used for a ``Person``, but
also for an ``Organization``.
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

The hydrator handles an embeddable as a single field holding the
embeddable object. It does not convert array data into an embeddable,
so provide an instance of it (or add a strategy for the field that
creates one from your data):

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

.. _extracting-associations:

Extracting Associations
-----------------------

By default, ``extract()`` returns associations as they are: a OneToOne or
ManyToOne association is returned as the associated entity (which may be
an uninitialized proxy), a OneToMany or ManyToMany association as the
collection (e.g. a ``PersistentCollection``). This is exactly what
Laminas forms need to bind nested fieldsets to the associated entities,
see :doc:`Laminas Form <laminas-form>`.

When you need plain data instead, for instance to serialize an entity
to JSON, you can change how associations are extracted:

.. code:: php

   use Doctrine\Laminas\Hydrator\AssociationExtraction;
   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($entityManager);
   $hydrator->setAssociationExtraction(AssociationExtraction::Identifier);

The following modes are available:

========================================== ==================================================
Mode                                       Extracted associations
========================================== ==================================================
``AssociationExtraction::Object``          Entities and collections as they are (default).
``AssociationExtraction::Identifier``      Identifiers of the associated entities.
``AssociationExtraction::Array``           Associated entities extracted into nested arrays.
========================================== ==================================================

The following examples use a BlogPost with the ``user`` association from
example 2 and the ``tags`` association from example 3.

Identifiers
~~~~~~~~~~~

With ``AssociationExtraction::Identifier``, each associated entity is
replaced by its identifier, collections become arrays of identifiers:

.. code:: php

   $hydrator->setAssociationExtraction(AssociationExtraction::Identifier);

   $data = $hydrator->extract($blogPost);

   // [
   //     'id'    => 5,
   //     'title' => 'The best blog post in the world!',
   //     'user'  => 2,
   //     'tags'  => [3, 8],
   // ]

Entities with a composite identifier are extracted as an array of their
identifier fields, e.g. ``['country' => 'DE', 'code' => '48149']``. An
empty OneToOne or ManyToOne association is extracted as ``null``.

Nested Arrays
~~~~~~~~~~~~~

With ``AssociationExtraction::Array``, associated entities are extracted
into arrays themselves:

.. code:: php

   $hydrator->setAssociationExtraction(AssociationExtraction::Array);

   $data = $hydrator->extract($blogPost);

   // [
   //     'id'    => 5,
   //     'title' => 'The best blog post in the world!',
   //     'user'  => ['id' => 2, 'username' => 'bakura', 'password' => 'p@$$w0rd'],
   //     'tags'  => [
   //         ['id' => 3, 'name' => 'PHP', 'blogPost' => 5],
   //         ['id' => 8, 'name' => 'STL', 'blogPost' => 5],
   //     ],
   // ]

By default, only the associations of the extracted entity are converted
to arrays; associations of nested entities are extracted as identifiers.
Pass the maximum depth as second argument to extract more levels:

.. code:: php

   $hydrator->setAssociationExtraction(AssociationExtraction::Array, 3);

Circular references are detected: an entity that is already being
extracted further up, like the blog post of each tag above, is extracted
as its identifier, regardless of the maximum depth.

Associated entities are extracted by separate hydrators. Strategies and
filters added to your hydrator only apply to the extracted entity itself.
To exclude fields of associated entities, like the password of the user
above, configure the hydrator used for them, see
:ref:`Configuring Associated Entities <association-hydrators>`:

.. code:: php

   use Doctrine\Laminas\Hydrator\Filter\PropertyName;

   $hydrator->getAssociationHydrator(User::class)->addFilter('password', new PropertyName(['password']));

Further Notes
~~~~~~~~~~~~~

- Values that a strategy has already converted are left untouched; only
  instances of the association's target entity are converted.
- Keys of collections are preserved, which matters for collections
  indexed by a field.
- Embeddables are no associations and are always extracted as objects.
- The extracted data can be passed to ``hydrate()`` again, as the
  hydrator accepts both identifiers and nested arrays for associations.
- Extracting associations as arrays loads the associated entities from
  the database, see
  :doc:`Performance Considerations <performance-considerations>`.

.. _association-hydrators:

Configuring Associated Entities
-------------------------------

Strategies, filters and the naming strategy of a hydrator refer to the
fields of an entity. Therefore, they only apply to the entity passed to
``hydrate()`` or ``extract()``. Associated entities are hydrated and
extracted by separate hydrators, one per entity class, which are used at
all levels of associations.

These association hydrators are used in two cases:

- When hydrating data containing nested arrays for an association, like
  ``['user' => ['username' => 'willshakes']]``. Identifiers and objects
  given for an association do not need to be hydrated.
- When extracting associations with ``AssociationExtraction::Array``.

To configure the hydrator for entities of a class, retrieve it from your
hydrator:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
   use Doctrine\Laminas\Hydrator\Filter\PropertyName;

   $hydrator = new DoctrineHydrator($entityManager);

   // Applies to the blog post passed to hydrate() or extract()
   $hydrator->addStrategy('title', new TitleStrategy());

   // Applies to every user associated with the blog post, at any level
   $hydrator->getAssociationHydrator(User::class)
       ->addFilter('password', new PropertyName(['password']));

Association hydrators inherit the following settings from your hydrator:
the object manager, hydrating by value or by reference, the type converter,
the instantiator, the default strategies for collections and the naming
strategy. Strategies and filters are not inherited. The naming strategy
can be changed or removed on an association hydrator, and the way
associations are extracted is always determined by the hydrator
``extract()`` is called on.

To use a hydrator with different settings, e.g. one hydrating by
reference, set it for the class:

.. code:: php

   $hydrator->setAssociationHydrator(Address::class, new DoctrineHydrator($entityManager, false));

A hydrator set this way keeps its own settings and only inherits the
naming strategy, unless a naming strategy is set on it.

Association hydrators are always configured on the hydrator you call
``hydrate()`` or ``extract()`` on. Also note that the entity passed to
``hydrate()`` or ``extract()`` is always handled by your hydrator itself,
even if an association hydrator is configured for its class.

When using Laminas forms, nested fieldsets hydrate and extract their
objects with their own hydrators, so association hydrators are usually
not involved there, see :doc:`Laminas Form <laminas-form>`.
