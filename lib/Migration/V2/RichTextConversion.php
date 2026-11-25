<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Migration\V2;

/**
 * Result of converting the Netgen Remote Media 2 tags of a RichText document.
 */
final class RichTextConversion
{
    /**
     * Number of converted tags.
     */
    public int $converted = 0;

    /**
     * Reasons why tags were left in the Netgen Remote Media 2 format, one per tag.
     *
     * @var string[]
     */
    public array $left = [];
}
