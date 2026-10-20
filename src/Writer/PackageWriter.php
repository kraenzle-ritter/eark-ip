<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\Model\MetadataSection;
use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\Resources;
use KraenzleRitter\EarkIp\Source\PackageSource;
use KraenzleRitter\EarkIp\Xml\Ns;
use ZipArchive;

/**
 * Writes a package described by a PackageBuilder, as a folder or, for a
 * target ending in .zip, as a ZIP with a single root folder named after
 * the package identifier.
 *
 * Layout (CSIPSTR1–16):
 *
 *     METS.xml
 *     metadata/descriptive/  metadata/preservation/  metadata/rights/
 *     documentation/  schemas/
 *     representations/<name>/METS.xml
 *     representations/<name>/data/  …/metadata/  …/documentation/  …/schemas/
 *     submission/            (AIP: the SIP it was built from, unchanged)
 */
final class PackageWriter
{
    public function write(PackageBuilder $package, string $target): string
    {
        if (str_ends_with(strtolower($target), '.zip')) {
            return $this->writeZip($package, $target);
        }
        if (file_exists($target) && (! is_dir($target) || (scandir($target) ?: []) !== ['.', '..'])) {
            throw new EarkIpException("{$target} exists and is not an empty folder.");
        }
        $this->writeDirectory($package, $target);

        return $target;
    }

    /**
     * Folder name for a package identifier. commons-ip requires the root
     * folder to carry @OBJID exactly, so only what no file system or ZIP
     * can hold (slashes, NUL) is replaced. On Windows, identifiers such as
     * "urn:uuid:…" still cannot be unpacked; CSIP1 allows a mapping there.
     */
    public static function folderName(string $objId): string
    {
        return str_replace(['/', '\\', "\0"], '_', $objId);
    }

    private function writeZip(PackageBuilder $package, string $target): string
    {
        $staging = sys_get_temp_dir().'/eark-ip-'.bin2hex(random_bytes(6));
        $root = $staging.'/'.self::folderName($package->objId);
        try {
            $this->writeDirectory($package, $root);
            $zip = new ZipArchive;
            if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new EarkIpException("Cannot create {$target}.");
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            );
            $name = basename($root);
            $zip->addEmptyDir($name);
            foreach ($iterator as $item) {
                $entry = $name.'/'.str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
                $item->isDir() ? $zip->addEmptyDir($entry) : $zip->addFile($item->getPathname(), $entry);
            }
            if (! $zip->close()) {
                throw new EarkIpException("Cannot write {$target}.");
            }
        } finally {
            self::remove($staging);
        }

        return $target;
    }

    private function writeDirectory(PackageBuilder $package, string $root): void
    {
        $created = $package->created ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $specification = $package->specification();
        $profile = $specification->profileUrl($package->version);
        $this->mkdir($root);
        $this->mkdir($root.'/metadata');
        // The structMap always has a Documentation division; its label must lead
        // to a folder (commons-ip checks CSIP107), even when it stays empty.
        $this->mkdir($root.'/documentation');

        // Package level files.
        $descriptive = $this->place($root, 'metadata/descriptive', $package->descriptive);
        $preservation = $this->place($root, 'metadata/preservation', $package->preservation);
        $rights = $this->place($root, 'metadata/rights', $package->rights);
        $documentation = $this->placeContents($root, 'documentation', $package->documentation);
        $schemas = $this->placeContents($root, 'schemas', $this->schemas($package));

        // Representations, each with its own METS.
        $representations = [];
        foreach ($package->representations as $name => $representation) {
            $representations[$name] = $this->writeRepresentation($package, $representation, $root, $created, $profile);
        }

        if ($package->submission !== null) {
            $this->copySource($package->submission, $root.'/submission');
        }

        // The package METS.
        $mets = new MetsBuilder($root, $package->objId, $created);
        $mets->rootAttributes(
            $package->contentCategory,
            $package->otherContentCategory,
            $package->contentInformationType,
            $package->otherContentInformationType,
            $profile,
            $package->label,
        );
        $mets->schemaLocations($this->schemaLocations($package, 'schemas/'));
        $mets->header($package->type->value, [$package->software, ...$package->agents], $package->altRecordIds, $package->recordStatus);
        $this->metadataSections($mets, $package->objId, $descriptive, $preservation, $rights);

        $divisions = [];
        if ($documentation !== []) {
            $id = Ids::for('fileGrp', $package->objId, 'Documentation');
            $mets->fileGroup('Documentation', $id, array_fill_keys($documentation, null));
            $divisions['Documentation'] = [[$id], null];
        } else {
            $divisions['Documentation'] = [[], null];
        }
        $schemaGroup = Ids::for('fileGrp', $package->objId, 'Schemas');
        $mets->fileGroup('Schemas', $schemaGroup, array_fill_keys($schemas, 'application/xml'));
        $divisions['Schemas'] = [[$schemaGroup], null];

        $representationGroups = [];
        $pointers = [];
        foreach ($representations as $name => [$metsPath, $contentInformationType, $otherContentInformationType]) {
            $use = 'Representations/'.$name;
            $id = Ids::for('fileGrp', $package->objId, $use);
            $mets->fileGroup($use, $id, [$metsPath => 'application/xml'], $contentInformationType, $otherContentInformationType);
            $representationGroups[] = $id;
            $pointers[$use] = [[], [$metsPath, $id]];
        }
        if ($representations !== []) {
            $divisions['Representations'] = [$representationGroups, null];
        }
        if ($package->submission !== null) {
            // As commons-ip does: every file of the submission in its own group.
            $id = Ids::for('fileGrp', $package->objId, 'Submission');
            $files = [];
            foreach ($package->submission->files() as $file) {
                $files[$root.'/submission/'.$file] = null;
            }
            $mets->fileGroup('Submission', $id, $files);
            $divisions['Submission'] = [[$id], null];
        }
        $mets->structMap($divisions + $pointers);

        if ($package->parentAips !== []) {
            $mets->pointerMap('parent AIP', 'AIP parent identifier', $package->parentAips);
        }
        if ($package->childAips !== []) {
            $mets->pointerMap('child AIPs', 'child AIP', $package->childAips);
        }

        $mets->save($root.'/METS.xml');
    }

    /** @return array{string, string, string|null} path of the representation METS, its content information type and the other type */
    private function writeRepresentation(PackageBuilder $package, RepresentationBuilder $representation, string $root, \DateTimeImmutable $created, string $profile): array
    {
        $base = $root.'/representations/'.$representation->name;
        $this->mkdir($base.'/data');
        $this->mkdir($base.'/metadata');
        $this->mkdir($base.'/documentation');

        $data = [];
        foreach ($representation->data as $path => [$content, $mimeType]) {
            $target = $base.'/data/'.$path;
            $this->mkdir(dirname($target));
            if (file_exists($target)) {
                throw new EarkIpException("representations/{$representation->name}/data/{$path} is given twice.");
            }
            $content->writeTo($target);
            $data[$target] = $mimeType;
        }
        $descriptive = $this->place($base, 'metadata/descriptive', $representation->descriptive);
        $preservation = $this->place($base, 'metadata/preservation', $representation->preservation);
        $documentation = $this->placeContents($base, 'documentation', $representation->documentation);
        $schemas = $this->placeContents($base, 'schemas', $representation->schemas);

        $type = $representation->contentInformationType ?? $package->contentInformationType;
        $other = $representation->contentInformationType !== null ? $representation->otherContentInformationType : $package->otherContentInformationType;

        $mets = new MetsBuilder($base, $representation->name, $created);
        $mets->rootAttributes($package->contentCategory, $package->otherContentCategory, $type, $other, $profile, null);
        $mets->schemaLocations($this->schemaLocations($package, '../../schemas/'));
        $mets->header($package->type->value, [$package->software]);
        $this->metadataSections($mets, $representation->name, $descriptive, $preservation, []);

        $divisions = [];
        $dataGroup = Ids::for('fileGrp', $representation->name, 'Data');
        $mets->fileGroup('Data', $dataGroup, $data);
        $divisions['Data'] = [[$dataGroup], null];
        if ($documentation !== []) {
            $id = Ids::for('fileGrp', $representation->name, 'Documentation');
            $mets->fileGroup('Documentation', $id, array_fill_keys($documentation, null));
            $divisions['Documentation'] = [[$id], null];
        }
        if ($schemas !== []) {
            $id = Ids::for('fileGrp', $representation->name, 'Schemas');
            $mets->fileGroup('Schemas', $id, array_fill_keys($schemas, 'application/xml'));
            $divisions['Schemas'] = [[$id], null];
        }
        $mets->structMap($divisions);
        $mets->save($base.'/METS.xml');

        return [$base.'/METS.xml', $type, $other];
    }

    /**
     * @param  list<array{MetadataFile, string}>  $descriptive
     * @param  list<array{MetadataFile, string}>  $preservation
     * @param  list<array{MetadataFile, string}>  $rights
     */
    private function metadataSections(MetsBuilder $mets, string $objId, array $descriptive, array $preservation, array $rights): void
    {
        foreach ([[MetadataSection::Descriptive, $descriptive, 'dmd'], [MetadataSection::Provenance, $preservation, 'digiprov'], [MetadataSection::Rights, $rights, 'rights']] as [$section, $files, $kind]) {
            foreach ($files as [$file, $path]) {
                $mets->metadata($section, $file, $path, Ids::for($kind, $objId, $mets->relative($path)));
            }
        }
    }

    /**
     * @param  list<MetadataFile>  $files
     * @return list<array{MetadataFile, string}>
     */
    private function place(string $base, string $folder, array $files): array
    {
        $placed = [];
        foreach ($files as $file) {
            $path = $this->target($base, $folder, $file->fileName());
            $file->content->writeTo($path);
            $placed[] = [$file, $path];
        }

        return $placed;
    }

    /**
     * @param  array<string, Content>  $contents
     * @return list<string>
     */
    private function placeContents(string $base, string $folder, array $contents): array
    {
        $paths = [];
        foreach ($contents as $name => $content) {
            $path = $this->target($base, $folder, $name);
            $content->writeTo($path);
            $paths[] = $path;
        }

        return $paths;
    }

    private function target(string $base, string $folder, string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        if ($name === '' || $name === '.' || $name === '..') {
            throw new EarkIpException("\"{$name}\" cannot be a file name.");
        }
        $this->mkdir($base.'/'.$folder);
        $path = $base.'/'.$folder.'/'.$name;
        if (file_exists($path)) {
            throw new EarkIpException("{$folder}/{$name} is given twice.");
        }

        return $path;
    }

    /**
     * The schemas the package uses: METS, xlink and the DILCIS
     * extensions, PREMIS when there is PREMIS, and those of the caller.
     *
     * @return array<string, Content>
     */
    private function schemas(PackageBuilder $package): array
    {
        $shipped = [
            'mets.xsd' => Resources::path('schemas/mets.xsd'),
            'xlink.xsd' => Resources::path('schemas/xlink.xsd'),
            'DILCISExtensionMETS.xsd' => Resources::specPath($package->version, 'schemas/DILCISExtensionMETS.xsd'),
        ];
        if ($package->type === PackageType::SIP) {
            $shipped['DILCISExtensionSIPMETS.xsd'] = Resources::specPath($package->version, 'schemas/DILCISExtensionSIPMETS.xsd');
        }
        $premis = array_filter([...$package->preservation, ...array_merge(...array_map(fn ($r) => $r->preservation, array_values($package->representations)))], fn (MetadataFile $file) => str_starts_with($file->mdType, 'PREMIS'));
        if ($premis !== []) {
            $shipped['premis-v3-0.xsd'] = Resources::premisSchema();
        }

        $schemas = array_map(fn (string $path) => new FileContent($path), $shipped);

        return $package->schemas + $schemas;
    }

    /** @return array<string, string> */
    private function schemaLocations(PackageBuilder $package, string $prefix): array
    {
        $locations = [
            Ns::METS => $prefix.'mets.xsd',
            Ns::XLINK => $prefix.'xlink.xsd',
            Ns::CSIP => $prefix.'DILCISExtensionMETS.xsd',
        ];
        if ($package->type === PackageType::SIP) {
            $locations[Ns::SIP] = $prefix.'DILCISExtensionSIPMETS.xsd';
        }

        return $locations;
    }

    private function copySource(PackageSource $source, string $target): void
    {
        foreach ($source->directories() as $directory) {
            $this->mkdir($target.'/'.$directory);
        }
        foreach ($source->files() as $file) {
            $this->mkdir(dirname($target.'/'.$file));
            (new SourceContent($source, $file))->writeTo($target.'/'.$file);
        }
    }

    private function mkdir(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0777, true) && ! is_dir($path)) {
            throw new EarkIpException("Cannot create {$path}.");
        }
    }

    private static function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }
        if (! is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
            self::remove($path.'/'.$item);
        }
        rmdir($path);
    }
}
