<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

use KraenzleRitter\EarkIp\Exception\EarkIpException;

final class StringContent implements Content
{
    public function __construct(
        public readonly string $contents,
        private readonly string $name = 'content',
        private readonly ?\DateTimeImmutable $created = null,
    ) {}

    public function writeTo(string $path): void
    {
        if (file_put_contents($path, $this->contents) === false) {
            throw new EarkIpException("Cannot write {$path}.");
        }
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
