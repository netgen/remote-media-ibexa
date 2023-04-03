<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Templating\Twig\Runtime;

use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;

class RemoteMediaIbexaRuntime
{
    public function __construct(
        private readonly ProviderInterface $remoteMediaProvider,
    ) {
    }

    public function getIbexaAdminPreviewHtmlTag(RemoteResourceLocation $remoteResourceLocation): string
    {
        return $this->remoteMediaProvider->generateVariationHtmlTag(
            location: $remoteResourceLocation,
            variationGroup: 'ibexa_admin',
            variationName: 'preview',
            forceVideo: true,
        );
    }
}
