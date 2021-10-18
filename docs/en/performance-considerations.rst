Performance considerations
==========================

Although using the hydrator is like magical as it abstracts most of the
tedious task, you have to be aware that it can leads to performance
issues in some situations. Please carefully read the following
paragraphs in order to know how to solve (and avoid!) them.

Unwanted side-effects
---------------------

You have to be very careful when you are using Doctrine Hydrator with
complex entities that contain a lot of associations, as a lot of
unnecessary calls to database can be made if you are not perfectly aware
of what happen under the hood. To explain this problem, let’s have an
example.

Imagine the following entity :

.. code:: php

   namespace Application\Entity;

   #[ORM\Entity]
   class User
   {
       #[ORM\Id]
       #[ORM\Column(type: 'integer')]
       #[ORM\GeneratedValue(strategy: 'AUTO')]
       private ?int $id = null;

       #[ORM\Column(type: 'string', length=48)]
       private ?string $name = null;

       #[ORM\OneToOne(targetEntity: 'City')]
       private ?City $city = null;

       // … getter and setters are defined …
   }

This simple entity contains an id, a string property, and a OneToOne
relationship. If you are using Laminas forms the correct way, you will
likely have a fieldset for every entity, so that you have a perfect
mapping between entities and fieldsets. Here are fieldsets for User and
and City entities.

   If you are not comfortable with Fieldsets and how they should work,
   please refer to `this part of Laminas
   documentation <https://docs.laminas.dev/laminas-form/collections/>`__.

First the User fieldset :

.. code:: php

   namespace Application\Form;

   use Application\Entity\User;
   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
   use Doctrine\Persistence\ObjectManager;
   use Laminas\Form\Element\Text;
   use Laminas\Form\Fieldset;
   use Laminas\InputFilter\InputFilterProviderInterface;

   class UserFieldset extends Fieldset implements InputFilterProviderInterface
   {
       public function __construct(ObjectManager $objectManager)
       {
           parent::__construct('user');

           $this->setHydrator(new DoctrineHydrator($objectManager))
                ->setObject(new User());

           $this->add([
               'type'    => Text::class,
               'name'    => 'name',
               'options' => [
                   'label' => 'Your name',
               ],
               'attributes' => [
                   'required' => 'required',
               ],
           ]);

           $cityFieldset = new CityFieldset($objectManager);
           $cityFieldset->setLabel('Your city');
           $cityFieldset->setName('city');
           $this->add($cityFieldset);
       }

       public function getInputFilterSpecification()
       {
           return [
               'name' => [
                   'required' => true,
               ],
           ];
       }
   }

And then the City fieldset :

.. code:: php

   namespace Application\Form;

   use Application\Entity\City;
   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
   use Doctrine\Persistence\ObjectManager;
   use Laminas\Form\Element\Text;
   use Laminas\Form\Fieldset;
   use Laminas\InputFilter\InputFilterProviderInterface;

   class CityFieldset extends Fieldset implements InputFilterProviderInterface
   {
       public function __construct(ObjectManager $objectManager)
       {
           parent::__construct('city');

           $this->setHydrator(new DoctrineHydrator($objectManager))
                ->setObject(new City());

           $this->add([
               'type'    => Text::class,
               'name'    => 'name',
               'options' => [
                   'label' => 'Name of your city',
               ],
               'attributes' => [
                   'required' => 'required',
               ],
           ]);

           $this->add([
               'type'    => Text::clas,
               'name'    => 'postCode',
               'options' => [
                   'label' => 'Postcode of your city',
               ],
               'attributes' => [
                   'required' => 'required',
               ],
           ]);
       }

       public function getInputFilterSpecification()
       {
           return [
               'name' => [
                   'required' => true,
               ],
               'postCode' => [
                   'required' => true,
               ],
           ];
       }
   }

Now, let’s say that we have one form where a logged user can only change
his name. This specific form does not allow the user to change this
city, and the fields of the city are not even rendered in the form.
Naively, this form would be like this :

.. code:: php

   namespace Application\Form;

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
   use Doctrine\Persistence\ObjectManager;
   use Laminas\Form\Form;

   class EditNameForm extends Form
   {
       public function __construct(ObjectManager $objectManager)
       {
           parent::__construct('edit-name-form');

           $this->setHydrator(new DoctrineHydrator($objectManager));

           // Add the user fieldset, and set it as the base fieldset
           $userFieldset = new UserFieldset($objectManager);
           $userFieldset->setName('user');
           $userFieldset->setUseAsBaseFieldset(true);
           $this->add($userFieldset);

           // … add CSRF and submit elements …

           // Set the validation group so that we don't care about city
           $this->setValidationGroup([
               'csrf', // assume we added a CSRF element
               'user' => [
                   'name',
               ],
           ]);
       }
   }

..

   Once again, if you are not familiar with the concepts here, please
   read the `official documentation about
   that <https://docs.laminas.dev/laminas-form/collections/>`__.

Here, we create a simple form called “EditSimpleForm”. Because we set
the validation group, all the inputs related to city (postCode and name
of the city) won’t be validated, which is exactly what we want. The
action will look something like this :

.. code:: php

   public function editNameAction()
   {
       // Create the form and inject the Entity Manager
       $form = new EditNameForm($this->entityManager);

       // Get the logged user (for more informations about userIdentity(), please read the Authentication doc)
       $loggedUser = $this->userIdentity();

       // We bind the logged user to the form, so that the name is pre-filled with previous data
       $form->bind($loggedUser);

       $request = $this->request;
       if ($request->isPost()) {
           // Set data from post
           $form->setData($request->getPost());

           if ($form->isValid()) {
               // You can now safely save $loggedUser
           }
       }
   }

This looks good, doesn’t it? However, if we check the queries that are
made (for instance using the awesome
`Laminas:raw-latex:`\DeveloperTools `module <https://github.com/laminas/laminas-developer-tools>`__,
we will see that a request is made to fetch data for the City
relationship of the user, and we hence have a completely useless
database call, as this information is not rendered by the form.

You could ask, “why?” Yes, we set the validation group, BUT the problem
happens during the extracting phase. Here is how it works : when an
object is bound to the form, this latter iterates through all its
fields, and tries to extract the data from the object that is bound. In
our example, here is how it works:

1. It first arrives to the UserFieldset. The input are “name” (which is
   string field), and a “city” which is another fieldset (in our User
   entity, this is a OneToOne relationship to another entity). The
   hydrator will extract both the name and the city (which will be a
   Doctrine 2 Proxy object).
2. Because the UserFieldset contains a reference to another Fieldset (in
   our case, a CityFieldset), it will, in turn, tries to extract the
   values of the City to populate the values of the CityFieldset. And
   here is the problem : City is a Proxy, and hence because the hydrator
   tries to extract its values (the name and postcode field), Doctrine
   will automatically fetch the object from the database in order to
   please the hydrator.

This is absolutely normal, this is how ZF forms work and what make them
nearly magic, but in this specific case, it can leads to disastrous
consequences. When you have very complex entities with a lot of
OneToMany collections, imagine how many unnecessary calls can be made
(actually, after discovering this problem, I’ve realized that my
applications was doing 10 unnecessary database calls).

In fact, the fix is ultra simple : if you don’t need specific fieldsets
in a form, remove them. Here is the fix EditUserForm :

.. code:: php

   namespace Application\Form;

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
   use Doctrine\Persistence\ObjectManager;
   use Laminas\Form\Form;

   class EditNameForm extends Form
   {
       public function __construct(ObjectManager $objectManager)
       {
           parent::__construct('edit-name-form');

           $this->setHydrator(new DoctrineHydrator($objectManager));

           // Add the user fieldset, and set it as the base fieldset
           $userFieldset = new UserFieldset($objectManager);
           $userFieldset->setName('user');
           $userFieldset->setUseAsBaseFieldset(true);

           // We don't want City relationship, so remove it!!
           $userFieldset->remove('city');

           $this->add($userFieldset);

           // … add CSRF and submit elements …

           // We don't even need the validation group as the City fieldset does not
           // exist anymore
       }
   }

And boom! Because the UserFieldset does not contain the CityFieldset
relation anymore it won’t be extracted.

As a rule of thumb, try to remove any unnecessary fieldset relationship,
and always look at which database calls are made.
