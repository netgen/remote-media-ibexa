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
     * Fields which couldn't be migrated, or were migrated only partially, and resources which couldn't be
     * loaded from the remote.
     *
     * @var string[]
     */
    public array $warnings = [];

    /**
     * RichText tags left in the Netgen Remote Media 2 format, by content ID, with the field, version and reason.
     * The editor doesn't know them and removes them on the first save, so they must be fixed before.
     *
     * @var array<int, string[]>
     */
    public array $leftTags = [];
}
