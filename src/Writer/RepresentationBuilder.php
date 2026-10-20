<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\Source\Path;

/** One representation: its data files, metadata, documentation and schemas. */
final class RepresentationBuilder
{
    /** @var array<string, array{Content, ?string}> path below data/ => [content, MIME type] */
    public array $data = [];

    /** @var list<MetadataFile> */
    public array $descriptive = [];

    /** @var list<MetadataFile> */
    public array $preservation = [];

    /** @var array<string, Content> */
    public array $documentation = [];

    /** @var array<string, Content> */
    public array $schemas = [];

    public ?string $contentInformationType = null;

    public ?string $otherContentInformationType = null;

    public function __construct(public readonly string $name)
    {
        if (Path::normalize($name) !== $name || str_contains($name, '/')) {
            throw new EarkIpException("\"{$name}\" cannot be the folder name of a representation.");
        }
    }

    /** Add a data file at a path below data/, e.g. "letters/1901.pdf". */
    public function file(Content|string $content, ?string $path = null, ?string $mimeType = null): self
    {
        $content = is_string($content) ? new FileContent($content) : $content;
        $target = Path::normalize($path ?? $content->suggestedName());
        if ($target === null) {
            throw new EarkIpException("\"{$path}\" is not a path inside the representation.");
        }
        $this->data[$target] = [$content, $mimeType];

        return $this;
    }

    /** Add every file below a folder, keeping the relative paths. */
    public function directory(string $directory, string $prefix = ''): self
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        $files = [];
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && ! $file->isLink()) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        foreach ($files as $file) {
            $relative = str_replace('\\', '/', substr($file, strlen(rtrim($directory, '/')) + 1));
            $this->file($file, ltrim($prefix.'/'.$relative, '/'));
        }

        return $this;
    }

    public function contentInformationType(string $type, ?string $other = null): self
    {
        $this->contentInformationType = $type;
        $this->otherContentInformationType = $other;

        return $this;
    }

    public function descriptiveMetadata(MetadataFile $file): self
    {
        $this->descriptive[] = $file;

        return $this;
    }

    public function preservationMetadata(MetadataFile $file): self
    {
        $this->preservation[] = $file;

        return $this;
    }

    public function documentation(Content|string $content, ?string $name = null): self
    {
        $content = is_string($content) ? new FileContent($content) : $content;
        $this->documentation[$name ?? $content->suggestedName()] = $content;

        return $this;
    }

    public function schema(Content|string $content, ?string $name = null): self
    {
        $content = is_string($content) ? new FileContent($content) : $content;
        $this->schemas[$name ?? $content->suggestedName()] = $content;

        return $this;
    }
}
