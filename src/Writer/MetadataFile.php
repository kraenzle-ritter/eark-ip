<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

/**
 * A metadata file to put into a package, with the type METS records for
 * it (@MDTYPE, @OTHERMDTYPE, @MDTYPEVERSION).
 */
final class MetadataFile
{
    public function __construct(
        public readonly Content $content,
        public readonly string $mdType,
        public readonly ?string $otherMdType = null,
        public readonly ?string $mdTypeVersion = null,
        public readonly ?string $name = null,
        public readonly string $status = 'CURRENT',
        public readonly string $mimeType = 'text/xml',
    ) {}

    /** "OTHER" types are given as their name, e.g. "anton-import-format". */
    public static function of(Content|string $content, string $type, ?string $version = null, ?string $name = null, ?string $mimeType = null): self
    {
        $content = is_string($content) ? new FileContent($content) : $content;
        $known = in_array($type, self::METS_TYPES, true);
        $mime = $mimeType ?? (str_ends_with(strtolower($name ?? $content->suggestedName()), '.json') ? 'application/json' : 'text/xml');

        return new self($content, $known ? $type : 'OTHER', $known ? null : $type, $version, $name, mimeType: $mime);
    }

    public function fileName(): string
    {
        return $this->name ?? $this->content->suggestedName();
    }

    /** Values of @MDTYPE in METS 1.12. */
    private const METS_TYPES = [
        'MARC', 'MODS', 'EAD', 'DC', 'NISOIMG', 'LC-AV', 'VRA', 'TEIHDR', 'DDI', 'FGDC', 'LOM',
        'PREMIS', 'PREMIS:OBJECT', 'PREMIS:AGENT', 'PREMIS:RIGHTS', 'PREMIS:EVENT', 'TEXTMD', 'METSRIGHTS',
        'ISO 19115:2003 NAP', 'EAC-CPF', 'LIDO', 'OTHER',
    ];
}
