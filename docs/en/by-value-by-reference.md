# By value and by reference

By default, Doctrine Hydrator works by value. This means that the hydrator will access and modify your properties
through the public API of your entities (that is to say, with getters and setters). However, you can override this
behaviour to work by reference (that is to say that the hydrator will access the properties through Reflection API,
and hence bypass any logic you may include in your setters/getters).

To change the behaviour, just give the second parameter of the constructor to false:

```php
use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

$hydrator = new DoctrineHydrator($objectManager, false);
```

To illustrate the difference between, the two, let's do an extraction with the given entity:

```php
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
```

Let's now use the hydrator using the default method, by value:

```php
use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

$hydrator = new DoctrineHydrator($objectManager);
$object   = new SimpleEntity();
$object->setFoo('bar');

$data = $hydrator->extract($object);

echo $data['foo']; // never executed, because the script was killed when getter was accessed
```

As we can see here, the hydrator used the public API (here getFoo) to retrieve the value.

However, if we use it by reference:

```php
use Doctrine\Laminas\Hydrator\DoctrineObject as DoctrineHydrator;

$hydrator = new DoctrineHydrator($objectManager, false);
$object   = new SimpleEntity();
$object->setFoo('bar');

$data = $hydrator->extract($object);

echo $data['foo']; // prints 'bar'
```

It now only prints "bar", which shows clearly that the getter has not been called.
