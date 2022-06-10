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

    basic-usage
    collections-strategy
    by-value-by-reference
    laminas-form
    enum-strategy
    performance-considerations
