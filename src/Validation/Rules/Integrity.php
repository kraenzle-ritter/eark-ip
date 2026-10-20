<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

use DOMElement;
use KraenzleRitter\EarkIp\Source\Path;
use KraenzleRitter\EarkIp\Validation\MetsUnderTest;
use KraenzleRitter\EarkIp\Validation\Severity;
use KraenzleRitter\EarkIp\Validation\ValidationContext;

/**
 * Checks that a file METS points to is there and matches the recorded
 * size and checksum. The corpus of the DILCIS Board files these checks
 * under the requirement of the attribute (CSIP69 size, CSIP71 checksum,
 * CSIP79 location …), so the caller passes the requirement IDs.
 */
final class Integrity
{
    /** METS CHECKSUMTYPE values and the hash() algorithm for each. HAVAL and MNP are not computable. */
    private const ALGORITHMS = [
        'ADLER-32' => 'adler32',
        'CRC32' => 'crc32b',
        'MD5' => 'md5',
        'SHA-1' => 'sha1',
        'SHA-256' => 'sha256',
        'SHA-384' => 'sha384',
        'SHA-512' => 'sha512',
        'TIGER' => 'tiger192,3',
        'WHIRLPOOL' => 'whirlpool',
    ];

    public static function algorithm(string $checksumType): ?string
    {
        return self::ALGORITHMS[strtoupper(trim($checksumType))] ?? null;
    }

    /**
     * Resolve an xlink:href to a package path. Fails $id when the file is
     * missing; a URL outside the package only earns a warning, since CSIP
     * recommends but does not require relative paths.
     */
    public static function locate(ValidationContext $context, MetsUnderTest $mets, DOMElement $element, ?string $href, string $id): ?string
    {
        if ($href === null || trim($href) === '') {
            return null;
        }

        $candidates = Path::fromHref($href, $mets->model->directory());
        if ($candidates === []) {
            $context->fail($id, "xlink:href \"{$href}\" does not point to a file in the package; it is not checked.", $mets->at($element), Severity::Warning);

            return null;
        }

        foreach ($candidates as $path) {
            if ($context->source->hasFile($path)) {
                $context->markReferenced($path);
                $context->checked($id);

                return $path;
            }
        }

        foreach ($candidates as $path) {
            foreach ($context->source->files() as $file) {
                if (strcasecmp($file, $path) === 0) {
                    $context->markReferenced($file);
                    $context->fail($id, "xlink:href \"{$href}\" matches {$file} only when case is ignored; this breaks on case-sensitive file systems.", $mets->at($element), Severity::Warning);

                    return $file;
                }
            }
        }

        $context->fail($id, "xlink:href \"{$href}\" points to {$candidates[0]}, which is not in the package.", $mets->at($element));

        return null;
    }

    public static function size(ValidationContext $context, MetsUnderTest $mets, DOMElement $element, string $path, ?string $declared, string $id): void
    {
        if ($declared === null || ! ctype_digit(trim($declared))) {
            return;
        }
        $actual = $context->source->size($path);
        $context->expect(
            $id,
            (int) trim($declared) === $actual,
            "@SIZE {$declared} of {$path} does not match its size of {$actual} bytes.",
            $mets->at($element),
        );
    }

    public static function checksum(
        ValidationContext $context,
        MetsUnderTest $mets,
        DOMElement $element,
        string $path,
        ?string $declared,
        ?string $type,
        string $id,
    ): void {
        if ($declared === null || $type === null || trim($declared) === '') {
            return;
        }
        $algorithm = self::algorithm($type);
        if ($algorithm === null) {
            $context->fail('EARKIP-CHECKSUMTYPE', "@CHECKSUMTYPE \"{$type}\" of {$path} cannot be computed; the checksum is not verified.", $mets->at($element));

            return;
        }
        $context->checked('EARKIP-CHECKSUMTYPE');

        $actual = $context->checksum($path, $algorithm);
        $context->expect(
            $id,
            strcasecmp($actual, trim($declared)) === 0,
            "@CHECKSUM of {$path} does not match: METS has {$declared}, the file has {$actual} ({$type}).",
            $mets->at($element),
        );
    }
}
