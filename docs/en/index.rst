Introduction
============

Hydrators insert an array of data into object properties (this is called
"hydrating") and convert object properties back to an array (this is called
"extracting"). Hydrators are often used in the context of Forms, with
the binding functionality of Laminas, but can also be used in any
hydrating/extracting context (for instance, it can be used in RESTful
context). For an introduction to hydrators, please read
`Laminas hydrator
documentation <https://docs.laminas.dev/laminas-hydrator/>`__.

Installation
------------

Run the following to install this library:

.. code:: bash

   $ composer require doctrine/doctrine-laminas-hydrator

Next Steps
----------

.. toctree::
    :caption: Table of Contents

-  :doc:`Basic Usage <basic-usage>`:
   introduces the hydrator, hydrating by value or by reference, strategies,
   filters and naming strategies.
-  :doc:`Working with Associations <associations>`:
   explains how associated entities and collections are hydrated and extracted.
-  :doc:`Working with Embeddables <embeddables>`:
   explains how embeddables are hydrated and extracted.
-  :doc:`Converting Custom DBAL Types <type-conversion>`:
   explains how values are converted based on the Doctrine mapping, including
   enums and custom DBAL types.
-  :doc:`Example Usage with Laminas Form <laminas-form>`:
   shows a complete example with the Laminas form library.
-  :doc:`Example Usage in an API Context <api-usage>`:
   shows a complete example of an API exchanging entities as JSON.
-  :doc:`Performance Considerations <performance-considerations>`:
   some remarks to consider for keeping your application performant.
-  :doc:`Upgrading to v4 <upgrading>`:
   lists the changes when upgrading from version 3, and how to adapt to them.
