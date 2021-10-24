Collections Strategy
====================

By default, every collection association has a strategy
attached to it that is called during the hydrating and extracting phase.
All those strategies extend from the class
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
strategies

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

Note that you can also add strategies to non-collection fields.
