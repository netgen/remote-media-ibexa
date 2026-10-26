<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Migration\V2;

use Doctrine\ORM\EntityManagerInterface;
use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\API\Search\Query;
use Netgen\RemoteMedia\API\Values\RemoteResource;

use function array_chunk;
use function array_diff;
use function array_keys;
use function array_unique;
use function array_values;
use function count;
use function mb_strlen;
use function sprintf;

/**
 * Remote resources stored in the database during the migration, by remote ID.
 *
 * Works with the entity manager in batches: resources added since the last commit() are entities managed by
 * the entity manager, stored ones are entity references, so the entity manager can be cleared after commit().
 */
final class ResourceRegistry
{
    private const REMOTE_BATCH_SIZE = 100;

    /**
     * Length of the remote_id, url, name and original_filename columns of ngrm_remote_resource.
     */
    private const MAX_LENGTH = 255;

    /** @var array<string, int> */
    private array $ids = [];

    /** @var array<int, string> non-empty alt texts of stored resources */
    private array $altTexts = [];

    /** @var array<int, string> non-empty captions of stored resources */
    private array $captions = [];

    /** @var array<string, \Netgen\RemoteMedia\API\Values\RemoteResource> */
    private array $added = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProviderInterface $provider,
    ) {}

    public function load(): void
    {
        $this->ids = [];
        $this->altTexts = [];
        $this->captions = [];
        $this->added = [];

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT id, remote_id, alt_text, caption FROM ngrm_remote_resource',
        );

        foreach ($rows as $row) {
            $this->remember((string) $row['remote_id'], (int) $row['id'], $row['alt_text'] ?? null, $row['caption'] ?? null);
        }
    }

    public function get(string $remoteId): ?RemoteResource
    {
        if (isset($this->added[$remoteId])) {
            return $this->added[$remoteId];
        }

        if (!isset($this->ids[$remoteId])) {
            return null;
        }

        return $this->entityManager->getReference(RemoteResource::class, $this->ids[$remoteId]);
    }

    /**
     * Alt text of a stored or added resource, null when it has none.
     */
    public function getAltText(string $remoteId): ?string
    {
        if (isset($this->added[$remoteId])) {
            return $this->toNonEmpty($this->added[$remoteId]->getAltText());
        }

        return $this->altTexts[$this->ids[$remoteId] ?? 0] ?? null;
    }

    /**
     * Caption of a stored or added resource, null when it has none.
     */
    public function getCaption(string $remoteId): ?string
    {
        if (isset($this->added[$remoteId])) {
            return $this->toNonEmpty($this->added[$remoteId]->getCaption());
        }

        return $this->captions[$this->ids[$remoteId] ?? 0] ?? null;
    }

    /**
     * @throws \Netgen\RemoteMediaIbexa\Migration\V2\ResourceNotStorableException
     */
    public function add(RemoteResource $resource): RemoteResource
    {
        $values = [
            'remote ID' => $resource->getRemoteId(),
            'URL' => $resource->getUrl(),
            'name' => (string) $resource->getName(),
            'original filename' => (string) $resource->getOriginalFilename(),
        ];

        foreach ($values as $label => $value) {
            if (mb_strlen($value) > self::MAX_LENGTH) {
                throw new ResourceNotStorableException(
                    sprintf(
                        'Resource "%s" can\'t be stored: its %s is longer than %d characters (%s)',
                        $resource->getRemoteId(),
                        $label,
                        self::MAX_LENGTH,
                        $value,
                    ),
                );
            }
        }

        $this->entityManager->persist($resource);
        $this->added[$resource->getRemoteId()] = $resource;

        return $resource;
    }

    /**
     * To call after the entity manager is flushed: from here on, added resources are used through references.
     */
    public function commit(): void
    {
        foreach ($this->added as $remoteId => $resource) {
            $this->remember($remoteId, (int) $resource->getId(), $resource->getAltText(), $resource->getCaption());
        }

        $this->added = [];
    }

    /**
     * Loads resources which aren't stored yet from the remote (in batches, to keep the number of API requests low)
     * and stores them.
     *
     * @param string[] $remoteIds
     *
     * @return array<string, string> reasons, by remote ID, for resources which couldn't be stored
     */
    public function storeFromRemote(array $remoteIds): array
    {
        $missing = array_values(
            array_diff(array_unique($remoteIds), array_keys($this->ids), array_keys($this->added)),
        );

        if (count($missing) === 0) {
            return [];
        }

        $failed = [];

        foreach (array_chunk($missing, self::REMOTE_BATCH_SIZE) as $chunk) {
            $result = $this->provider->search(Query::fromRemoteIds($chunk, count($chunk)));

            foreach ($result->getResources() as $resource) {
                if ($this->get($resource->getRemoteId()) !== null) {
                    continue;
                }

                try {
                    $this->add($resource);
                } catch (ResourceNotStorableException $e) {
                    $failed[$resource->getRemoteId()] = $e->getMessage();
                }
            }
        }

        $this->entityManager->flush();
        $this->commit();

        foreach (array_diff($missing, array_keys($this->ids), array_keys($failed)) as $remoteId) {
            $failed[$remoteId] = sprintf('Resource "%s" doesn\'t exist on the remote', $remoteId);
        }

        return $failed;
    }

    private function remember(string $remoteId, int $id, ?string $altText, ?string $caption): void
    {
        $this->ids[$remoteId] = $id;

        $altText = $this->toNonEmpty($altText);
        $caption = $this->toNonEmpty($caption);

        if ($altText !== null) {
            $this->altTexts[$id] = $altText;
        }

        if ($caption !== null) {
            $this->captions[$id] = $caption;
        }
    }

    private function toNonEmpty(?string $value): ?string
    {
        return ($value ?? '') === '' ? null : $value;
    }
}
