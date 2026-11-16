<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Tests\RichText;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Netgen\Bundle\RemoteMediaBundle\Controller\Configuration as ConfigurationController;
use Netgen\RemoteMedia\Core\Provider\Cloudinary\CloudinaryProvider;
use Netgen\RemoteMedia\Core\Resolver\Variation as VariationResolver;
use Netgen\RemoteMedia\Core\Transformation\Registry;
use Netgen\RemoteMediaIbexa\RichText\EditorConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_keys;
use function http_build_query;
use function preg_replace;

#[CoversClass(EditorConfiguration::class)]
final class EditorConfigurationTest extends TestCase
{
    private const DEFAULT_SETTINGS = [
        'variation_group' => 'default',
        'location_source' => 'ibexa_richtext',
        'allowed_types' => [],
        'allowed_visibilities' => [],
        'allowed_tags' => [],
        'disable_upload' => false,
        'folder' => null,
        'parent_folder' => null,
        'upload_context' => [],
    ];

    private MockObject&Security $security;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
    }

    public function testConfiguration(): void
    {
        $configuration = $this->getConfiguration([
            'variation_group' => 'article',
            'location_source' => 'article_body',
            'allowed_types' => ['image'],
            'allowed_visibilities' => ['public'],
            'allowed_tags' => ['news'],
            'folder' => 'media/articles',
            'parent_folder' => 'media',
            'upload_context' => ['source' => 'ibexa'],
        ]);

        // Croppable variations of the configured group, merged over the default group
        self::assertSame(['default_crop', 'article_crop'], array_keys($configuration['config']['availableVariations']));
        self::assertSame(['image'], $configuration['config']['allowedTypes']);
        self::assertSame(['public'], $configuration['config']['allowedVisibilities']);
        self::assertSame(['news'], $configuration['config']['allowedTags']);
        self::assertSame(['id' => 'media/articles', 'label' => 'articles'], $configuration['config']['folder']);
        self::assertSame(['id' => 'media', 'label' => 'media'], $configuration['config']['parentFolder']);
        self::assertSame(['source' => 'ibexa'], $configuration['config']['uploadContext']);
        self::assertSame('article_body', $configuration['source']);
    }

    public function testEndpoints(): void
    {
        $configuration = $this->getConfiguration();
        $placeholder = EditorConfiguration::LOCATION_ID_PLACEHOLDER;

        // Location deletion is not exposed: locations are never deleted from the editor
        self::assertSame(
            [
                'createLocation' => '/netgen_remote_media_ajax_location_create',
                'updateLocation' => '/netgen_remote_media_ajax_location_update?locationId=' . $placeholder,
                'getSelectedImage' => '/netgen_remote_media_ajax_location_selected_image?locationId=' . $placeholder,
                'viewResource' => '/netgen_remote_media_ajax_resource_view?locationId=' . $placeholder,
            ],
            $configuration['endpoints'],
        );
        self::assertSame($placeholder, $configuration['locationIdPlaceholder']);
    }

    public function testNoFolders(): void
    {
        $configuration = $this->getConfiguration(['folder' => '', 'parent_folder' => null]);

        self::assertNull($configuration['config']['folder']);
        self::assertNull($configuration['config']['parentFolder']);
    }

    public function testUploadAllowedWithPolicy(): void
    {
        $this->security->method('getToken')->willReturn($this->createMock(TokenInterface::class));
        $this->security
            ->expects(self::once())
            ->method('isGranted')
            ->with(new Attribute('ngrm', 'upload'))
            ->willReturn(true);

        self::assertFalse($this->getConfiguration()['config']['disableUpload']);
    }

    public function testUploadDisabledWithoutPolicy(): void
    {
        $this->security->method('getToken')->willReturn($this->createMock(TokenInterface::class));
        $this->security->method('isGranted')->willReturn(false);

        self::assertTrue($this->getConfiguration()['config']['disableUpload']);
    }

    public function testUploadDisabledWithoutToken(): void
    {
        $this->security->method('getToken')->willReturn(null);
        $this->security->expects(self::never())->method('isGranted');

        self::assertTrue($this->getConfiguration()['config']['disableUpload']);
    }

    public function testUploadDisabledBySetting(): void
    {
        $this->security->method('getToken')->willReturn($this->createMock(TokenInterface::class));
        $this->security->method('isGranted')->willReturn(true);

        self::assertTrue($this->getConfiguration(['disable_upload' => true])['config']['disableUpload']);
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function getConfiguration(array $settings = []): array
    {
        $settings += self::DEFAULT_SETTINGS;

        $configResolver = $this->createMock(ConfigResolverInterface::class);
        $configResolver
            ->method('getParameter')
            ->willReturnCallback(
                static function (string $name, ?string $namespace) use ($settings): mixed {
                    self::assertSame('netgen_remote_media_ibexa', $namespace);

                    return $settings[(string) preg_replace('/^richtext\./', '', $name)];
                },
            );

        $router = $this->createMock(RouterInterface::class);
        $router
            ->method('generate')
            ->willReturnCallback(
                static fn (string $name, array $parameters = []): string => '/' . $name . ($parameters !== [] ? '?' . http_build_query($parameters) : ''),
            );

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $croppable = ['transformations' => ['crop' => []]];
        $variationResolver = new VariationResolver(
            new Registry(),
            null,
            [
                'default' => ['default_crop' => $croppable, 'thumbnail' => ['transformations' => []]],
                'article' => ['article_crop' => $croppable],
                'blog' => ['blog_crop' => $croppable],
            ],
        );

        $editorConfiguration = new EditorConfiguration(
            $configResolver,
            new ConfigurationController($router, $translator, $variationResolver, CloudinaryProvider::FOLDER_MODE_DYNAMIC, false),
            $router,
            $this->security,
        );

        return $editorConfiguration->getConfiguration();
    }
}
