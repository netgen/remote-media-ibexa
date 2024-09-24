<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\FieldType;

use Ibexa\Contracts\Core\FieldType\Value as SPIValue;
use Ibexa\Contracts\Core\Persistence\Content\FieldValue;
use Ibexa\Contracts\Core\Repository\Values\ContentType\FieldDefinition;
use Ibexa\Core\FieldType\FieldType;
use Ibexa\Core\FieldType\ValidationError;
use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;
use Netgen\RemoteMediaIbexa\Converter\Hash as HashConverter;

use function array_diff;
use function array_intersect;
use function array_keys;
use function count;
use function implode;
use function in_array;
use function is_array;

final class Type extends FieldType
{
    protected $settingsSchema = [
        'allowedTypes' => [
            'type' => 'choice',
            'default' => [],
        ],
        'allowedVisibilities' => [
            'type' => 'choice',
            'default' => [],
        ],
        'allowedTags' => [
            'type' => 'choice',
            'default' => [],
        ],
        'allowedVariations' => [
            'type' => 'choice',
            'default' => [],
        ],
        'locationSource' => [
            'type' => 'string',
            'default' => null,
        ],
        'disableUpload' => [
            'type' => 'bool',
            'default' => false,
        ],
        'parentFolder' => [
            'type' => 'string',
            'default' => null,
        ],
        'folder' => [
            'type' => 'string',
            'default' => null,
        ],
    ];

    public function __construct(
        private readonly HashConverter $hashConverter,
        private readonly ProviderInterface $remoteMediaProvider,
        private readonly string $fieldTypeIdentifier,
    ) {}

    public function getFieldTypeIdentifier(): string
    {
        return $this->fieldTypeIdentifier;
    }

    public function getName(SPIValue $value, FieldDefinition $fieldDefinition, string $languageCode): string
    {
        return $value instanceof Value ? $value->getName() : '';
    }

    public function isEmptyValue(SPIValue $value): bool
    {
        if (!$value instanceof Value) {
            return true;
        }

        return !$value->remoteResourceLocation instanceof RemoteResourceLocation;
    }

    public function getEmptyValue(): Value
    {
        return new Value();
    }

    public function fromHash(mixed $hash): Value
    {
        if (!is_array($hash) || count(array_keys($hash)) === 0) {
            return $this->getEmptyValue();
        }

        $remoteResourceLocation = $this->hashConverter->remoteResourceLocationFromHash($hash);

        return $remoteResourceLocation instanceof RemoteResourceLocation
            ? new Value($remoteResourceLocation)
            : $this->getEmptyValue();
    }

    /**
     * @return array<string,string|null>
     */
    public function toHash(SPIValue $value): array
    {
        /** @var \Netgen\RemoteMediaIbexa\FieldType\Value $value */
        if (!$value->remoteResourceLocation instanceof RemoteResourceLocation) {
            return [];
        }

        return $this->hashConverter->remoteResourceLocationToHash($value->remoteResourceLocation);
    }

    public function toPersistenceValue(SPIValue $value): FieldValue
    {
        return new FieldValue(
            [
                'data' => null,
                'externalData' => $this->toHash($value),
                'sortKey' => $this->getSortInfo($value),
            ],
        );
    }

    public function fromPersistenceValue(FieldValue $fieldValue): Value
    {
        if ($fieldValue->externalData === null) {
            return $this->getEmptyValue();
        }

        return $this->fromHash($fieldValue->externalData);
    }

    public function validateFieldSettings($fieldSettings): array
    {
        $validationErrors = [];

        foreach ($fieldSettings as $name => $value) {
            if (!isset($this->settingsSchema[$name])) {
                $validationErrors[] = new ValidationError(
                    "Setting '%setting%' is unknown",
                    null,
                    [
                        '%setting%' => $name,
                    ],
                    "[{$name}]",
                );

                continue;
            }

            switch ($name) {
                case 'allowedTypes':
                    $supportedTypes = $this->remoteMediaProvider->getSupportedTypes();

                    if (!is_array($value) || count(array_diff($value, $supportedTypes)) > 0) {
                        $validationErrors[] = new ValidationError(
                            "Setting '%setting%' must be an array and contain valid supported Remote Media types.",
                            null,
                            [
                                '%setting%' => $name,
                            ],
                            "[{$name}]",
                        );
                    }

                    break;

                case 'allowedVisibilities':
                    $supportedVisibilities = $this->remoteMediaProvider->getSupportedVisibilities();

                    if (!is_array($value) || count(array_diff($value, $supportedVisibilities)) > 0) {
                        $validationErrors[] = new ValidationError(
                            "Setting '%setting%' must be an array and contain valid supported Remote Media visibilities.",
                            null,
                            [
                                '%setting%' => $name,
                            ],
                            "[{$name}]",
                        );
                    }

                    break;

                case 'allowedTags':
                    $tags = $this->remoteMediaProvider->listTags();

                    if (!is_array($value) || count(array_diff($value, $tags)) > 0) {
                        $validationErrors[] = new ValidationError(
                            "Setting '%setting%' must be an array and contain valid tags that are available.",
                            null,
                            [
                                '%setting%' => $name,
                            ],
                            "[{$name}]",
                        );
                    }

                    break;

                case 'allowedVariations':
                    if (!is_array($value)) {
                        $validationErrors[] = new ValidationError(
                            "Setting '%setting%' must be an array and contain valid variations.",
                            null,
                            [
                                '%setting%' => $name,
                            ],
                            "[{$name}]",
                        );
                    }

                    break;
            }
        }

        return $validationErrors;
    }

    public function validate(FieldDefinition $fieldDefinition, SPIValue $value)
    {
        /** @var Value $value */
        $validationErrors = [];

        if ($this->isEmptyValue($value)) {
            return $validationErrors;
        }

        $allowedTypes = $fieldDefinition->getFieldSettings()['allowedTypes'] ?? [];
        $resourceType = $value->remoteResourceLocation?->getRemoteResource()->getType();

        if (count($allowedTypes) > 0 && !in_array($resourceType, $allowedTypes, true)) {
            $validationErrors[] = new ValidationError(
                'Remote resource type "%type%" is not allowed. Must be one of the allowed types: "%types%"',
                null,
                [
                    '%type%' => $resourceType,
                    '%types%' => implode('", "', $allowedTypes),
                ],
                'allowedTypes',
            );
        }

        $allowedVisibilities = $fieldDefinition->getFieldSettings()['allowedVisibilities'] ?? [];
        $resourceVisibility = $value->remoteResourceLocation?->getRemoteResource()->getVisibility();

        if (count($allowedVisibilities) > 0 && !in_array($resourceVisibility, $allowedVisibilities, true)) {
            $validationErrors[] = new ValidationError(
                'Remote resource visibility "%visibility%" is not allowed. Must be one of the allowed visibilities: "%visibilities%"',
                null,
                [
                    '%visibility%' => $resourceVisibility,
                    '%visibilities%' => implode('", "', $allowedVisibilities),
                ],
                'allowedVisibilities',
            );
        }

        $allowedTags = $fieldDefinition->getFieldSettings()['allowedTags'] ?? [];
        $resourceTags = $value->remoteResourceLocation instanceof RemoteResourceLocation
            ? $value->remoteResourceLocation->getRemoteResource()->getTags()
            : [];

        if (count($allowedTags) > 0 && count(array_intersect($resourceTags, $allowedTags)) === 0) {
            $validationErrors[] = new ValidationError(
                'Remote resource is not allowed because it doesn\'t contain any of the allowed tags: "%tags%"',
                null,
                [
                    '%tags%' => implode('", "', $allowedTags),
                ],
                'allowedTags',
            );
        }

        return $validationErrors;
    }

    public function isSearchable(): bool
    {
        return true;
    }

    protected function createValueFromInput(mixed $inputValue): Value
    {
        return match (true) {
            $inputValue instanceof RemoteResourceLocation => new Value($inputValue),
            $inputValue instanceof Value => $inputValue,
            default => $this->getEmptyValue(),
        };
    }

    protected function checkValueStructure(SPIValue $value): void {}
}
