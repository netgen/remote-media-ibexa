<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\FieldType;

use Ibexa\Contracts\Core\FieldType\Indexable;
use Ibexa\Contracts\Core\Persistence\Content\Field;
use Ibexa\Contracts\Core\Persistence\Content\Type\FieldDefinition;
use Ibexa\Contracts\Core\Search;
use Ibexa\Contracts\Core\Search\FieldType\IntegerField;
use Ibexa\Contracts\Core\Search\FieldType\MultipleStringField;
use Ibexa\Contracts\Core\Search\FieldType\StringField;
use Ibexa\Core\FieldType\FieldType;
use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\API\Values\Folder;
use Netgen\RemoteMedia\API\Values\RemoteResource;
use Netgen\RemoteMedia\Exception\RemoteResourceNotFoundException;

use function array_map;
use function mb_substr;
use function trim;

final class SearchFields implements Indexable
{
    public function __construct(
        private ProviderInterface $provider,
    ) {}

    /**
     * @return Search\Field[]
     */
    public function getIndexData(Field $field, FieldDefinition $fieldDefinition): array
    {
        $remoteResourceLocationId = $field->value->externalData['remote_resource_location_id'];
        $remoteId = $field->value->externalData['remote_id'];
        $watermarkText = $field->value->externalData['watermark_text'];

        try {
            $resource = $this->provider->loadByRemoteId($remoteId);
        } catch (RemoteResourceNotFoundException $e) {
            $resource = null;
        }

        return [
            new Search\Field(
                'value',
                $remoteResourceLocationId,
                new IntegerField(),
            ),
            new Search\Field(
                'remoteresourceid',
                $resource instanceof RemoteResource ? $resource->getId() : null,
                new IntegerField(),
            ),
            new Search\Field(
                'remoteid',
                $remoteId,
                new StringField(),
            ),
            new Search\Field(
                'type',
                $this->prepareStringValue(
                    $resource instanceof RemoteResource ? $resource->getType() : null,
                ),
                new StringField(),
            ),
            new Search\Field(
                'watermarktext',
                $watermarkText,
                new StringField(),
            ),
            new Search\Field(
                'name',
                $this->prepareStringValue(
                    $resource instanceof RemoteResource ? $resource->getName() : null,
                ),
                new StringField(),
            ),
            new Search\Field(
                'folder',
                $this->prepareStringValue(
                    $resource instanceof RemoteResource
                        ? ($resource->getFolder() instanceof Folder ? (string) $resource->getFolder() : null)
                        : null,
                ),
                new StringField(),
            ),
            new Search\Field(
                'originalfilename',
                $this->prepareStringValue(
                    $resource instanceof RemoteResource ? $resource->getOriginalFilename() : null,
                ),
                new StringField(),
            ),
            new Search\Field(
                'visibility',
                $this->prepareStringValue(
                    $resource instanceof RemoteResource ? $resource->getVisibility() : null,
                ),
                new StringField(),
            ),
            new Search\Field(
                'alttext',
                $this->prepareStringValue(
                    $resource instanceof RemoteResource ? $resource->getAltText() : null,
                ),
                new StringField(),
            ),
            new Search\Field(
                'caption',
                $this->prepareStringValue(
                    $resource instanceof RemoteResource ? $resource->getCaption() : null,
                ),
                new StringField(),
            ),
            new Search\Field(
                'tags',
                array_map(
                    fn (string $tag): string => $this->prepareStringValue($tag),
                    $resource instanceof RemoteResource ? $resource->getTags() : [],
                ),
                new MultipleStringField(),
            ),
        ];
    }

    /**
     * @return array<string, FieldType>
     */
    public function getIndexDefinition(): array
    {
        return [
            'value' => new IntegerField(),
            'remoteresourceid' => new IntegerField(),
            'remoteid' => new StringField(),
            'type' => new StringField(),
            'watermarktext' => new StringField(),
            'name' => new StringField(),
            'folder' => new StringField(),
            'originalfilename' => new StringField(),
            'visibility' => new StringField(),
            'alttext' => new StringField(),
            'caption' => new StringField(),
            'tags' => new MultipleStringField(),
        ];
    }

    public function getDefaultMatchField(): ?string
    {
        return 'value';
    }

    public function getDefaultSortField(): ?string
    {
        return $this->getDefaultMatchField();
    }

    private function prepareStringValue(?string $value): ?string
    {
        return $value !== null ? trim(mb_substr($value, 0, 255)) : null;
    }
}
