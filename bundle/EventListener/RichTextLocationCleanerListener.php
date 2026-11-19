<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\EventListener;

use Netgen\RemoteMediaIbexa\RichText\LocationCleaner;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Deletes the unreferenced remote resource locations of RichText fields deleted during the request or the console
 * command, once all fields have been deleted.
 */
final class RichTextLocationCleanerListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly LocationCleaner $locationCleaner,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onTerminate',
            ConsoleEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onTerminate(): void
    {
        $this->locationCleaner->cleanUp();
    }
}
