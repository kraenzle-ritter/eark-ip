<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Exception;

/**
 * A package from elsewhere is refused before anything is read from it:
 * paths that leave the package, symlinks, or more entries or bytes than
 * the configured limits allow.
 */
final class UnsafePackageException extends EarkIpException {}
