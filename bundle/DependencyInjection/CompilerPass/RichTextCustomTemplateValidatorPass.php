<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

use function sprintf;

final class RichTextCustomTemplateValidatorPass implements CompilerPassInterface
{
    public const SERVICE_ID = 'netgen_remote_media_ibexa.migration.v2.richtext_custom_template_validator';

    private const CUSTOM_TEMPLATE_VALIDATOR = 'Ibexa\FieldTypeRichText\RichText\Validator\CustomTemplateValidator';

    private const CUSTOM_TAGS_VALIDATOR = 'Ibexa\FieldTypeRichText\RichText\Validator\CustomTagsValidator';

    /**
     * Points the custom template validator used by the Netgen Remote Media 2 RichText migration to the one the
     * installed ibexa/fieldtype-richtext provides: newer versions have CustomTemplateValidator, older ones
     * (e.g. 4.4, 4.6.19) only CustomTagsValidator.
     */
    public function process(ContainerBuilder $container): void
    {
        foreach ([self::CUSTOM_TEMPLATE_VALIDATOR, self::CUSTOM_TAGS_VALIDATOR] as $validatorId) {
            if ($container->has($validatorId)) {
                $container->setAlias(self::SERVICE_ID, new Alias($validatorId, false));

                return;
            }
        }

        throw new RuntimeException(
            sprintf(
                'Service "%s" can\'t be defined: neither "%s" nor "%s" exists. Is ibexa/fieldtype-richtext installed and its bundle enabled?',
                self::SERVICE_ID,
                self::CUSTOM_TEMPLATE_VALIDATOR,
                self::CUSTOM_TAGS_VALIDATOR,
            ),
        );
    }
}
