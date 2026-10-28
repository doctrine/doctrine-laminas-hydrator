Using Enums
===========

Doctrine ORM supports `backed enums <https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/basic-mapping.html#mapping-php-enums>`__
through the ``enumType`` option of a column. The hydrator passes enum
instances through unchanged, but does not convert scalar values like
``'H'`` into an enum. To hydrate backed enums from such values, you need
to set up a hydration strategy.

The Enum and Entity
-------------------

The following example uses a card with an enum representing hearts, diamonds, clubs or spades:

.. code:: php

    enum Suit: string {
        case Hearts = 'H';
        case Diamonds = 'D';
        case Clubs = 'C';
        case Spades = 'S';
    }

    #[Entity]
    class Card
    {
        /** ... */

        #[Column(type: 'string', enumType: Suit::class)]
        public Suit $suit;

        public function getSuit(): Suit
        {
            return $this->suit;
        }

        public function setSuit(Suit $suit): void
        {
            $this->suit = $suit;
        }
    }

Custom Hydration Strategy
-------------------------

Next, you need to create a hydration strategy for your backed enum:

.. code:: php

    namespace Application\Hydrator\Strategy;

    use Laminas\Hydrator\Strategy\StrategyInterface;

    class SuitHydratorStrategy implements StrategyInterface
    {
        public function extract(mixed $value, ?object $object = null): ?string
        {
            return $value instanceof Suit ? $value->value : $value;
        }

        public function hydrate(mixed $value, ?array $data = null): ?Suit
        {
            if ($value === null || $value instanceof Suit) {
                return $value;
            }

            return Suit::from($value);
        }
    }

Usage Example
-------------

Once you have enum, entity and strategy set up, you can wire it all together by adding the
strategy to the relevant field in your hydrator.

.. code:: php

    $hydrator = new DoctrineHydrator($entityManager);
    $hydrator->addStrategy('suit', new \Application\Hydrator\Strategy\SuitHydratorStrategy());

    $card = new Card();
    $hydrator->hydrate(['suit' => 'H'], $card);

    echo $card->getSuit()->name; // prints "Hearts"

    $data = $hydrator->extract($card);
    echo $data['suit']; // prints "H"
