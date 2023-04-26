<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\FieldType\FieldStorage;

use Ibexa\Contracts\Core\FieldType\StorageGateway;
use Ibexa\Contracts\Core\Persistence\Content\Field;
use Ibexa\Contracts\Core\Persistence\Content\VersionInfo;

abstract class Gateway extends StorageGateway
{
    abstract public function storeFieldData(VersionInfo $versionInfo, Field $field): ?bool;

    abstract public function getFieldData(VersionInfo $versionInfo, Field $field): void;

    /**
     * @param int[] $fieldIds
     */
    abstract public function deleteFieldData(VersionInfo $versionInfo, array $fieldIds): void;
}
