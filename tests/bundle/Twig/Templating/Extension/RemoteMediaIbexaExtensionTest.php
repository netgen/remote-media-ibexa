<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Tests\Twig\Templating\Extension;

use Netgen\Bundle\RemoteMediaIbexaBundle\Templating\Twig\Extension\RemoteMediaIbexaExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

#[CoversClass(RemoteMediaIbexaExtension::class)]
final class RemoteMediaIbexaExtensionTest extends TestCase
{
    private RemoteMediaIbexaExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new RemoteMediaIbexaExtension();
    }

    public function testGetFunctions(): void
    {
        self::assertNotEmpty($this->extension->getFunctions());
        self::assertContainsOnlyInstancesOf(TwigFunction::class, $this->extension->getFunctions());
    }
}
