<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Converter;

use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\API\Values\RemoteResource;
use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;
use Netgen\RemoteMedia\Exception\RemoteResourceLocationNotFoundException;
use Netgen\RemoteMedia\Exception\RemoteResourceNotFoundException;

use function is_string;
use function serialize;
use function unserialize;

class Hash
{
    public function __construct(
        private readonly ProviderInterface $remoteMediaProvider,
    ) {}

    /**
     * @return array<string,string|null>
     */
    public function remoteResourceLocationToHash(RemoteResourceLocation $remoteResourceLocation): array
    {
        return [
            'remote_id' => $remoteResourceLocation->getRemoteResource()->getRemoteId(),
            'source' => $remoteResourceLocation->getSource(),
            'crop_settings' => serialize($remoteResourceLocation->getCropSettings()),
            'watermark_text' => $remoteResourceLocation->getWatermarkText(),
        ];
    }

    /**
     * @param array<string,string|null> $hash
     */
    public function remoteResourceLocationFromHash(array $hash): ?RemoteResourceLocation
    {
        $remoteResourceLocationId = (int) ($hash['remote_resource_location_id'] ?? null);

        if ($remoteResourceLocationId > 0) {
            unset($hash['remote_resource_location_id']);

            try {
                return $this->remoteMediaProvider->loadLocation($remoteResourceLocationId);
            } catch (RemoteResourceLocationNotFoundException $e) {
            }
        }

        $remoteResource = $this->resolveRemoteResource($hash);

        if (!$remoteResource instanceof RemoteResource) {
            return null;
        }

        return new RemoteResourceLocation(
            remoteResource: $remoteResource,
            source: $hash['source'] ?? null,
            cropSettings: unserialize($hash['crop_settings'] ?? 'a:0:{}'),
            watermarkText: $hash['watermark_text'] ?? null,
        );
    }

    /**
     * @param array<string,string|null> $hash
     */
    private function resolveRemoteResource(array $hash): ?RemoteResource
    {
        $remoteId = $hash['remote_id'] ?? null;

        if (!is_string($remoteId)) {
            return null;
        }

        try {
            return $this->remoteMediaProvider->loadByRemoteId($remoteId);
        } catch (RemoteResourceNotFoundException $e) {
        }

        try {
            return $this->remoteMediaProvider->loadFromRemote($remoteId);
        } catch (RemoteResourceNotFoundException $e) {
        }

        return null;
    }
}
