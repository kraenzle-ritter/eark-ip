<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Source;

/**
 * Read access to a package, whether it lies in a folder or in a ZIP.
 * All paths are relative to the package root folder and use "/".
 */
interface PackageSource
{
    /** Name of the package root folder (for a ZIP without one: the file name without extension). */
    public function name(): string;

    /** Where the package was opened from. */
    public function location(): string;

    public function isArchive(): bool;

    /** False when a ZIP does not unpack to a single root folder (CSIPSTR1). */
    public function hasSingleRootFolder(): bool;

    /** @return list<string> all files, sorted */
    public function files(): array;

    /** @return list<string> all folders, sorted, including implicit ones */
    public function directories(): array;

    public function hasFile(string $path): bool;

    public function hasDirectory(string $path): bool;

    public function size(string $path): int;

    public function contents(string $path): string;

    public function hash(string $path, string $algorithm): string;

    /** @return resource */
    public function stream(string $path);
}
