<?php

namespace Doctrine\Zend\Hydrator;

use Zend\Hydrator\HydratorPluginManagerInterface;

if (interface_exists(HydratorPluginManagerInterface::class)) {
    class_alias(DoctrineObjectV3::class, DoctrineObject::class);
    class_alias(Filter\PropertyNameV3::class, Filter\PropertyName::class);
    class_alias(Strategy\AbstractCollectionStrategyV3::class, Strategy\AbstractCollectionStrategy::class);
    class_alias(Strategy\AllowRemoveByReferenceV3::class, Strategy\AllowRemoveByReference::class);
    class_alias(Strategy\AllowRemoveByValueV3::class, Strategy\AllowRemoveByValue::class);
    class_alias(Strategy\DisallowRemoveByReferenceV3::class, Strategy\DisallowRemoveByReference::class);
    class_alias(Strategy\DisallowRemoveByValueV3::class, Strategy\DisallowRemoveByValue::class);
} else {
    class_alias(DoctrineObjectV2::class, DoctrineObject::class);
    class_alias(Filter\PropertyNameV2::class, Filter\PropertyName::class);
    class_alias(Strategy\AbstractCollectionStrategyV2::class, Strategy\AbstractCollectionStrategy::class);
    class_alias(Strategy\AllowRemoveByReferenceV2::class, Strategy\AllowRemoveByReference::class);
    class_alias(Strategy\AllowRemoveByValueV2::class, Strategy\AllowRemoveByValue::class);
    class_alias(Strategy\DisallowRemoveByReferenceV2::class, Strategy\DisallowRemoveByReference::class);
    class_alias(Strategy\DisallowRemoveByValueV2::class, Strategy\DisallowRemoveByValue::class);
}
