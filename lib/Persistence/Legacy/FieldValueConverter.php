<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Persistence\Legacy;

use Ibexa\Contracts\Core\Persistence\Content\FieldValue;
use Ibexa\Contracts\Core\Persistence\Content\Type\FieldDefinition;
use Ibexa\Core\Persistence\Legacy\Content\FieldValue\Converter;
use Ibexa\Core\Persistence\Legacy\Content\StorageFieldDefinition;
use Ibexa\Core\Persistence\Legacy\Content\StorageFieldValue;

use function array_key_exists;
use function is_array;
use function json_decode;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

class FieldValueConverter implements Converter
{
    public function toStorageValue(FieldValue $value, StorageFieldValue $storageFieldValue): void
    {
        // There is no contained data. All data is external. So we just do
        // nothing here.
    }

    public function toFieldValue(StorageFieldValue $value, FieldValue $fieldValue): void
    {
        // There is no contained data. All data is external. So we just do
        // nothing here.
    }

    public function toStorageFieldDefinition(FieldDefinition $fieldDef, StorageFieldDefinition $storageDef): void
    {
        $storageDef->dataText5 = json_encode($fieldDef->fieldTypeConstraints->fieldSettings, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    public function toFieldDefinition(StorageFieldDefinition $storageDef, FieldDefinition $fieldDef): void
    {
        if ($storageDef->dataText5 === null || $storageDef->dataText5 === '') {
            return;
        }

        $settingsData = json_decode($storageDef->dataText5, true, 512, JSON_THROW_ON_ERROR);
        $fieldSettings = &$fieldDef->fieldTypeConstraints->fieldSettings;

        if (!is_array($settingsData)) {
            return;
        }

        if (array_key_exists('allowedTypes', $settingsData)) {
            $fieldSettings['allowedTypes'] = $settingsData['allowedTypes'];
        }

        if (array_key_exists('allowedVisibilities', $settingsData)) {
            $fieldSettings['allowedVisibilities'] = $settingsData['allowedVisibilities'];
        }

        if (array_key_exists('allowedTags', $settingsData)) {
            $fieldSettings['allowedTags'] = $settingsData['allowedTags'];
        }

        if (array_key_exists('allowedVariations', $settingsData)) {
            $fieldSettings['allowedVariations'] = $settingsData['allowedVariations'];
        }

        if (array_key_exists('locationSource', $settingsData)) {
            $fieldSettings['locationSource'] = $settingsData['locationSource'];
        }

        if (array_key_exists('disableUpload', $settingsData)) {
            $fieldSettings['disableUpload'] = $settingsData['disableUpload'];
        }

        if (array_key_exists('parentFolder', $settingsData)) {
            $fieldSettings['parentFolder'] = $settingsData['parentFolder'];
        }

        if (array_key_exists('folder', $settingsData)) {
            $fieldSettings['folder'] = $settingsData['folder'];
        }
    }

    public function getIndexColumn(): string
    {
        return 'sort_key_string';
    }
}
