Basic Usage
===========

The library ships with a very powerful hydrator that allow almost any
use-case.

Create a Hydrator
-----------------

To create a Doctrine Hydrator, you just need one thing: an object
manager (also called Entity Manager in Doctrine ORM or Document Manager
in Doctrine ODM):

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($objectManager);

The hydrator constructor also allows a second parameter, ``byValue``,
which is true by default. We will come back later about this
distinction, but to be short, it allows the hydrator the change the way
it gets/sets data by either accessing the public API of your entity
(getters/setters) or directly get/set data through reflection, hence
bypassing any of your custom logic.

Example 1: Simple Entity with no Associations
---------------------------------------------

Let’s begin by a simple example:

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

As you can see from this example, in simple cases, the Doctrine Hydrator
provides nearly no benefits over a simpler hydrator like ``ClassMethods``.
However, even in those cases, I suggest you to use it, as it performs
automatic conversions between types. For instance, it can convert
timestamp to DateTime (which is the type used by Doctrine to represent
dates):

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
DateTime object during the hydration, hence allowing us to have a nice
API in our entity with correct typehint.

Example 2: OneToOne/ManyToOne Associations
------------------------------------------

Doctrine Hydrator is especially useful when dealing with associations
(OneToOne, OneToMany, ManyToOne) and integrates nicely with the
Form/Fieldset logic (`learn more about this
here <https://docs.laminas.dev/laminas-form/collections/>`__).

Let’s take a simple example with a BlogPost and a User entity to
illustrate OneToOne association:

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

There are two use cases that can arise when using OneToOne association:
the toOne entity (in this case, the User) may already exist (which will
often be the case with a User and BlogPost example), or it can be
created. The DoctrineHydrator natively supports both cases.

Existing Entity in the Association
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

When the association’s entity already exists, all you need to do is
simply give the identifier of the association:

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

If the association’s entity does not exist, you just need to give the
object:

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
   echo $blogPost->getUser()->getId(); // prints 2

For this to work, you must also slightly change your mapping, so that
Doctrine can persist new entities on associations (note the cascade
options on the ManyToOne association):

.. code:: php

   namespace Application\Entity;

   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Entity]
   class BlogPost
   {
       /** .. */

       #[ORM\ManyToOne(targetEntity: User::class, cascade: ['persist'])] 
       private ?User $user = null;

       /** … */
   }

It’s also possible to use a nested fieldset for the User data. The
hydrator will use the mapping data to determine the identifiers for the
toOne relation and either attempt to find the existing record or
instanciate a new target instance which will be hydrated before it is
passed to the BlogPost entity.

**NOTE** : you’re not really allowing users to be added via a blog post,
are you?

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($entityManager, BlogPost::class);
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

Example 3: OneToMany Association
--------------------------------

Doctrine Hydrator also handles OneToMany relationships (when use
``Laminas\Form\Element\Collection`` element). Please refer to the
official `Laminas
documentation <https://docs.laminas.dev/laminas-form/collections/>`__ to
learn more about Collection.

   Note: internally, for a given collection, if an array contains
   identifiers, the hydrator automatically fetches the objects through
   the Doctrine ``find`` function. However, this may cause problems if
   one of the values of the collection is the empty string ’’ (as the
   ``find`` will most likely fail). In order to solve this problem,
   empty string identifiers are simply ignored during the hydration
   phase. Therefore, if your database contains an empty string value as
   primary key, the hydrator could not work correctly (the simplest way
   to avoid that is simply to not have an empty string primary key,
   which should not happen if you use auto-increment primary keys,
   anyway).

Let’s take again a simple example: a BlogPost and Tag entities.

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

Please note some interesting things in BlogPost entity. We have defined
two functions: addTags and removeTags. Those functions must be always
defined and are called automatically by Doctrine hydrator when dealing
with collections. You may think this is overkill, and ask why you cannot
just define a ``setTags`` function to replace the old collection by the
new one:

.. code:: php

   public function setTags(Collection $tags): void
   {
       $this->tags = $tags;
   }

But this is very bad, because Doctrine collections should not be
swapped, mostly because collections are managed by an ObjectManager,
thus they must not be replaced by a new instance.

Once again, two cases may arise: the tags already exist or they do not.

Example 4: Embedded Entities
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Doctrine provides so-called embeddables as a layer of abstraction which
allow reusing partial object across entities. For example, one might
have an entity ``Address`` which is not only used for a ``Person``, but
probably for an ``Organisation`` as well. Let’s have a look at the
classes. First we have a ``Tag`` class, which will be our embeddable:

.. code:: php

   namespace Application\Entity;

   use Doctrine\ORM\Mapping as ORM;

   /**
    * Address class for embedding in entities.
    */
   #[ORM\Embeddable]
   class Tag
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

   <?php

   namespace Application\Entity;

   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Entity]
   class Person 
   {
       #[ORM\Id]
       #[ORM\GeneratedValue]
       private ?int $id = null;

       #[ORM\Column(type: 'string', nullable: true)]
       private ?string $name = null;

       #[ORM\Embedded(class: 'Address')]
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
   }

The hydrator provided by this module will require the data for the
embeddable to be in a separate array as follows:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($entityManager);
   $person = new Person();
   $data = [
       'name' => 'Mr. Example',
       'address'  => [
           [
               'postalCode' => '48149',
               'city' => 'Münster',
           ],
       ],
   ];

   $person = $hydrator->hydrate($data, $person);

   echo $person->getAddress()->getPostalCode(); // prints "48149"
   echo $person->getAddress()->getCity();       // prints "Münster"

.. _existing-entity-in-the-association-1:

Existing Entity in the Association
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

When the association’s entity already exists, what you need to do is
simply give the identifiers of the entities:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

   $hydrator = new DoctrineHydrator($entityManager);
   $blogPost = new BlogPost();
   $data = [
       'title' => 'The best blog post in the world!',
       'tags'  => [
           ['id' => 3], // add tag whose id is 3
           ['id' => 8], // also add tag whose id is 8
       ],
   ];

   $blogPost = $hydrator->hydrate($data, $blogPost);

   echo $blogPost->getTitle(); // prints "The best blog post in the world!"
   echo count($blogPost->getTags()); // prints 2

**NOTE** : once again, this:

.. code:: php

   $data = [
       'title' => 'The best blog post in the world!',
       'tags'  => [
           ['id' => 3], // add tag whose id is 3
           ['id' => 8], // also add tag whose id is 8
       ],
   ];

can be written:

.. code:: php

   $data = [
       'title' => 'The best blog post in the world!',
       'tags'  => [3, 8],
   ];

.. _non-existing-entity-in-the-association-1:

Non-existing Entity in the Association
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

If the association’s entity does not exist, you just need to give the
object:

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
       'title' => 'The best blog post in the world!',
       'tags'  => $tags, // Note that you can mix integers and entities without any problem
   ];

   $blogPost = $hydrator->hydrate($data, $blogPost);

   echo $blogPost->getTitle(); // prints "The best blog post in the world!"
   echo count($blogPost->getTags()); // prints 2

For this to work, you must also slightly change your mapping, so that
Doctrine can persist new entities on associations (note the cascade
options on the OneToMany association):

.. code:: php

   namespace Application\Entity;

   use Doctrine\ORM\Mapping as ORM;
   use Doctrine\Common\Collections\Collection;

   #[ORM\Entity]
   class BlogPost
   {
       /** .. */

       #[ORM\OneToMany(targetEntity: Tag::class, mappedBy: 'blogPost', cascade: ['persist'])]
       private Collection $tags;

       /** … */
   }

Handling of Null Values
~~~~~~~~~~~~~~~~~~~~~~~

When a null value is passed to a OneToOne or ManyToOne field, for
example;

.. code:: php

   $data = [
       'city' => null,
   ];

The hydrator will check whether the setCity() method on the Entity
allows null values and act accordingly. The following describes the
process that happens when a null value is received:

1. If the setCity() method DOES NOT allow null values
   i.e. ``function setCity(City $city)``, the null is silently ignored
   and will not be hydrated.
2. If the setCity() method DOES allow null values
   i.e. ``function setCity(City $city = null)``, the null value will be
   hydrated.
