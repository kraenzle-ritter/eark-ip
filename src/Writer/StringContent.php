<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

final class StringContent implements Content
{
    public function __construct(
        public readonly string $contents,
        private readonly string $name = 'content',
        private readonly ?\DateTimeImmutable $created = null,
    ) {}

    public function writeTo(string $path): void
    {
        VerifiedCopy::string($this->contents, $path, $this->name);
    }

    public function created(): ?\DateTimeImmutable
    {
        return $this->created;
    }

    public function suggestedName(): string
    {
        return $this->name;
    }
}
