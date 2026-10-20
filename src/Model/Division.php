<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** mets/structMap//div. */
final class Division
{
    /**
     * @param  list<string>  $fileIds  @FILEID of the fptr children
     * @param  list<MetsPointer>  $pointers
     * @param  list<Division>  $children
     */
    public function __construct(
        public readonly ?string $id,
        public readonly ?string $label,
        public readonly array $fileIds = [],
        public readonly array $pointers = [],
        public readonly array $children = [],
        public readonly ?string $dmdId = null,
        public readonly ?string $admId = null,
    ) {}

    public function child(string $label): ?self
    {
        foreach ($this->children as $child) {
            if ($child->label === $label) {
                return $child;
            }
        }

        return null;
    }
}
