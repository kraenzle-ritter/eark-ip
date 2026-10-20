<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

use KraenzleRitter\EarkIp\Exception\CopyNotVerifiedException;

/**
 * Writes a stream to a file and checks the result before anything relies
 * on it. The writer computes SIZE and CHECKSUM in the METS from the written
 * file, so an unchecked copy that was cut short would carry its own
 * matching checksum and pass validation.
 *
 * The data goes to a temporary name next to the target, hashed while it is
 * read; the copy is hashed again, compared with what was read and with the
 * expected size, and only then renamed into place.
 *
 * @internal
 */
final class VerifiedCopy
{
    private const CHUNK = 1048576;

    /**
     * @param  resource  $in  closed by this method
     * @param  int|null  $expectedSize  bytes the source has, when known
     * @return string SHA-256 of the data
     *
     * @throws CopyNotVerifiedException
     */
    public static function stream($in, string $target, ?int $expectedSize, string $source): string
    {
        $temporary = dirname($target).'/.'.basename($target).'.partial-'.bin2hex(random_bytes(4));
        try {
            $out = @fopen($temporary, 'wb');
            if ($out === false) {
                throw CopyNotVerifiedException::failed($source, $target, 'cannot write the copy');
            }
            $hash = hash_init('sha256');
            $bytes = 0;
            try {
                while (! feof($in)) {
                    $chunk = fread($in, self::CHUNK);
                    if ($chunk === false) {
                        throw CopyNotVerifiedException::failed($source, $target, 'read error');
                    }
                    if ($chunk === '') {
                        continue;
                    }
                    hash_update($hash, $chunk);
                    $bytes += strlen($chunk);
                    if (fwrite($out, $chunk) !== strlen($chunk)) {
                        throw CopyNotVerifiedException::failed($source, $target, 'write error (disk full?)');
                    }
                }
            } finally {
                fclose($out);
            }

            if ($expectedSize !== null && $bytes !== $expectedSize) {
                throw CopyNotVerifiedException::failed($source, $target, "read {$bytes} of {$expectedSize} bytes");
            }
            $expected = hash_final($hash);
            $actual = (string) @hash_file('sha256', $temporary);
            if ($actual !== $expected || @filesize($temporary) !== $bytes) {
                throw CopyNotVerifiedException::mismatch($source, $target, $expected, $actual);
            }
            if (! @rename($temporary, $target)) {
                throw CopyNotVerifiedException::failed($source, $target, 'cannot move the copy into place');
            }

            return $expected;
        } finally {
            if (is_resource($in)) {
                fclose($in);
            }
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    /**
     * @return string SHA-256 of the contents
     *
     * @throws CopyNotVerifiedException
     */
    public static function string(string $contents, string $target, string $source): string
    {
        $in = fopen('php://memory', 'r+b');
        if ($in === false) {
            throw CopyNotVerifiedException::failed($source, $target, 'cannot buffer the contents');
        }
        fwrite($in, $contents);
        rewind($in);

        return self::stream($in, $target, strlen($contents), $source);
    }
}
