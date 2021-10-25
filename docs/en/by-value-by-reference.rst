By Value and by Reference
=========================

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

To illustrate the difference between the two, consider an extraction
with the given entity:

.. code:: php

   namespace Application\Entity;

   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Entity]
   class SimpleEntity
   {
       #[ORM\Column(type: 'string')]
       private ?string $foo = null;

       public function getFoo(): void
       {
           die();
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

   echo $data['foo']; // never executed, because the script was killed when getter was accessed

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
