<?php

declare(strict_types=1);

namespace DoctrineTest\Laminas\Hydrator\Filter;

use Doctrine\Laminas\Hydrator\Filter\PropertyName;
use PHPUnit\Framework\TestCase;

class PropertyNameTest extends TestCase
{
    public function testAllowListProperties(): void
    {
        $filter = new PropertyName([
            'property1',
            'property2',
        ], false);

        $this->assertTrue($filter->filter('property1'));
        $this->assertTrue($filter->filter('property2'));
        $this->assertFalse($filter->filter('somethingElse'));
    }

    public function testDenyListProperties(): void
    {
        $filter = new PropertyName([
            'property1',
            'property2',
        ], true);

        $this->assertFalse($filter->filter('property1'));
        $this->assertFalse($filter->filter('property2'));
        $this->assertTrue($filter->filter('somethingElse'));
    }

    public function testDefaultIsDenyList(): void
    {
        $filter = new PropertyName([
            'property1',
            'property2',
        ]);

        $this->assertFalse($filter->filter('property1'));
        $this->assertFalse($filter->filter('property2'));
        $this->assertTrue($filter->filter('somethingElse'));
    }
}
