<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

use KraenzleRitter\EarkIp\Exception\EarkIpException;

final class FileContent implements Content
{
    public function __construct(public readonly string $path)
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new EarkIpException("File not found or not readable: {$path}");
        }
    }

    public function writeTo(string $path): void
    {
        if (! copy($this->path, $path)) {
            throw new EarkIpException("Cannot copy {$this->path} to {$path}.");
        }
        touch($path, (int) filemtime($this->path));
    }

    public function created(): ?\DateTimeImmutable
    {
        $time = filemtime($this->path);

        return $time === false ? null : (new \DateTimeImmutable('@'.$time))->setTimezone(new \DateTimeZone('UTC'));
    }

    public function suggestedName(): string
    {
        return basename($this->path);
    }
}
