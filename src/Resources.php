<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp;

/**
 * Paths to the files shipped under resources/: profiles, schemas and
 * vocabularies of the DILCIS Board, and the requirement catalogue built
 * from them.
 */
final class Resources
{
    public static function path(string $relative = ''): string
    {
        $base = dirname(__DIR__).'/resources';

        return $relative === '' ? $base : $base.'/'.ltrim($relative, '/');
    }

    public static function specPath(SpecificationVersion $version, string $relative = ''): string
    {
        return self::path('spec/'.$version->value.($relative === '' ? '' : '/'.ltrim($relative, '/')));
    }

    /** The XSD that validates a METS document of this version, including the DILCIS extensions. */
    public static function metsSchema(SpecificationVersion $version): string
    {
        return self::specPath($version, 'schemas/eark-ip-mets.xsd');
    }

    public static function premisSchema(): string
    {
        return self::path('schemas/premis-v3-0.xsd');
    }
}
