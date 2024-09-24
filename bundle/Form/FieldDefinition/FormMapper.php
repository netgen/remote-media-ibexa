<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Form\FieldDefinition;

use Ibexa\AdminUi\FieldType\FieldDefinitionFormMapperInterface;
use Ibexa\AdminUi\Form\Data\FieldDefinitionData;
use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\Core\Resolver\Variation as VariationResolver;
use Netgen\RemoteMedia\Form\Type\RemoteMediaFolderType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;

use function array_combine;
use function array_keys;

class FormMapper implements FieldDefinitionFormMapperInterface
{
    public function __construct(
        private readonly ProviderInterface $remoteMediaProvider,
        private readonly VariationResolver $variationResolver,
    ) {}

    public function mapFieldDefinitionForm(FormInterface $fieldDefinitionForm, FieldDefinitionData $data): void
    {
        $supportedTypes = $this->remoteMediaProvider->getSupportedTypes();
        $formConfig = $fieldDefinitionForm->getConfig();

        $choices = [];
        foreach ($supportedTypes as $supportedType) {
            $choices['field_definition.ngremotemedia.type.' . $supportedType] = $supportedType;
        }

        $fieldDefinitionForm->add('allowedTypes', ChoiceType::class, [
            'choices' => $choices,
            'property_path' => 'fieldSettings[allowedTypes]',
            'label' => /* @Desc("Allowed types") */ 'field_definition.ngremotemedia.selection_allowed_types',
            'multiple' => true,
            'expanded' => false,
            'required' => false,
        ]);

        $supportedVisibilities = $this->remoteMediaProvider->getSupportedVisibilities();

        $choices = [];
        foreach ($supportedVisibilities as $supportedVisibility) {
            $choices['field_definition.ngremotemedia.visibility.' . $supportedVisibility] = $supportedVisibility;
        }

        $fieldDefinitionForm->add('allowedVisibilities', ChoiceType::class, [
            'choices' => $choices,
            'property_path' => 'fieldSettings[allowedVisibilities]',
            'label' => /* @Desc("Allowed visibilities") */ 'field_definition.ngremotemedia.selection_allowed_visibilities',
            'multiple' => true,
            'expanded' => false,
            'required' => false,
        ]);

        $tags = $this->remoteMediaProvider->listTags();

        $fieldDefinitionForm->add('allowedTags', ChoiceType::class, [
            'choices' => array_combine($tags, $tags),
            'property_path' => 'fieldSettings[allowedTags]',
            'label' => /* @Desc("Allowed tags") */ 'field_definition.ngremotemedia.selection_allowed_tags',
            'multiple' => true,
            'expanded' => false,
            'required' => false,
        ]);

        $variations = array_keys($this->variationResolver->getAvailableCroppableVariations($data->contentTypeData->identifier));

        $fieldDefinitionForm->add('allowedVariations', ChoiceType::class, [
            'choices' => array_combine($variations, $variations),
            'property_path' => 'fieldSettings[allowedVariations]',
            'label' => /* @Desc("Allowed variations") */ 'field_definition.ngremotemedia.selection_allowed_variations',
            'multiple' => true,
            'expanded' => false,
            'required' => false,
        ]);

        $fieldDefinitionForm->add('locationSource', TextType::class, [
            'property_path' => 'fieldSettings[locationSource]',
            'label' => /* @Desc("Location source") */ 'field_definition.ngremotemedia.input_location_source',
            'required' => false,
        ]);

        $fieldDefinitionForm->add('disableUpload', CheckboxType::class, [
            'property_path' => 'fieldSettings[disableUpload]',
            'label' => /* @Desc("Disable upload") */ 'field_definition.ngremotemedia.checkbox_disable_upload',
        ]);

        $transformer = new Transformer();

        $fieldDefinitionForm->add(
            $formConfig->getFormFactory()->createBuilder()
                ->create('parentFolder', RemoteMediaFolderType::class, [
                    'property_path' => 'fieldSettings[parentFolder]',
                    'label' => /* @Desc(""Parent folder"") */ 'field_definition.ngremotemedia.selection_parent_folder',
                    'required' => false,
                ])
                ->setAutoInitialize(false)
                ->addModelTransformer($transformer)
                ->getForm(),
        );

        $fieldDefinitionForm->add(
            $formConfig->getFormFactory()->createBuilder()
                ->create('folder', RemoteMediaFolderType::class, [
                    'property_path' => 'fieldSettings[folder]',
                    'label' => /* @Desc("Folder") */ 'field_definition.ngremotemedia.selection_folder',
                    'required' => false,
                ])
                ->setAutoInitialize(false)
                ->addModelTransformer($transformer)
                ->getForm(),
        );
    }
}
