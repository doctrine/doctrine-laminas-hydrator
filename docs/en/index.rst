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
   introduces the basic usage of Doctrine Laminas Hydrator.
-  :doc:`Collections Strategy <collections-strategy>`:
   explains using strategies for hydrating or extracting collections.
-  :doc:`enum-strategy <enum-strategy>`:
   this sections shows an example how PHP enums can be handled using strategies.
-  :doc:`By Value or By Reference <by-value-by-reference>`:
   shows the differences of by-value and by-reference extraction or hydration of data.
-  :doc:`Laminas Form <laminas-form>`:
   this section shows usage examples with the Laminas form library.
-  :doc:`Performance Considerations <performance-considerations>`:
   some remarks to consider for keeping your application performant.
