Using Enums with PHP 8.1
========================

Doctrine ORM 2.11 introduced `support for PHP 8.1 enums <https://www.doctrine-project.org/2022/01/11/orm-2.11.html>`__.
To make use of backed enums with doctrine-laminas-hydrator, you need to set up a hydration strategy.

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
        public function extract($value, ?object $object = null): ?int
        {
            return null === $value ? null : Suit::tryFrom($value)->value;
        }

        public function hydrate($value, ?array $data): ?Suit
        {
            return null === $value ? null : Suit::tryFrom($value);
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
    $hydrator->hydrate([ 'suit' => 'H' ], $card);
