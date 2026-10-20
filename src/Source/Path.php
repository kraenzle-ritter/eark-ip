<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Source;

/**
 * Relative paths inside a package. METS points to files with URLs
 * (`xlink:href`), mostly relative, sometimes with "./", "file:" or
 * percent-encoding.
 */
final class Path
{
    /**
     * Normalise a path inside the package: forward slashes, no "." or
     * empty segments. Returns null for anything that leaves the package
     * (absolute paths, "..").
     */
    public static function normalize(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path)) {
            return null;
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                return null;
            }
            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    /**
     * Candidate package paths for an xlink:href, relative to the folder
     * of the METS document that contains it. Returns an empty list when
     * the href points outside the package (other scheme, absolute,
     * "..").
     *
     * @return list<string>
     */
    public static function fromHref(string $href, string $baseDirectory = ''): array
    {
        $href = trim($href);

        if (str_starts_with(strtolower($href), 'file:')) {
            $href = substr($href, 5);
            if (str_starts_with($href, '//')) {
                return [];
            }
        } elseif (preg_match('#^[A-Za-z][A-Za-z0-9+.-]*:#', $href)) {
            return [];
        }

        $base = $baseDirectory === '' ? '' : rtrim($baseDirectory, '/').'/';
        $candidates = [];
        foreach (array_unique([$href, rawurldecode($href)]) as $variant) {
            $path = self::normalize($base.$variant);
            if ($path !== null) {
                $candidates[] = $path;
            }
        }

        return array_values(array_unique($candidates));
    }

    public static function directory(string $path): string
    {
        $directory = dirname($path);

        return $directory === '.' ? '' : $directory;
    }

    /** Percent-encode a package path for use in xlink:href, keeping slashes. */
    public static function toHref(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
