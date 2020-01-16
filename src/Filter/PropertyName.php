<?php

declare(strict_types=1);

namespace Doctrine\Laminas\Hydrator\Filter;

use Laminas\Hydrator\Filter\FilterInterface;
use function in_array;
use function is_array;

/**
 * Provides a filter to restrict returned fields by whitelisting or
 * blacklisting property names.
 */
class PropertyName implements FilterInterface
{
    /**
     * The properties to exclude.
     *
     * @var string[]
     */
    protected array $properties = [];

    /**
     * Either an exclude or an include.
     */
    protected bool $exclude;

    /**
     * @param string|string[] $properties The properties to exclude or include.
     * @param bool            $exclude    If the method should be excluded
     */
    public function __construct($properties, bool $exclude = true)
    {
        $this->exclude    = $exclude;
        $this->properties = is_array($properties)
            ? $properties
            : [$properties];
    }

    public function filter(string $property) : bool
    {
        return in_array($property, $this->properties, true)
            ? ! $this->exclude
            : $this->exclude;
    }
}
