<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Tests\Twig\Templating\Runtime;

use Netgen\Bundle\RemoteMediaIbexaBundle\Templating\Twig\Runtime\RemoteMediaIbexaRuntime;
use Netgen\RemoteMedia\API\ProviderInterface;
use Netgen\RemoteMedia\API\Values\AuthenticatedRemoteResource;
use Netgen\RemoteMedia\API\Values\AuthToken;
use Netgen\RemoteMedia\API\Values\Folder;
use Netgen\RemoteMedia\API\Values\RemoteResource;
use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[CoversClass(RemoteMediaIbexaRuntime::class)]
final class RemoteMediaIbexaRuntimeTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject|\Netgen\RemoteMedia\API\ProviderInterface */
    private MockObject|ProviderInterface $remoteMediaProviderMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|\Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface */
    private MockObject|AuthorizationCheckerInterface $securityMock;

    private RemoteMediaIbexaRuntime $runtime;

    protected function setUp(): void
    {
        $this->remoteMediaProviderMock = $this->createMock(ProviderInterface::class);
        $this->securityMock = $this->createMock(AuthorizationCheckerInterface::class);

        $this->runtime = new RemoteMediaIbexaRuntime($this->remoteMediaProviderMock, $this->securityMock);
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

    public function testGetIbexaAdminPreviewHtmlTagProtected(): void
    {
        $resource = new RemoteResource(
            remoteId: 'upload|image|folder/example',
            type: RemoteResource::TYPE_VIDEO,
            url: 'https://cloudinary.com/test/upload/image/folder/example',
            md5: '548fcb29c1434a57dd5a762d58541bb1',
            name: 'example',
            visibility: RemoteResource::VISIBILITY_PROTECTED,
            folder: Folder::fromPath('folder'),
        );

        $location = new RemoteResourceLocation($resource);

        $authenticatedResource = new AuthenticatedRemoteResource(
            remoteResource: $resource,
            url: 'https://cloudinary.com/test/upload/image/folder/example?token=a096686ea3ecf5fd8ce3b0a9a4b2d6c5',
            token: AuthToken::fromDuration(600),
        );

        $authenticatedLocation = new RemoteResourceLocation($authenticatedResource);

        $this->remoteMediaProviderMock
            ->expects(self::once())
            ->method('authenticateRemoteResourceLocation')
            ->willReturn($authenticatedLocation);

        $tagString = '<img src="https://cloudinary.com/upload/some_variation_config/example?token=a096686ea3ecf5fd8ce3b0a9a4b2d6c5">';

        $this->remoteMediaProviderMock
            ->expects(self::once())
            ->method('generateVariationHtmlTag')
            ->with($authenticatedLocation, 'ibexa_admin', 'preview_protected', [], true)
            ->willReturn($tagString);

        self::assertSame(
            $tagString,
            $this->runtime->getIbexaAdminPreviewHtmlTag($location),
        );
    }
}
