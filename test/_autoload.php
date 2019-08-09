<?php

namespace DoctrineTest\Zend\Hydrator;

use Zend\Hydrator\HydratorPluginManagerInterface;

if (interface_exists(HydratorPluginManagerInterface::class)) {
    class_alias(Assets\SimpleStrategyV3::class, Assets\SimpleStrategy::class);
} else {
    class_alias(Assets\SimpleStrategyV2::class, Assets\SimpleStrategy::class);
}
