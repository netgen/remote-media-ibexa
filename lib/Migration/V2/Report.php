<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Migration\V2;

final class Report
{
    /**
     * Fields converted to the Netgen Remote Media 3 format.
     */
    public int $migrated = 0;

    /**
     * Fields already in the Netgen Remote Media 3 format, e.g. from a previous run.
     */
    public int $skipped = 0;

    /**
     * Fields without a resource.
     */
    public int $empty = 0;

    /**
     * Fields which couldn't be migrated, or were migrated only partially.
     *
     * @var string[]
     */
    public array $warnings = [];
}
