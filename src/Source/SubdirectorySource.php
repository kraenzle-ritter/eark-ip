<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Source;

/**
 * A folder inside another package seen as a package of its own, e.g.
 * the SIP an AIP keeps under submission/.
 */
final class SubdirectorySource implements PackageSource
{
    private readonly string $prefix;

    public function __construct(private readonly PackageSource $parent, private readonly string $directory)
    {
        $this->prefix = rtrim($directory, '/').'/';
    }

    public function name(): string
    {
        return basename($this->directory);
    }

    public function location(): string
    {
        return $this->parent->location().'#'.$this->directory;
    }

    public function isArchive(): bool
    {
        return $this->parent->isArchive();
    }

    public function hasSingleRootFolder(): bool
    {
        return true;
    }

    public function files(): array
    {
        return $this->below($this->parent->files());
    }

    public function directories(): array
    {
        return $this->below($this->parent->directories());
    }

    public function hasFile(string $path): bool
    {
        return $this->parent->hasFile($this->prefix.$path);
    }

    public function hasDirectory(string $path): bool
    {
        return $this->parent->hasDirectory($this->prefix.$path);
    }

    public function size(string $path): int
    {
        return $this->parent->size($this->prefix.$path);
    }

    public function contents(string $path): string
    {
        return $this->parent->contents($this->prefix.$path);
    }

    public function hash(string $path, string $algorithm): string
    {
        return $this->parent->hash($this->prefix.$path, $algorithm);
    }

    public function stream(string $path)
    {
        return $this->parent->stream($this->prefix.$path);
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function below(array $paths): array
    {
        $result = [];
        foreach ($paths as $path) {
            if (str_starts_with($path, $this->prefix)) {
                $result[] = substr($path, strlen($this->prefix));
            }
        }

        return $result;
    }
}
