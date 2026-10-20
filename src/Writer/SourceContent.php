<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

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
        VerifiedCopy::stream(
            $this->source->stream($this->path),
            $path,
            $this->source->size($this->path),
            $this->source->location().'/'.$this->path,
        );
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
