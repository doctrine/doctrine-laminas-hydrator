A Complete Example using Laminas\\Form
======================================

This documentation covers how the hydrator integrates into the
Laminas Form component.  This interaction will be exemplified
using BlogPost and Tag entities.

The Entities
------------

The BlogPost entity:

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

       #[ORM\OneToMany(targetEntity: Tag::class, mappedBy: 'blogPost', cascade: ['persist'])]
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

The Tag entity:

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

Fieldsets
---------

Fieldsets will be required for each entity.  With Laminas it's a good
practice to create one fieldset per entity in order to reuse them
across many forms.

The Tag Fieldset:

Note in this example the hidden "id" input.  This is needed for editing.
Usually when a BlogPost is created it will have no tags and this "id"
will be empty.  But when the BlogPost is edited tags may already exist
in the database and will be referenced by this "id" input. This
allows modification of a tag name by modifying an existing Tag entity
without creating a new tag and removing the old one.

.. code:: php

   namespace Application\Form;

   use Application\Entity\Tag;
   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
   use Doctrine\Persistence\ObjectManager;
   use Laminas\Form\Element\Hidden;
   use Laminas\Form\Element\Text;
   use Laminas\Form\Fieldset;
   use Laminas\InputFilter\InputFilterProviderInterface;

   class TagFieldset extends Fieldset implements InputFilterProviderInterface
   {
       public function __construct(ObjectManager $objectManager)
       {
           parent::__construct('tag');

           $this->setHydrator(new DoctrineHydrator($objectManager))
                ->setObject(new Tag());

           $this->add([
               'type' => Hidden::class,
               'name' => 'id',
           ]);

           $this->add([
               'type'    => Text::class,
               'name'    => 'name',
               'options' => [
                   'label' => 'Tag',
               ],
           ]);
       }

       public function getInputFilterSpecification()
       {
           return [
               'id' => [
                   'required' => false,
               ],
               'name' => [
                   'required' => true,
               ],
           ];
       }
   }

The BlogPost Fieldset:

.. code:: php

   namespace Application\Form;

   use Application\Entity\BlogPost;
   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
   use Doctrine\Persistence\ObjectManager;
   use Laminas\Form\Element\Collection;
   use Laminas\Form\Element\Text;
   use Laminas\Form\Fieldset;
   use Laminas\InputFilter\InputFilterProviderInterface;

   class BlogPostFieldset extends Fieldset implements InputFilterProviderInterface
   {
       public function __construct(ObjectManager $objectManager)
       {
           parent::__construct('blog-post');

           $this->setHydrator(new DoctrineHydrator($objectManager))
                ->setObject(new BlogPost());

           $this->add([
               'type' => Text::class,
               'name' => 'title',
           ]);

           $tagFieldset = new TagFieldset($objectManager);
           $this->add([
               'type'    => Collection::class,
               'name'    => 'tags',
               'options' => [
                   'count'          => 2,
                   'target_element' => $tagFieldset,
               ],
           ]);
       }

       public function getInputFilterSpecification()
       {
           return [
               'title' => [
                   'required' => true,
               ],
           ];
       }
   }

The blog post is a simple fieldset with an element
of type ``Laminas\Form\Element\Collection`` that represents the
ManyToOne association to tags.

Form
----

Two forms will be necessary; one for creating and one for updating.
Forms are the "glue" between fieldsets.  For this example each form
will be identical, but that is not always the case
(for instance, you may want to disallow modification of the title
of the blog post when updating).

The CreateBlogPostForm:

.. code:: php

   namespace Application\Form;

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
   use Doctrine\Persistence\ObjectManager;
   use Laminas\Form\Form;

   class CreateBlogPostForm extends Form
   {
       public function __construct(ObjectManager $objectManager)
       {
           parent::__construct('create-blog-post-form');

           // The form will hydrate an object of type "BlogPost"
           $this->setHydrator(new DoctrineHydrator($objectManager));

           // Add the BlogPost fieldset, and set it as the base fieldset
           $blogPostFieldset = new BlogPostFieldset($objectManager);
           $blogPostFieldset->setUseAsBaseFieldset(true);
           $this->add($blogPostFieldset);

           // … add CSRF and submit elements …

           // Optionally set your validation group here
       }
   }

The UpdateBlogPostForm:

.. code:: php

   namespace Application\Form;

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
   use Doctrine\Persistence\ObjectManager;
   use Laminas\Form\Form;

   class UpdateBlogPostForm extends Form
   {
       public function __construct(ObjectManager $objectManager)
       {
           parent::__construct('update-blog-post-form');

           // The form will hydrate an object of type "BlogPost"
           $this->setHydrator(new DoctrineHydrator($objectManager));

           // Add the BlogPost fieldset, and set it as the base fieldset
           $blogPostFieldset = new BlogPostFieldset($objectManager);
           $blogPostFieldset->setUseAsBaseFieldset(true);
           $this->add($blogPostFieldset);

           // … add CSRF and submit elements …

           // Optionally set your validation group here
       }
   }

Controller
----------

Using the ServiceManager, inject your Doctrine object manager into
a controller.

.. code:: php

   namespace Application\Controller;

   use Doctrine\ORM\EntityManager;
   use Laminas\Mvc\Controller\AbstractActionController

   class MySampleController extends AbstractActionController
   {
       private EntityManager $entityManager;

       public function __construct(EntityManager $entityManager)
       {
           $this->entityManager = $entityManager;
       }
   }

For the ServiceManager, you will need a factory for the controller.  This is an
example of using the `reflection-based
factory <https://docs.laminas.dev/laminas-servicemanager/reflection-abstract-factory/>`__,
which injects all dependencies automatically.

.. code:: php

   use Application\Controller\MySampleController;
   use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;

   return [
       /* … */
       'controllers' => [
           'factories' => [
               MySampleController::class => ReflectionBasedAbstractFactory::class,
           ],
       ],
       /* … */

You may generate individual factories automatically using the `console
tools <https://docs.laminas.dev/laminas-servicemanager/console-tools/>`__
provided by Laminas.

Creation
~~~~~~~~

In the controller's createAction, create a new BlogPost and all the
associated tags. As a consequence, the hidden ids for the tags will by
empty because they have not been persisted yet.

The controller createAction:

.. code:: php

   public function createAction()
   {
       // Create the form and inject the EntityManager
       $form = new CreateBlogPostForm($this->entityManager);

       // Create a new, empty entity and bind it to the form
       $blogPost = new BlogPost();
       $form->bind($blogPost);

       if ($this->request->isPost()) {
           $form->setData($this->request->getPost());

           if ($form->isValid()) {
               $objectManager->persist($blogPost);
               $objectManager->flush();
           }
       }

       return ['form' => $form];
   }

The update form is similar but uses an existing blog post
instead of creating a new one:

.. code:: php

   public function editAction()
   {
       // Create the form and inject the EntityManager
       $form = new UpdateBlogPostForm($this->entityManager);

       // Fetch the existing BlogPost from storage and bind it to the form.
       // This will pre-fill form field values
       $blogPost = $this->userService->get($this->params('blogPost_id'));
       $form->bind($blogPost);

       if ($this->request->isPost()) {
           $form->setData($this->request->getPost());

           if ($form->isValid()) {
               // Save the changes
               $objectManager->flush();
           }
       }

       return ['form' => $form];
   }
