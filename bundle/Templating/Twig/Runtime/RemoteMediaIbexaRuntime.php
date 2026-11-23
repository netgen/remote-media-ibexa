<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Templating\Twig\Runtime;

use Ibexa\Core\MVC\Symfony\Security\Authorization\Attribute;
use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\API\Values\AuthToken;
use Netgen\RemoteMedia\API\Values\RemoteResource;
use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class RemoteMediaIbexaRuntime
{
    public function __construct(
        private readonly ProviderInterface $remoteMediaProvider,
        private readonly AuthorizationCheckerInterface $security,
        private readonly string $viewResourceTemplate,
    ) {}

    public function getIbexaAdminPreviewHtmlTag(RemoteResourceLocation $remoteResourceLocation): string
    {
        $canRead = $remoteResourceLocation->getRemoteResource()->isPublic() || $this->canReadProtected();

        $remoteResourceLocation = $remoteResourceLocation->getRemoteResource()->isPublic()
            ? $remoteResourceLocation
            : $this->remoteMediaProvider->authenticateRemoteResourceLocation($remoteResourceLocation, AuthToken::fromDuration(600));

        $isImage = $remoteResourceLocation->getRemoteResource()->getType() === RemoteResource::TYPE_IMAGE;
        $variationName = $canRead ? 'preview' : 'preview_protected';
        $variationName = $isImage ? $variationName . '_image' : $variationName;

        return $this->remoteMediaProvider->generateVariationHtmlTag(
            location: $remoteResourceLocation,
            variationGroup: 'ibexa_admin',
            variationName: $variationName,
            forceVideo: true,
            useThumbnail: !$canRead,
        );
    }

    /**
     * Ibexa core voter supports only attribute objects, "ibexa:module:function" strings
     * need an additional voter (e.g. from lolautruche/ez-core-extra-bundle).
     */
    public function canReadProtected(): bool
    {
        return $this->security->isGranted(new Attribute('ngrm', 'read_protected'));
    }

    /**
     * Template configured in "netgen_remote_media.templates.view_resource".
     */
    public function getViewResourceTemplate(): string
    {
        return $this->viewResourceTemplate;
    }
}
