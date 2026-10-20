<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation;

use DOMNode;

/** Where a violation was found: a file of the package and, inside XML, the line and element. */
final class Location
{
    public function __construct(
        public readonly string $file,
        public readonly ?int $line = null,
        public readonly ?string $element = null,
    ) {}

    public static function ofNode(string $file, DOMNode $node): self
    {
        return new self($file, $node->getLineNo() ?: null, $node->nodeName);
    }

    public function __toString(): string
    {
        return $this->file
            .($this->line !== null ? ':'.$this->line : '')
            .($this->element !== null ? ' <'.$this->element.'>' : '');
    }

    /** @return array{file: string, line: int|null, element: string|null} */
    public function toArray(): array
    {
        return ['file' => $this->file, 'line' => $this->line, 'element' => $this->element];
    }
}
