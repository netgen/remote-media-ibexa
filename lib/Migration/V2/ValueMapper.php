<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Migration\V2;

use Netgen\RemoteMedia\API\Values\CropSettings;

use function array_filter;
use function implode;
use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function trim;

/**
 * Maps Netgen Remote Media 2 data to its Netgen Remote Media 3 counterparts.
 */
final class ValueMapper
{
    /**
     * Netgen Remote Media 3 remote ID, e.g. "upload|image|folder/name".
     */
    public function toRemoteId(string $type, string $resourceType, string $resourceId): string
    {
        return implode('|', [$type, $resourceType, $resourceId]);
    }

    /**
     * Remote ID of a Netgen Remote Media 2 field value (JSON in data_text), null for an empty value.
     *
     * @param array<string, mixed> $value
     */
    public function toRemoteIdFromFieldValue(array $value): ?string
    {
        $resourceId = $value['resourceId'] ?? null;

        if (!is_string($resourceId) || $resourceId === '') {
            return null;
        }

        return $this->toRemoteId(
            $this->toString($value['type'] ?? null) ?? 'upload',
            $this->toString($value['resourceType'] ?? null) ?? 'image',
            $resourceId,
        );
    }

    /**
     * Converts a Netgen Remote Media 2 field value to Cloudinary API resource data, as accepted by the
     * Netgen Remote Media 3 Cloudinary remote resource factory.
     *
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    public function toCloudinaryData(array $value): array
    {
        $metaData = is_array($value['metaData'] ?? null) ? $value['metaData'] : [];

        $data = [
            'public_id' => $value['resourceId'] ?? null,
            'resource_type' => $this->toString($value['resourceType'] ?? null) ?? 'image',
            'type' => $this->toString($value['type'] ?? null) ?? 'upload',
            'url' => $this->toString($value['url'] ?? null),
            'secure_url' => $this->toString($value['secure_url'] ?? null),
            'bytes' => (int) ($value['size'] ?? 0),
            'version' => $metaData['version'] ?? null,
            'format' => $this->toString($metaData['format'] ?? null),
            'width' => $metaData['width'] ?? null,
            'height' => $metaData['height'] ?? null,
            'created_at' => $this->toString($metaData['created'] ?? null),
            'signature' => $this->toString($metaData['signature'] ?? null),
            'overwritten' => $metaData['overwritten'] ?? null,
            'etag' => $this->toString($metaData['etag'] ?? null),
            'tags' => is_array($metaData['tags'] ?? null) ? $metaData['tags'] : [],
            'context' => [
                'custom' => array_filter(
                    [
                        'alt_text' => $this->toString($metaData['alt_text'] ?? null),
                        'caption' => $this->toString($metaData['caption'] ?? null),
                    ],
                    static fn (?string $item): bool => $item !== null,
                ),
            ],
        ];

        return array_filter($data, static fn (mixed $item): bool => $item !== null && $item !== '');
    }

    /**
     * Location override (alt text or caption) for the value of a Netgen Remote Media 2 usage, a field value or
     * a RichText tag: the value when it differs from the value of the resource on Cloudinary, otherwise null, so
     * the location uses the value of the resource.
     */
    public function toOverride(?string $value, ?string $resourceValue): ?string
    {
        if ($value === null || trim($value) === '' || $value === $resourceValue) {
            return null;
        }

        return $value;
    }

    /**
     * Converts Netgen Remote Media 2 crops ("variations" in field values, "coords" in RichText),
     * a map of variation names to {x, y, w, h}. Variations without complete coordinates are left out.
     *
     * @return \Netgen\RemoteMedia\API\Values\CropSettings[]
     */
    public function toCropSettings(mixed $variations): array
    {
        if (!is_array($variations)) {
            return [];
        }

        $cropSettings = [];

        foreach ($variations as $variationName => $coords) {
            if (!is_array($coords)) {
                continue;
            }

            foreach (['x', 'y', 'w', 'h'] as $key) {
                if (!is_numeric($coords[$key] ?? null)) {
                    continue 2;
                }
            }

            $cropSettings[] = new CropSettings(
                (string) $variationName,
                (int) $coords['x'],
                (int) $coords['y'],
                (int) $coords['w'],
                (int) $coords['h'],
            );
        }

        return $cropSettings;
    }

    private function toString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }
}
