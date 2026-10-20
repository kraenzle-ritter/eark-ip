<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** A folder under representations/, with its METS.xml when it has one. */
final class Representation
{
    /** @param  list<string>  $files  package paths of all files below the folder */
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly ?MetsDocument $mets,
        public readonly array $files = [],
        public readonly ?string $metsError = null,
    ) {}

    /** @return list<string> package paths below data/ */
    public function dataFiles(): array
    {
        $prefix = $this->path.'/data/';

        return array_values(array_filter($this->files, fn (string $file) => str_starts_with($file, $prefix)));
    }
}
