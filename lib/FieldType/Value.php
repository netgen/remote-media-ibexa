<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\FieldType;

use Ibexa\Core\FieldType\Value as BaseValue;
use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;

final class Value extends BaseValue
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        public ?RemoteResourceLocation $remoteResourceLocation = null,
        array $properties = [],
    ) {
        parent::__construct($properties);
    }

    public function __toString(): string
    {
        return $this->remoteResourceLocation instanceof RemoteResourceLocation
            ? $this->remoteResourceLocation->getRemoteResource()->getRemoteId()
            : '';
    }

    public function getName(): string
    {
        return $this->remoteResourceLocation instanceof RemoteResourceLocation
            ? $this->remoteResourceLocation->getRemoteResource()->getName()
            : '';
    }
}
