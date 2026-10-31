Example Usage in an API Context
===============================

APIs typically exchange entities as plain data, e.g. as JSON. This page
shows how to configure the hydrator for that purpose: associations and
embeddables are represented as nested arrays, enums and dates as scalar
values, and the keys of the data use snake case.

The Entities
------------

The example uses a blog post with an enum, a date, an author and tags. The
author has an address, which is an embeddable. Getters and setters are
omitted for brevity:

.. code:: php

   namespace Application\Entity;

   use DateTimeImmutable;
   use Doctrine\Common\Collections\ArrayCollection;
   use Doctrine\Common\Collections\Collection;
   use Doctrine\ORM\Mapping as ORM;

   enum PostStatus: string
   {
       case Draft = 'draft';
       case Published = 'published';
   }

   #[ORM\Entity]
   class BlogPost
   {
       #[ORM\Id]
       #[ORM\Column(type: 'integer')]
       #[ORM\GeneratedValue]
       private ?int $id = null;

       #[ORM\Column(type: 'string')]
       private ?string $title = null;

       #[ORM\Column(type: 'string', enumType: PostStatus::class)]
       private PostStatus $status = PostStatus::Draft;

       #[ORM\Column(type: 'datetime_immutable', nullable: true)]
       private ?DateTimeImmutable $publishedAt = null;

       #[ORM\ManyToOne(targetEntity: User::class)]
       private ?User $author = null;

       #[ORM\ManyToMany(targetEntity: Tag::class)]
       private Collection $tags;

       public function __construct()
       {
           $this->tags = new ArrayCollection();
       }

       // … getters and setters, addTags() and removeTags() …
   }

   #[ORM\Entity]
   class User
   {
       #[ORM\Id]
       #[ORM\Column(type: 'integer')]
       #[ORM\GeneratedValue]
       private ?int $id = null;

       #[ORM\Column(type: 'string')]
       private ?string $name = null;

       #[ORM\Column(type: 'string')]
       private ?string $password = null;

       #[ORM\Embedded(class: Address::class)]
       private Address $address;

       public function __construct()
       {
           $this->address = new Address();
       }

       // … getters and setters …
   }

   #[ORM\Embeddable]
   class Address
   {
       #[ORM\Column(type: 'string', nullable: true)]
       private ?string $postalCode = null;

       #[ORM\Column(type: 'string', nullable: true)]
       private ?string $city = null;

       // … getters and setters …
   }

   #[ORM\Entity]
   class Tag
   {
       #[ORM\Id]
       #[ORM\Column(type: 'integer')]
       #[ORM\GeneratedValue]
       private ?int $id = null;

       #[ORM\Column(type: 'string')]
       private ?string $name = null;

       // … getters and setters …
   }

Configuring the Hydrator
------------------------

The hydrator for the API is configured once, e.g. in a factory:

.. code:: php

   use Doctrine\Laminas\Hydrator\AssociationExtraction;
   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
   use Doctrine\Laminas\Hydrator\DoctrineTypeConverter;
   use Doctrine\Laminas\Hydrator\EmbeddableExtraction;
   use Doctrine\Laminas\Hydrator\Filter\PropertyName;
   use Doctrine\ORM\EntityManagerInterface;
   use Laminas\Hydrator\NamingStrategy\UnderscoreNamingStrategy;

   function createApiHydrator(EntityManagerInterface $entityManager): DoctrineHydrator
   {
       // Extract enums as their backing values and dates as strings
       $hydrator = new DoctrineHydrator(
           $entityManager,
           typeConverter: new DoctrineTypeConverter(extractEnums: true, extractDateTimes: true),
       );

       // Represent associated entities and embeddables as nested arrays
       $hydrator->setAssociationExtraction(AssociationExtraction::Array);
       $hydrator->setEmbeddableExtraction(EmbeddableExtraction::Array);

       // Use snake case keys, e.g. "published_at"
       $hydrator->setNamingStrategy(new UnderscoreNamingStrategy());

       // Never expose the passwords of users
       $hydrator->getAssociationHydrator(User::class)
           ->addFilter('password', new PropertyName(['password']));

       return $hydrator;
   }

The type converter, the naming strategy and the representation of
associations and embeddables apply to associated entities as well. Filters
and strategies are configured per class, as the filter for users above, see
:ref:`Configuring Associated Entities <association-hydrators>`.

Reading Entities
----------------

To return an entity, extract it and encode the data:

.. code:: php

   $hydrator = createApiHydrator($entityManager);
   $blogPost = $entityManager->find(BlogPost::class, 5);

   echo json_encode($hydrator->extract($blogPost), JSON_THROW_ON_ERROR);

This results in:

.. code:: json

   {
       "id": 5,
       "title": "Doctrine Laminas Hydrator 4.0",
       "status": "published",
       "published_at": "2026-10-29T14:30:00+01:00",
       "author": {
           "id": 2,
           "name": "Jane",
           "address": {
               "postal_code": "20095",
               "city": "Hamburg"
           }
       },
       "tags": [
           {"id": 3, "name": "php"},
           {"id": 8, "name": "doctrine"}
       ]
   }

Only the associations of the blog post are extracted as arrays, the
associations of the author would be extracted as identifiers. To extract
more levels, pass the maximum depth to ``setAssociationExtraction()``, see
:ref:`Extracting Associations <extracting-associations>`.

When extracting lists of entities, fetch the associations you extract
along with the entities, so they are not loaded one by one:

.. code:: php

   $blogPosts = $entityManager->createQueryBuilder()
       ->select('post', 'author', 'tag')
       ->from(BlogPost::class, 'post')
       ->leftJoin('post.author', 'author')
       ->leftJoin('post.tags', 'tag')
       ->getQuery()
       ->getResult();

   echo json_encode(array_map($hydrator->extract(...), $blogPosts), JSON_THROW_ON_ERROR);

See :doc:`Performance Considerations <performance-considerations>` for
details.

Writing Entities
----------------

Unlike when extracting, filters do not apply when hydrating: every field
of the entity contained in the data is written, including its identifier
and its associations. Moreover, if the data contains an identifier, the
hydrator loads the entity with that identifier and hydrates it instead of
the given one. Therefore, only pass the fields clients are allowed to
change, and validate them, e.g. using
`laminas-inputfilter <https://docs.laminas.dev/laminas-inputfilter/>`__:

.. code:: php

   $input = json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);

   // Only accept the fields clients are allowed to change
   $data = array_intersect_key($input, array_flip(['title', 'status', 'published_at', 'author', 'tags']));

Creating an entity hydrates a new instance:

.. code:: php

   $blogPost = $hydrator->hydrate($data, new BlogPost());

   $entityManager->persist($blogPost);
   $entityManager->flush();

with a request like:

.. code:: json

   {
       "title": "Doctrine Laminas Hydrator 4.0",
       "status": "draft",
       "author": 2,
       "tags": [3, 8]
   }

The status is converted to ``PostStatus::Draft`` based on the ``enumType``
of its mapping, and the author and the tags are loaded by their
identifiers. Identifiers are accepted for associations regardless of the
association extraction, so clients can reference existing entities
without sending their data.

Updating an entity hydrates the existing instance. Only the fields
contained in the data are written, so the same code works for partial
updates:

.. code:: php

   $blogPost = $entityManager->find(BlogPost::class, $id);

   $hydrator->hydrate($data, $blogPost);
   $entityManager->flush();

Note that a collection given in the data replaces the elements of the
collection: ``"tags": [3]`` removes all other tags of the blog post. To only
add elements, use a ``DisallowRemove*`` strategy, see
:doc:`Working with Associations <associations>`.

Nested Data
~~~~~~~~~~~

The hydrator also accepts nested arrays for associations, in the format
they are extracted with ``AssociationExtraction::Array``. A nested array is
hydrated into the referenced entity, so a request like
``"author": {"id": 2, "name": "Joe"}`` renames the user with the identifier
``2``. Only accept nested data for associations if clients are allowed to
change the associated entities, and restrict their fields as well.
Otherwise, accept identifiers only.

Embeddables are hydrated from nested arrays as they are extracted, writing
only the given fields. For example, updating a user with
``{"address": {"city": "Berlin"}}`` changes the city and keeps the postal
code. If you prefer flat data, e.g. ``"address__city"``, use
``EmbeddableExtraction::Flattened`` instead, see
:ref:`Embeddables as Arrays <embeddable-extraction>`.

Invalid Data
~~~~~~~~~~~~

Values that cannot be converted cause exceptions while hydrating, e.g. a
``ValueError`` for an invalid enum value, or an exception of
``DateTimeImmutable`` for an invalid date. Validate the data before
hydrating it to return a meaningful error response to the client.
