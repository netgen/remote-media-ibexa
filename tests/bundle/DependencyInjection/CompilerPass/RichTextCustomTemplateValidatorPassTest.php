<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Tests\DependencyInjection\CompilerPass;

use Netgen\Bundle\RemoteMediaIbexaBundle\DependencyInjection\CompilerPass\RichTextCustomTemplateValidatorPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(RichTextCustomTemplateValidatorPass::class)]
final class RichTextCustomTemplateValidatorPassTest extends TestCase
{
    private const CUSTOM_TEMPLATE_VALIDATOR = 'Ibexa\FieldTypeRichText\RichText\Validator\CustomTemplateValidator';

    private const CUSTOM_TAGS_VALIDATOR = 'Ibexa\FieldTypeRichText\RichText\Validator\CustomTagsValidator';

    public function testProcessUsesCustomTemplateValidator(): void
    {
        // Newer ibexa/fieldtype-richtext versions may have both, CustomTemplateValidator validates tags and styles
        $container = $this->createContainer([self::CUSTOM_TEMPLATE_VALIDATOR, self::CUSTOM_TAGS_VALIDATOR]);

        $container->compile();

        self::assertSame(self::CUSTOM_TEMPLATE_VALIDATOR, $this->getValidatorId($container));
    }

    public function testProcessFallsBackToCustomTagsValidator(): void
    {
        $container = $this->createContainer([self::CUSTOM_TAGS_VALIDATOR]);

        $container->compile();

        self::assertSame(self::CUSTOM_TAGS_VALIDATOR, $this->getValidatorId($container));
    }

    public function testProcessFailsWithoutValidator(): void
    {
        $container = $this->createContainer([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Service "netgen_remote_media_ibexa.migration.v2.richtext_custom_template_validator" can\'t be defined: neither "'
            . self::CUSTOM_TEMPLATE_VALIDATOR . '" nor "' . self::CUSTOM_TAGS_VALIDATOR . '" exists.',
        );

        $container->compile();
    }

    /**
     * @param string[] $validatorIds
     */
    private function createContainer(array $validatorIds): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new RichTextCustomTemplateValidatorPass());

        foreach ($validatorIds as $validatorId) {
            // Public, so the reference isn't replaced by the inlined definition
            $container->setDefinition($validatorId, new Definition(stdClass::class))->setPublic(true);
        }

        // Stands for the RichText migration, which uses the validator
        $container->setDefinition('migration', new Definition(stdClass::class))
            ->setProperty('validator', new Reference(RichTextCustomTemplateValidatorPass::SERVICE_ID))
            ->setPublic(true);

        return $container;
    }

    private function getValidatorId(ContainerBuilder $container): string
    {
        $validator = $container->getDefinition('migration')->getProperties()['validator'];

        self::assertInstanceOf(Reference::class, $validator);

        return (string) $validator;
    }
}
