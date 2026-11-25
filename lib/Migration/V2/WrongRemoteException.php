<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Migration\V2;

use RuntimeException;

/**
 * The remote doesn't have the resources of Netgen Remote Media 2, e.g. because of a wrong Cloudinary account.
 */
final class WrongRemoteException extends RuntimeException {}
