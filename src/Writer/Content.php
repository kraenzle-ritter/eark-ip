<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

use KraenzleRitter\EarkIp\Exception\EarkIpException;

/** Something to write into a package: a file on disk, a string, or a file of another package. */
interface Content
{
    /**
     * Write the content to the given path, streaming where possible. The
     * written file is checked against what was read; nothing is left at
     * the path when the copy fails.
     *
     * @throws EarkIpException
     */
    public function writeTo(string $path): void;

    /** When the content was created, for @CREATED; null when unknown. */
    public function created(): ?\DateTimeImmutable;

    /** A file name to use when the caller gives none. */
    public function suggestedName(): string;
}
