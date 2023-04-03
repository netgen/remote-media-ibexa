<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Tests\Twig\Templating\Runtime;

use Netgen\Bundle\RemoteMediaIbexaBundle\Templating\Twig\Runtime\RemoteMediaIbexaRuntime;
use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\API\Values\Folder;
use Netgen\RemoteMedia\API\Values\RemoteResource;
use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemoteMediaIbexaRuntime::class)]
final class RemoteMediaIbexaRuntimeTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject|\Netgen\RemoteMedia\API\ProviderInterface */
    private MockObject|ProviderInterface $remoteMediaProviderMock;

    private RemoteMediaIbexaRuntime $runtime;

    protected function setUp(): void
    {
        $this->remoteMediaProviderMock = $this->createMock(ProviderInterface::class);

        $this->runtime = new RemoteMediaIbexaRuntime($this->remoteMediaProviderMock);
    }

    public function testGetIbexaAdminPreviewHtmlTag(): void
    {
        $resource = new RemoteResource(
            remoteId: 'upload|image|folder/example',
            type: RemoteResource::TYPE_VIDEO,
            url: 'https://cloudinary.com/test/upload/image/folder/example',
            md5: '548fcb29c1434a57dd5a762d58541bb1',
            name: 'example',
            folder: Folder::fromPath('folder'),
        );

        $location = new RemoteResourceLocation($resource);
        $tagString = '<img src="https://cloudinary.com/upload/some_variation_config/example">';

        $this->remoteMediaProviderMock
            ->expects(self::once())
            ->method('generateVariationHtmlTag')
            ->with($location, 'ibexa_admin', 'preview', [], true)
            ->willReturn($tagString);

        self::assertSame(
            $tagString,
            $this->runtime->getIbexaAdminPreviewHtmlTag($location),
        );
    }
}
