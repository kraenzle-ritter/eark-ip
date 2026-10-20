<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Reader;

use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\Exception\XmlException;
use KraenzleRitter\EarkIp\Model\MetsDocument;
use KraenzleRitter\EarkIp\Model\MetsPointer;
use KraenzleRitter\EarkIp\Model\Package;
use KraenzleRitter\EarkIp\Model\Representation;
use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\Source\Limits;
use KraenzleRitter\EarkIp\Source\PackageSource;
use KraenzleRitter\EarkIp\Source\Sources;
use KraenzleRitter\EarkIp\Source\SubdirectorySource;
use KraenzleRitter\EarkIp\SpecificationVersion;
use KraenzleRitter\EarkIp\Xml\Xml;

/**
 * Reads a package into the model without validating it, so that broken
 * packages from elsewhere can still be inspected. Fails only when there
 * is no readable package METS.xml.
 */
final class PackageReader
{
    public function __construct(private readonly ?Limits $limits = null) {}

    public function read(string|PackageSource $package, ?SpecificationVersion $version = null): Package
    {
        $source = Sources::open($package, $this->limits);

        if (! $source->hasFile('METS.xml')) {
            throw new EarkIpException("{$source->location()} has no METS.xml in its root folder.");
        }

        $mets = (new MetsReader)->read(Xml::load($source->contents('METS.xml'), 'METS.xml'), 'METS.xml');
        $detected = VersionDetector::version($mets);
        $type = VersionDetector::type($mets);

        return new Package(
            name: $source->name(),
            mets: $mets,
            type: $type,
            version: $version ?? $detected ?? SpecificationVersion::default(),
            versionDetected: $detected !== null,
            representations: $this->representations($source, $mets),
            parentAips: $this->identifiers($mets->structMap('parent AIP')?->pointers() ?? []),
            childAips: $this->identifiers($mets->structMap('child AIPs')?->pointers() ?? []),
            submission: $type === PackageType::AIP && $source->hasFile('submission/METS.xml')
                ? $this->read(new SubdirectorySource($source, 'submission'))
                : null,
            location: $source->location(),
        );
    }

    /**
     * Folders under representations/ plus folders the package METS points
     * to, each with its METS.xml when it has one.
     *
     * @return list<Representation>
     */
    private function representations(PackageSource $source, MetsDocument $mets): array
    {
        $folders = [];
        foreach ($source->directories() as $directory) {
            if (preg_match('#^representations/([^/]+)$#', $directory, $match)) {
                $folders[$directory] = $match[1];
            }
        }
        foreach ($mets->structMaps as $structMap) {
            foreach ($structMap->pointers() as $pointer) {
                $path = $mets->resolve($pointer->href);
                if ($path !== null && preg_match('#^(representations/([^/]+))/METS\.xml$#', $path, $match)) {
                    $folders[$match[1]] = $match[2];
                }
            }
        }
        ksort($folders);

        $representations = [];
        foreach ($folders as $path => $name) {
            $metsPath = $path.'/METS.xml';
            $document = null;
            $error = null;
            if ($source->hasFile($metsPath)) {
                try {
                    $document = (new MetsReader)->read(Xml::load($source->contents($metsPath), $metsPath), $metsPath);
                } catch (XmlException $exception) {
                    $error = $exception->getMessage();
                }
            }
            $representations[] = new Representation(
                name: $name,
                path: $path,
                mets: $document,
                files: array_values(array_filter($source->files(), fn (string $file) => str_starts_with($file, $path.'/'))),
                metsError: $error,
            );
        }

        return $representations;
    }

    /**
     * @param  list<MetsPointer>  $pointers
     * @return list<string>
     */
    private function identifiers(array $pointers): array
    {
        return array_values(array_filter(array_map(
            fn (MetsPointer $pointer) => $pointer->href === null ? null : trim($pointer->href),
            $pointers,
        ), fn (?string $id) => $id !== null && $id !== ''));
    }
}
