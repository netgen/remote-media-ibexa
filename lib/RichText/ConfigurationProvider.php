<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\RichText;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Contracts\FieldTypeRichText\Configuration\Provider;
use Netgen\Bundle\RemoteMediaBundle\Controller\Configuration as ConfigurationController;
use Netgen\RemoteMedia\API\Values\Folder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Exposes the remote media CKEditor 5 plugin configuration to the admin UI
 * as `ibexa.richText.ngremotemedia`.
 */
final class ConfigurationProvider implements Provider
{
    public const LOCATION_ID_PLACEHOLDER = '__LOCATION_ID__';

    private const SETTINGS_NAMESPACE = 'netgen_remote_media_ibexa';

    public function __construct(
        private readonly ConfigResolverInterface $configResolver,
        private readonly ConfigurationController $configurationController,
        private readonly RouterInterface $router,
        private readonly Security $security,
    ) {}

    public function getName(): string
    {
        return 'ngremotemedia';
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        // Paths, translations and croppable variations are resolved the same way
        // as for the remote media configuration AJAX endpoint.
        $response = ($this->configurationController)(
            new Request([
                'variationGroup' => $this->getSetting('variation_group'),
                'uploadContext' => $this->getSetting('upload_context'),
            ]),
        );

        $config = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return [
            'config' => [
                ...$config,
                'allowedTypes' => $this->getSetting('allowed_types'),
                'allowedVisibilities' => $this->getSetting('allowed_visibilities'),
                'allowedTags' => $this->getSetting('allowed_tags'),
                'parentFolder' => $this->resolveFolder($this->getSetting('parent_folder')),
                'folder' => $this->resolveFolder($this->getSetting('folder')),
                'disableUpload' => $this->getSetting('disable_upload') || !$this->canUpload(),
            ],
            'source' => $this->getSetting('location_source'),
            'endpoints' => [
                'createLocation' => $this->router->generate('netgen_remote_media_ajax_location_create'),
                'updateLocation' => $this->generateLocationPath('netgen_remote_media_ajax_location_update'),
                'deleteLocation' => $this->generateLocationPath('netgen_remote_media_ajax_location_delete'),
                'getSelectedImage' => $this->generateLocationPath('netgen_remote_media_ajax_location_selected_image'),
                'viewResource' => $this->generateLocationPath('netgen_remote_media_ajax_resource_view'),
            ],
            'locationIdPlaceholder' => self::LOCATION_ID_PLACEHOLDER,
        ];
    }

    private function getSetting(string $name): mixed
    {
        return $this->configResolver->getParameter('richtext.' . $name, self::SETTINGS_NAMESPACE);
    }

    /**
     * Twig globals (and with them this provider) are also resolved outside of the firewall, e.g. in CLI.
     */
    private function canUpload(): bool
    {
        return $this->security->getToken() !== null && $this->security->isGranted('ibexa:ngrm:upload');
    }

    /**
     * @return array{id: string, label: string}|null
     */
    private function resolveFolder(?string $path): ?array
    {
        if ($path === null || $path === '') {
            return null;
        }

        $folder = Folder::fromPath($path);

        return ['id' => $folder->getPath(), 'label' => $folder->getName()];
    }

    private function generateLocationPath(string $routeName): string
    {
        return $this->router->generate($routeName, ['locationId' => self::LOCATION_ID_PLACEHOLDER]);
    }
}
