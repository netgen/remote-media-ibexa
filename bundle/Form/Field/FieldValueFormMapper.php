<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Form\Field;

use Ibexa\Contracts\ContentForms\Data\Content\FieldData;
use Ibexa\Contracts\ContentForms\FieldType\FieldValueFormMapperInterface;
use Ibexa\Contracts\Core\Repository\FieldTypeService;
use Netgen\RemoteMedia\Form\Type\RemoteMediaType;
use Symfony\Component\Form\FormInterface;

use function array_values;

class FieldValueFormMapper implements FieldValueFormMapperInterface
{
    public function __construct(
        private readonly FieldTypeService $fieldTypeService,
    ) {
    }

    public function mapFieldValueForm(FormInterface $fieldForm, FieldData $data): void
    {
        $fieldDefinition = $data->fieldDefinition;
        $formConfig = $fieldForm->getConfig();
        $fieldSettings = $fieldDefinition->getFieldSettings();

        /** @var \Ibexa\Core\Repository\Values\Content\Content|null $content */
        $content = $fieldForm->getConfig()->getOption('content');
        $contentTypeIdentifier = $content?->getContentType()->identifier ?? 'default';

        $locationSource = $fieldSettings['locationSource'] ?? 'ibexa_content_' . $contentTypeIdentifier . '_' . $fieldDefinition->identifier;

        $fieldForm->add(
            $formConfig->getFormFactory()->createBuilder()
                ->create(
                    'value',
                    RemoteMediaType::class,
                    [
                        'required' => $fieldDefinition->isRequired,
                        'label' => $fieldDefinition->getName(),
                        'variation_group' => $contentTypeIdentifier,
                        'location_source' => $locationSource,
                        'allowed_types' => array_values($fieldSettings['allowedTypes']),
                        'allowed_visibilities' => array_values($fieldSettings['allowedVisibilities']),
                        'allowed_variations' => array_values($fieldSettings['allowedVariations']),
                        'allowed_tags' => array_values($fieldSettings['allowedTags']),
                        'disable_upload' => $fieldSettings['disableUpload'],
                        'parent_folder' => $fieldSettings['parentFolder'],
                        'folder' => $fieldSettings['folder'],
                    ],
                )
                ->setAutoInitialize(false)
                ->addModelTransformer(
                    new FieldValueTransformer(
                        $this->fieldTypeService->getFieldType('ngremotemedia'),
                    ),
                )
                ->getForm(),
        );
    }
}
