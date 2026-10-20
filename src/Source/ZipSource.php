<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Source;

use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\Exception\PackageNotFoundException;
use KraenzleRitter\EarkIp\Exception\UnsafePackageException;
use ZipArchive;

/**
 * A package in a ZIP, read in place without unpacking. Entries that
 * leave the package, symlinks and archives over the limits are refused
 * when the ZIP is opened.
 */
final class ZipSource implements PackageSource
{
    private const SYMLINK = 0120000;

    private const FILE_TYPE_MASK = 0170000;

    private readonly ZipArchive $zip;

    /** Prefix of the root folder inside the ZIP, "" when there is none. */
    private string $prefix = '';

    /** @var array<string, string> package path => entry name */
    private array $entries = [];

    /** @var list<string> */
    private array $directories = [];

    private string $name;

    public function __construct(private readonly string $path, ?Limits $limits = null)
    {
        if (! is_file($path)) {
            throw PackageNotFoundException::forPath($path);
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new EarkIpException("{$path} is not a readable ZIP file.");
        }
        $this->zip = $zip;
        $this->name = pathinfo($path, PATHINFO_FILENAME);
        $this->index($limits ?? new Limits);
    }

    public function __destruct()
    {
        $this->zip->close();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function location(): string
    {
        return $this->path;
    }

    public function isArchive(): bool
    {
        return true;
    }

    public function hasSingleRootFolder(): bool
    {
        return $this->prefix !== '';
    }

    public function files(): array
    {
        return array_keys($this->entries);
    }

    public function directories(): array
    {
        return $this->directories;
    }

    public function hasFile(string $path): bool
    {
        return isset($this->entries[$path]);
    }

    public function hasDirectory(string $path): bool
    {
        return in_array($path, $this->directories, true);
    }

    public function size(string $path): int
    {
        $stat = $this->zip->statName($this->entry($path));

        return $stat === false ? 0 : (int) $stat['size'];
    }

    public function contents(string $path): string
    {
        $contents = $this->zip->getFromName($this->entry($path));
        if ($contents === false) {
            throw new EarkIpException("Cannot read {$path} from {$this->path}.");
        }

        return $contents;
    }

    public function hash(string $path, string $algorithm): string
    {
        $stream = $this->stream($path);
        $context = hash_init($algorithm);
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }

    public function stream(string $path)
    {
        $stream = $this->zip->getStream($this->entry($path));
        if ($stream === false) {
            throw new EarkIpException("Cannot read {$path} from {$this->path}.");
        }

        return $stream;
    }

    private function entry(string $path): string
    {
        return $this->entries[$path] ?? throw new EarkIpException("{$path} is not a file of the package.");
    }

    private function index(Limits $limits): void
    {
        if ($this->zip->numFiles > $limits->maxEntries) {
            throw new UnsafePackageException(sprintf(
                'The ZIP has %d entries, more than the limit of %d.',
                $this->zip->numFiles,
                $limits->maxEntries,
            ));
        }

        $names = [];
        $bytes = 0;
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $stat = $this->zip->statIndex($i);
            if ($stat === false) {
                continue;
            }
            $name = (string) $stat['name'];
            if ($this->isMacMetadata($name)) {
                continue;
            }
            if ($this->zip->getExternalAttributesIndex($i, $system, $attributes)
                && $system === ZipArchive::OPSYS_UNIX
                && (($attributes >> 16) & self::FILE_TYPE_MASK) === self::SYMLINK) {
                throw new UnsafePackageException("The ZIP contains a symbolic link: {$name}.");
            }

            $isDirectory = str_ends_with(str_replace('\\', '/', $name), '/');
            $normalized = Path::normalize($name);
            if ($normalized === null) {
                throw new UnsafePackageException("The ZIP contains an entry outside the package: {$name}.");
            }

            $bytes += (int) $stat['size'];
            if ($limits->maxUncompressedBytes !== null && $bytes > $limits->maxUncompressedBytes) {
                throw new UnsafePackageException(sprintf(
                    'The ZIP unpacks to more than the limit of %d bytes.',
                    $limits->maxUncompressedBytes,
                ));
            }

            $names[] = [$normalized, $name, $isDirectory];
        }

        $this->detectRootFolder($names);

        $directories = [];
        foreach ($names as [$normalized, $name, $isDirectory]) {
            $relative = $this->prefix === '' ? $normalized : substr($normalized, strlen($this->prefix));
            if ($relative === '') {
                continue;
            }
            if ($isDirectory) {
                $directories[$relative] = true;

                continue;
            }
            $this->entries[$relative] = $name;
            for ($parent = Path::directory($relative); $parent !== ''; $parent = Path::directory($parent)) {
                $directories[$parent] = true;
            }
        }

        ksort($this->entries);
        $this->directories = array_map('strval', array_keys($directories));
        sort($this->directories);
    }

    /** @param  list<array{string, string, bool}>  $names */
    private function detectRootFolder(array $names): void
    {
        $first = [];
        $topLevelFile = false;
        foreach ($names as [$normalized, , $isDirectory]) {
            $segments = explode('/', $normalized);
            $first[$segments[0]] = true;
            if (count($segments) === 1 && ! $isDirectory) {
                $topLevelFile = true;
            }
        }

        if (count($first) === 1 && ! $topLevelFile) {
            $root = (string) array_key_first($first);
            $this->prefix = $root.'/';
            $this->name = $root;
        }
    }

    private function isMacMetadata(string $name): bool
    {
        return str_starts_with($name, '__MACOSX/') || basename($name) === '.DS_Store';
    }
}
