Collections Strategy
====================

By default, every collection association has a strategy
attached to it that is called during the hydrating and extracting phase.
Strategies for collection associations must implement
``Doctrine\Laminas\Hydrator\Strategy\CollectionStrategyInterface``,
otherwise an ``InvalidArgumentException`` is thrown. All strategies
provided by this library extend from the class
``Doctrine\Laminas\Hydrator\Strategy\AbstractCollectionStrategy``.

The library provides four strategies out of the box:

1. ``Doctrine\Laminas\Hydrator\Strategy\AllowRemoveByValue``: this is
   the default strategy; it removes old elements that are not in the new
   collection.
2. ``Doctrine\Laminas\Hydrator\Strategy\AllowRemoveByReference``: this
   is the default strategy *if set to byReference*; it removes old
   elements that are not in the new collection.
3. ``Doctrine\Laminas\Hydrator\Strategy\DisallowRemoveByValue``: this
   strategy does not remove old elements even if they are not in the new
   collection.
4. ``Doctrine\Laminas\Hydrator\Strategy\DisallowRemoveByReference``:
   this strategy does not remove old elements even if they are not in
   the new collection.

As a consequence, when using ``AllowRemove*``, you need to define both
adder (eg. addTags) and remover (eg. removeTags). On the other hand,
when using the ``DisallowRemove*`` strategy, you must always define at
least the adder, but the remover is optional (because elements are never
removed).

The following table illustrates the difference between the two
strategies:

================ ================== ==================== =======
Strategy         Initial collection Submitted collection Result
================ ================== ==================== =======
AllowRemove\*    A, B               B, C                 B, C
DisallowRemove\* A, B               B, C                 A, B, C
================ ================== ==================== =======

The difference between ByValue and ByReference is that, when using
strategies that end with ByReference, it won’t use the public API of
your entity (adder and remover) - you don’t even need to define them -
it will add and remove elements directly from the collection.

Changing the Strategy
---------------------

Changing the strategy for collections is simple:

.. code:: php

   use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;
   use Doctrine\Laminas\Hydrator\Strategy;

   $hydrator = new DoctrineHydrator($entityManager);
   $hydrator->addStrategy('tags', new Strategy\DisallowRemoveByValue());

To change the strategy for all collections of a hydrator, including the
collections of associated entities, set the default strategy instead:

.. code:: php

   $hydrator->setDefaultByValueStrategy(Strategy\DisallowRemoveByValue::class);
   $hydrator->setDefaultByReferenceStrategy(Strategy\DisallowRemoveByReference::class);

Strategies added with ``addStrategy()`` only apply to the entity passed to
the hydrator. For collections of associated entities, add them to the
hydrator of the associated class, see
:ref:`Configuring Associated Entities <association-hydrators>`.

Note that you can also add strategies to non-collection fields, as shown
in :doc:`Type Conversion <type-conversion>`.

Custom Strategies
-----------------

A custom strategy for collections can extend ``AbstractCollectionStrategy``
and implement the ``hydrate()`` method:

.. code:: php

   use Doctrine\Laminas\Hydrator\Strategy\AbstractCollectionStrategy;

   final class MyCollectionStrategy extends AbstractCollectionStrategy
   {
       public function hydrate(mixed $value, ?array $data = null): mixed
       {
           $collection = $this->getCollectionFromObjectByValue();

           // … add and remove elements of $collection based on $value …

           return $collection;
       }
   }
