<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\RichText;

use Ibexa\Contracts\Core\Persistence\Content\VersionInfo;
use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\Exception\RemoteResourceLocationNotFoundException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

use function array_chunk;
use function array_diff;
use function array_keys;

/**
 * Deletes remote resource locations of deleted RichText fields which are not referenced anymore.
 *
 * Every version, translation and copy of the content can reference the same locations, so the locations of the
 * deleted fields are only collected while the fields are deleted. They are deleted later by `cleanUp()`, once the
 * fields are gone, only when no other field references them.
 */
final class LocationCleaner
{
    private const BATCH_SIZE = 100;

    /**
     * @var array<int, true>
     */
    private array $locationIds = [];

    public function __construct(
        private readonly LocationGateway $gateway,
        private readonly ProviderInterface $provider,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Collects the locations of the RichText fields with the given IDs in the given version, which are about to
     * be deleted.
     *
     * @param int[] $fieldIds
     */
    public function collect(VersionInfo $versionInfo, array $fieldIds): void
    {
        foreach ($this->gateway->loadLocationIds($fieldIds, $versionInfo->versionNo) as $locationId) {
            $this->locationIds[$locationId] = true;
        }
    }

    /**
     * Deletes the collected locations which are not referenced anymore.
     */
    public function cleanUp(): void
    {
        $locationIds = array_keys($this->locationIds);
        $this->locationIds = [];

        foreach (array_chunk($locationIds, self::BATCH_SIZE) as $batch) {
            foreach (array_diff($batch, $this->gateway->loadReferencedLocationIds($batch)) as $locationId) {
                $this->removeLocation($locationId);
            }
        }
    }

    private function removeLocation(int $locationId): void
    {
        try {
            $this->provider->removeLocation($this->provider->loadLocation($locationId));
        } catch (RemoteResourceLocationNotFoundException) {
            // Already deleted
        } catch (Throwable $exception) {
            $this->logger->error(
                'Netgen Remote Media: failed to delete the remote resource location #{locationId} of a deleted RichText field: {message}',
                ['locationId' => $locationId, 'message' => $exception->getMessage(), 'exception' => $exception],
            );
        }
    }
}
