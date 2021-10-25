Performance Considerations
==========================

Using this hydrator abstracts most of the tedious tasks, but be
aware that it can lead to performance issues in some situations.

Unwanted Side-Effects
---------------------

Be careful when using Doctrine hydrator with
complex entities that contain a lot of associations because a lot of
unnecessary calls to the database may be made if you are not aware
of what is happening under the hood. To explain this problem,
take the following entity:

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
relationship.  When using Laminas forms the correct way, There may be
a fieldset for every entity; so a 1:1 mapping
between entities and fieldsets.  Here are fieldsets for User and
and City entities:

.. note::

   More information on Laminas fieldsets may be found in
   `the Laminas
   documentation <https://docs.laminas.dev/laminas-form/collections/>`__.

First, the User fieldset:

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

And, now, the City fieldset:

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

For a form where an authenticated user may only change their name,
and it does not allow the user to change the city,
and the fields of the city are not rendered in the form,
the form would look like this:

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

.. note::

   For more information on Laminas Form and collections, please
   read the `Laminas documentation
   <https://docs.laminas.dev/laminas-form/collections/>`__.

Next, we create a simple form called ``EditSimpleForm``. Because we set
the validation group, all the inputs related to city (postCode and name
of the city) won’t be validated, which is exactly what we want. The
action will look something like this:

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

This looks good, yes? But, a check for the queries that are
made (for instance using the
`Laminas\\DeveloperTools module <https://github.com/laminas/laminas-developer-tools>`__),
shows that a request is made to fetch data for the City
relationship of the user, and we have an unneeded
database call because this information is not rendered by the form.

*Why?* We set the validation group, *but* the problem
happens during the extracting phase. When an
object is bound to the form, the form iterates through all its
fields and tries to extract the data from the object that is bound.
From this example:

1. It first arrives at the UserFieldset. The inputs are "name" (which is
   a string field), and "city" which is another fieldset (in our User
   entity, this is a OneToOne relationship to another entity). The
   hydrator will extract both the name and the city.  The city will be a
   Doctrine 2 Proxy object.
2. Because the UserFieldset contains a reference to another Fieldset (in
   this case, a CityFieldset), it will, in turn, try to extract the
   values of the City to populate the values of the CityFieldset. And
   here is the problem: City is a Proxy, and hence because the hydrator
   tries to extract its values (the name and postcode field), Doctrine
   will automatically fetch the object from the database in order to
   please the hydrator.

This is absolutely normal; this is how Laminas forms work and what make them
so useful, but in this specific case it can leads to disastrous
consequences. When you have complex entities with many
OneToMany collections, imagine how many unnecessary calls may be made
(e.g. after discovering this problem, the author realized that their
application was doing 10 unnecessary database calls).

The fix is simple: if you don’t need specific fieldsets
in a form, remove them. Here is a fixed EditUserForm:

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

Because the UserFieldset no longer contains the CityFieldset
relation, it won’t be extracted.

As a rule of thumb, try to remove any unnecessary fieldset relationships,
and always look at which database calls are made.
