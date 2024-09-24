<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\FieldType;

use Ibexa\Contracts\Core\FieldType\Indexable;
use Ibexa\Contracts\Core\Persistence\Content\Field;
use Ibexa\Contracts\Core\Persistence\Content\Type\FieldDefinition;
use Ibexa\Contracts\Core\Search\FieldType\IntegerField;
use Ibexa\Contracts\Core\Search\FieldType\StringField;
use Ibexa\Contracts\Core\Search\FieldType\MultipleStringField;
use Ibexa\Contracts\Core\Search;
use Ibexa\Core\FieldType\FieldType;
use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\API\Values\Folder;
use Netgen\RemoteMedia\API\Values\RemoteResource;
use Netgen\RemoteMedia\Exception\RemoteResourceNotFoundException;

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
                $resource instanceof RemoteResource ? $resource->getType() : null,
                new StringField(),
            ),
            new Search\Field(
                'watermarktext',
                $watermarkText,
                new StringField(),
            ),
            new Search\Field(
                'name',
                $resource instanceof RemoteResource ? $resource->getName() : null,
                new StringField(),
            ),
            new Search\Field(
                'folder',
                $resource instanceof RemoteResource ? ($resource->getFolder() instanceof Folder ? (string) $resource->getFolder() : null) : null,
                new StringField(),
            ),
            new Search\Field(
                'originalfilename',
                $resource instanceof RemoteResource ? $resource->getOriginalFilename() : null,
                new StringField(),
            ),
            new Search\Field(
                'visibility',
                $resource instanceof RemoteResource ? $resource->getVisibility() : null,
                new StringField(),
            ),
            new Search\Field(
                'alttext',
                $resource instanceof RemoteResource ? $resource->getAltText() : null,
                new StringField(),
            ),
            new Search\Field(
                'caption',
                $resource instanceof RemoteResource ? $resource->getCaption() : null,
                new StringField(),
            ),
            new Search\Field(
                'tags',
                $resource instanceof RemoteResource ? $resource->getTags() : [],
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
}
