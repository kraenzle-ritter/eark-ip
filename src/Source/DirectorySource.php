<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Source;

use FilesystemIterator;
use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\Exception\PackageNotFoundException;
use KraenzleRitter\EarkIp\Exception\UnsafePackageException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/** A package in a folder; the folder is the package root folder. */
final class DirectorySource implements PackageSource
{
    private readonly string $root;

    /** @var list<string> */
    private array $files = [];

    /** @var list<string> */
    private array $directories = [];

    public function __construct(string $root)
    {
        $real = realpath($root);
        if ($real === false || ! is_dir($real)) {
            throw PackageNotFoundException::forPath($root);
        }
        $this->root = $real;
        $this->scan();
    }

    public function name(): string
    {
        return basename($this->root);
    }

    public function location(): string
    {
        return $this->root;
    }

    public function isArchive(): bool
    {
        return false;
    }

    public function hasSingleRootFolder(): bool
    {
        return true;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function directories(): array
    {
        return $this->directories;
    }

    public function hasFile(string $path): bool
    {
        return in_array($path, $this->files, true);
    }

    public function hasDirectory(string $path): bool
    {
        return in_array($path, $this->directories, true);
    }

    public function size(string $path): int
    {
        return (int) filesize($this->absolute($path));
    }

    public function contents(string $path): string
    {
        $contents = file_get_contents($this->absolute($path));
        if ($contents === false) {
            throw new EarkIpException("Cannot read {$path}.");
        }

        return $contents;
    }

    public function hash(string $path, string $algorithm): string
    {
        return (string) hash_file($algorithm, $this->absolute($path));
    }

    public function stream(string $path)
    {
        $stream = fopen($this->absolute($path), 'rb');
        if ($stream === false) {
            throw new EarkIpException("Cannot read {$path}.");
        }

        return $stream;
    }

    private function absolute(string $path): string
    {
        if (! $this->hasFile($path)) {
            throw new EarkIpException("{$path} is not a file of the package.");
        }

        return $this->root.'/'.$path;
    }

    private function scan(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($this->root) + 1));
            if ($item->getFilename() === '.DS_Store') {
                continue;
            }
            if ($item->isLink()) {
                throw new UnsafePackageException("The package contains a symbolic link: {$relative}.");
            }
            if ($item->isDir()) {
                $this->directories[] = $relative;
            } else {
                $this->files[] = $relative;
            }
        }

        sort($this->files);
        sort($this->directories);
    }
}
