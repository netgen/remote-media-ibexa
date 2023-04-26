<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\FieldType;

use Ibexa\Contracts\Core\FieldType\FieldStorage as FieldStorageInterface;
use Ibexa\Contracts\Core\Persistence\Content\Field;
use Ibexa\Contracts\Core\Persistence\Content\VersionInfo;
use Netgen\RemoteMediaIbexa\FieldType\FieldStorage\Gateway;

use function array_keys;
use function count;
use function is_array;

final class FieldStorage implements FieldStorageInterface
{
    public function __construct(
        private readonly Gateway $gateway,
    ) {
    }

    public function storeFieldData(VersionInfo $versionInfo, Field $field, array $context): ?bool
    {
        $hash = $field->value->externalData;

        if (!is_array($hash) || count(array_keys($hash)) === 0) {
            $this->deleteFieldData($versionInfo, [$field->id], $context);

            return null;
        }

        return $this->gateway->storeFieldData($versionInfo, $field);
    }

    public function getFieldData(VersionInfo $versionInfo, Field $field, array $context): void
    {
        $this->gateway->getFieldData($versionInfo, $field);
    }

    public function deleteFieldData(VersionInfo $versionInfo, array $fieldIds, array $context): bool
    {
        $this->gateway->deleteFieldData($versionInfo, $fieldIds);

        return true;
    }

    public function hasFieldData(): bool
    {
        return true;
    }

    /**
     * @return \Ibexa\Contracts\Core\Search\Field[]
     */
    public function getIndexData(VersionInfo $versionInfo, Field $field, array $context): array
    {
        return [];
    }

    public function copyLegacyField(VersionInfo $versionInfo, Field $field, Field $originalField, array $context): ?bool
    {
        if ($originalField->value->externalData === null) {
            return false;
        }

        $field->value->externalData = $originalField->value->externalData;

        return $this->storeFieldData($versionInfo, $field, $context);
    }
}
