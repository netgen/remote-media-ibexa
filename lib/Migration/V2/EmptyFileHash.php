<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Migration\V2;

use Netgen\RemoteMedia\API\Factory\FileHash;

/**
 * Used by the remote resource factory for Netgen Remote Media 2 values without a Cloudinary etag:
 * the default hash factory downloads the file to hash it, which a migration of thousands of resources can't do.
 */
final class EmptyFileHash implements FileHash
{
    public function createHash(string $path): string
    {
        return '';
    }
}
