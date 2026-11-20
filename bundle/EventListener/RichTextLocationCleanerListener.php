<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\EventListener;

use Ibexa\Contracts\Core\Repository\Events\Content\BeforeUpdateContentEvent;
use Netgen\RemoteMediaIbexa\RichText\EditorConfiguration;
use Netgen\RemoteMediaIbexa\RichText\LocationCleaner;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function is_string;

/**
 * Deletes the unreferenced remote resource locations of RichText fields deleted during the request or the console
 * command, once all fields have been deleted, and the stored locations the editor no longer uses, once the content
 * is saved.
 */
final class RichTextLocationCleanerListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly LocationCleaner $locationCleaner,
        private readonly RequestStack $requestStack,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeUpdateContentEvent::class => 'onBeforeUpdateContent',
            KernelEvents::TERMINATE => 'onTerminate',
            ConsoleEvents::TERMINATE => 'onTerminate',
        ];
    }

    /**
     * The content edit form sends the stored locations the editor no longer uses (replaced by copies, or of removed
     * elements) when it is submitted, never with the autosave.
     */
    public function onBeforeUpdateContent(BeforeUpdateContentEvent $event): void
    {
        $value = $this->requestStack->getMainRequest()?->request->get(EditorConfiguration::UNUSED_LOCATIONS_PARAMETER);

        if (!is_string($value) || $value === '') {
            return;
        }

        $locationIds = array_filter(array_map('intval', explode(',', $value)), static fn (int $id): bool => $id > 0);

        $this->locationCleaner->collectUnused(array_values($locationIds));
    }

    public function onTerminate(): void
    {
        $this->locationCleaner->cleanUp();
    }
}
