<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\RichText;

use Ibexa\Contracts\Core\FieldType\GatewayBasedStorage;
use Ibexa\Contracts\Core\Persistence\Content\Field;
use Ibexa\Contracts\Core\Persistence\Content\VersionInfo;

/**
 * Decorates the RichText external storage, which is called for every RichText field before the field is deleted
 * (deleted drafts and versions, translations, content and field definitions), to collect the remote resource
 * locations of the deleted fields.
 *
 * Extends GatewayBasedStorage like the decorated storage, as the legacy storage handler copies external data only
 * for gateway based storages. Every call goes to the decorated storage.
 *
 * @extends \Ibexa\Contracts\Core\FieldType\GatewayBasedStorage<\Ibexa\Contracts\Core\FieldType\StorageGatewayInterface>
 */
final class FieldStorage extends GatewayBasedStorage
{
    public function __construct(
        private readonly GatewayBasedStorage $innerStorage,
        private readonly LocationCleaner $locationCleaner,
    ) {
        parent::__construct($innerStorage->gateway);
    }

    public function storeFieldData(VersionInfo $versionInfo, Field $field, array $context): mixed
    {
        return $this->innerStorage->storeFieldData($versionInfo, $field, $context);
    }

    public function getFieldData(VersionInfo $versionInfo, Field $field, array $context): mixed
    {
        return $this->innerStorage->getFieldData($versionInfo, $field, $context);
    }

    public function deleteFieldData(VersionInfo $versionInfo, array $fieldIds, array $context): mixed
    {
        $this->locationCleaner->collect($versionInfo, $fieldIds);

        return $this->innerStorage->deleteFieldData($versionInfo, $fieldIds, $context);
    }

    public function hasFieldData(): mixed
    {
        return $this->innerStorage->hasFieldData();
    }

    public function getIndexData(VersionInfo $versionInfo, Field $field, array $context): mixed
    {
        return $this->innerStorage->getIndexData($versionInfo, $field, $context);
    }

    public function copyLegacyField(VersionInfo $versionInfo, Field $field, Field $originalField, array $context): mixed
    {
        return $this->innerStorage->copyLegacyField($versionInfo, $field, $originalField, $context);
    }
}
