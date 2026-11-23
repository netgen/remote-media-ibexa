<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Templating\Twig\Extension;

use Netgen\Bundle\RemoteMediaIbexaBundle\Templating\Twig\Runtime\RemoteMediaIbexaRuntime;
use Netgen\RemoteMediaIbexa\RichText\EditorConfiguration;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RemoteMediaIbexaExtension extends AbstractExtension
{
    /**
     * @return \Twig\TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'ngrm_ibexa_admin_preview_html_tag',
                [RemoteMediaIbexaRuntime::class, 'getIbexaAdminPreviewHtmlTag'],
            ),
            new TwigFunction(
                'ngrm_ibexa_can_read_protected',
                [RemoteMediaIbexaRuntime::class, 'canReadProtected'],
            ),
            new TwigFunction(
                'ngrm_ibexa_view_resource_template',
                [RemoteMediaIbexaRuntime::class, 'getViewResourceTemplate'],
            ),
            new TwigFunction(
                'ngrm_ibexa_richtext_editor_configuration',
                [EditorConfiguration::class, 'getConfiguration'],
            ),
        ];
    }
}
