<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\RichText;

use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Netgen\Bundle\RemoteMediaBundle\Controller\Configuration as ConfigurationController;
use Netgen\RemoteMedia\API\Values\Folder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Builds the configuration of the remote media CKEditor 5 plugin in the RichText field.
 */
final class EditorConfiguration
{
    public const LOCATION_ID_PLACEHOLDER = '__LOCATION_ID__';

    /**
     * Request parameter with the IDs of the stored locations the editor no longer references, sent when the
     * content edit form is submitted.
     */
    public const UNUSED_LOCATIONS_PARAMETER = 'ngremotemedia_unused_locations';

    private const SETTINGS_NAMESPACE = 'netgen_remote_media_ibexa';

    public function __construct(
        private readonly ConfigResolverInterface $configResolver,
        private readonly ConfigurationController $configurationController,
        private readonly RouterInterface $router,
        private readonly Security $security,
    ) {}

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

        /** @var array<string, mixed> $config */
        $config = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return [
            'config' => [
                ...$config,
                'allowedTypes' => $this->getSetting('allowed_types'),
                'allowedVisibilities' => $this->getSetting('allowed_visibilities'),
                'allowedTags' => $this->getSetting('allowed_tags'),
                'parentFolder' => $this->resolveFolder($this->getSetting('parent_folder')),
                'folder' => $this->resolveFolder($this->getSetting('folder')),
                'disableUpload' => $this->getSetting('disable_upload') === true || !$this->canUpload(),
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
            'unusedLocationsParameter' => self::UNUSED_LOCATIONS_PARAMETER,
        ];
    }

    private function getSetting(string $name): mixed
    {
        return $this->configResolver->getParameter('richtext.' . $name, self::SETTINGS_NAMESPACE);
    }

    /**
     * The admin UI can also be rendered without a token, e.g. on error pages outside of the firewall.
     */
    private function canUpload(): bool
    {
        return $this->security->getToken() !== null && $this->security->isGranted(new Attribute('ngrm', 'upload'));
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
