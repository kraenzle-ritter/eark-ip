<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\Source\PackageSource;

/** A file of another package, e.g. a SIP an AIP is built from. */
final class SourceContent implements Content
{
    public function __construct(
        private readonly PackageSource $source,
        public readonly string $path,
        private readonly ?\DateTimeImmutable $created = null,
    ) {}

    public function writeTo(string $path): void
    {
        $in = $this->source->stream($this->path);
        $out = fopen($path, 'wb');
        if ($out === false) {
            throw new EarkIpException("Cannot write {$path}.");
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
    }

    public function created(): ?\DateTimeImmutable
    {
        return $this->created;
    }

    public function suggestedName(): string
    {
        return basename($this->path);
    }
}
