<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Tests\Templating;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Netgen\Bundle\RemoteMediaBundle\Controller\Configuration as ConfigurationController;
use Netgen\Bundle\RemoteMediaIbexaBundle\Templating\Twig\Extension\RemoteMediaIbexaExtension;
use Netgen\RemoteMedia\Core\Provider\Cloudinary\CloudinaryProvider;
use Netgen\RemoteMedia\Core\Resolver\Variation as VariationResolver;
use Netgen\RemoteMedia\Core\Transformation\Registry;
use Netgen\RemoteMediaIbexa\RichText\EditorConfiguration;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

use function json_decode;
use function preg_match;
use function substr_count;

#[CoversNothing]
final class EditorConfigurationTemplateTest extends TestCase
{
    public function testRender(): void
    {
        $source = '</script><script>alert("source")</script>';
        $translation = 'Translation & "quotes" \'apostrophes\'';

        $configResolver = $this->createMock(ConfigResolverInterface::class);
        $configResolver
            ->method('getParameter')
            ->willReturnCallback(
                static fn (string $name): mixed => match ($name) {
                    'richtext.variation_group' => 'default',
                    'richtext.location_source' => $source,
                    'richtext.disable_upload' => false,
                    'richtext.folder', 'richtext.parent_folder' => null,
                    default => [],
                },
            );

        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/path');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn($translation);

        $editorConfiguration = new EditorConfiguration(
            $configResolver,
            new ConfigurationController(
                $router,
                $translator,
                new VariationResolver(new Registry(), null, []),
                CloudinaryProvider::FOLDER_MODE_DYNAMIC,
                false,
            ),
            $router,
            $this->createMock(Security::class),
        );

        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../../bundle/Resources/views/themes/admin', 'ibexadesign');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new RemoteMediaIbexaExtension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([EditorConfiguration::class => static fn (): EditorConfiguration => $editorConfiguration]));

        $html = $twig->render('@ibexadesign/ngremotemedia/richtext/editor_configuration.html.twig');

        // Configuration values can't close the script element
        self::assertSame(1, substr_count($html, '</script>'));
        self::assertSame(1, preg_match('/window\.ibexa\.addConfig\(\'richText\.ngremotemedia\', (.+)\);\s*<\/script>/s', $html, $matches));

        $configuration = json_decode($matches[1], true);
        self::assertSame($source, $configuration['source']);
        self::assertSame($translation, $configuration['config']['translations']['browse_title']);
    }
}
