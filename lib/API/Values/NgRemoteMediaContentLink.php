<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\API\Values;

use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;

class NgRemoteMediaContentLink
{
    public function __construct(
        public readonly int $fieldId,
        public readonly int $version,
        public RemoteResourceLocation $remoteResourceLocation,
        public ?int $id = null,
    ) {
    }
}
