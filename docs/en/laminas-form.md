# A complete example using Laminas\Form

Now that we understand how the hydrator works, let's see how it integrates into the Laminas' Form component.
We are going to use a simple example with, once again, a BlogPost and a Tag entities. We will see how we can create the
blog post, and being able to edit it.

## The entities

First, let's define the (simplified) entities, beginning with the BlogPost entity:

```php
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
```

And then the Tag entity:

```php
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
```

## The fieldsets

We now need to create two fieldsets that will map those entities. With Laminas it's a good practice to create
one fieldset per entity in order to reuse them across many forms.

Here is the fieldset for the Tag. Notice that in this example, I added a hidden input whose name is "id". This is
needed for editing. Most of the time, when you create the Blog Post for the first time, the tags do not exist.
Therefore, the id will be empty. However, when you edit the blog post, all the tags already exist in database (they
have been persisted and have an id), and hence the hidden "id" input will have a value. This allows you to modify a tag
name by modifying an existing Tag entity without creating a new tag (and removing the old one).

```php
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
```

And the BlogPost fieldset:

```php
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
```

Plain and easy. The blog post is just a simple fieldset with an element type of ``Laminas\Form\Element\Collection``
that represents the ManyToOne association.

## The form

Now that we have created our fieldset, we will create two forms: one form for creation and one form for updating.
The form's purpose is to be the glue between the fieldsets. In this simple example, both forms are exactly the same,
but in a real application, you may want to change this behaviour by changing the validation group (for instance, you
may want to disallow the user to modify the title of the blog post when updating).

Here is the create form:

```php
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
```

And the update form:

```php
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
```

## The controllers

We now have everything. Let's create the controllers. First, you will need to make sure that you inject Doctrine's
entity manager into your controllers using dependency injection. Your controller should look like this:

```php
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
```

You will need to set up a factory for your controller. To get started you may use a
[reflection-based factory](https://docs.laminas.dev/laminas-servicemanager/reflection-abstract-factory/), which injects
all dependencies automatically. This is what the configuration needs to look like:

```php
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
```

Later you can - and probably should - generate individual factories automatically using the
[console tools](https://docs.laminas.dev/laminas-servicemanager/console-tools/) provided by Laminas. This will increase
your application's performance in production deployments.


### Creation

In the createAction, we will create a new BlogPost and all the associated tags. As a consequence, the hidden ids
for the tags will by empty (because they have not been persisted yet).

Here is the action for create a new blog post:

```php
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
```

The update form is similar, instead that we get the blog post from database instead of creating an empty one:

```php
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
```
