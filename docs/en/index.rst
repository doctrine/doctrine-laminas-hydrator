Usage of doctrine-laminas-hydrator
==================================

Hydrators convert an array of data to an object (this is called
“hydrating”) and convert an object back to an array (this is called
“extracting”). Hydrators are mainly used in the context of Forms, with
the binding functionality of Laminas, but can also be used in any
hydrating/extracting context (for instance, it can be used in RESTful
context). If you are not really comfortable with hydrators, please first
read `Laminas hydrator’s
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

    basic-usage
    collections-strategy
    by-value-by-reference
    laminas-form
    performance-considerations
