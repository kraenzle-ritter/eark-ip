<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

use DOMDocument;
use DOMElement;
use KraenzleRitter\EarkIp\MediaTypes;
use KraenzleRitter\EarkIp\Model\Agent;
use KraenzleRitter\EarkIp\Model\AltRecordId;
use KraenzleRitter\EarkIp\Model\MetadataSection;
use KraenzleRitter\EarkIp\Source\Path;
use KraenzleRitter\EarkIp\Xml\Ns;

/**
 * Builds one METS document with DOM. The writer feeds it files that are
 * already on disk, so sizes and checksums are those of the written
 * files.
 */
final class MetsBuilder
{
    public readonly DOMDocument $document;

    private readonly DOMElement $root;

    private ?DOMElement $amdSec = null;

    private ?DOMElement $fileSec = null;

    /** @var list<string> */
    private array $dmdIds = [];

    /** @var list<string> */
    private array $amdIds = [];

    /**
     * @param  string  $directory  absolute folder of this METS document; hrefs are relative to it
     */
    public function __construct(
        private readonly string $directory,
        private readonly string $objId,
        private readonly \DateTimeImmutable $created,
    ) {
        $this->document = new DOMDocument('1.0', 'UTF-8');
        $this->document->formatOutput = true;
        $this->root = $this->document->createElementNS(Ns::METS, 'mets');
        $this->document->appendChild($this->root);
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:csip', Ns::CSIP);
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sip', Ns::SIP);
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xlink', Ns::XLINK);
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', Ns::XSI);
        $this->root->setAttribute('OBJID', $objId);
    }

    public function rootAttributes(string $type, ?string $otherType, string $contentInformationType, ?string $otherContentInformationType, string $profile, ?string $label): void
    {
        if ($label !== null) {
            $this->root->setAttribute('LABEL', $label);
        }
        $this->root->setAttribute('TYPE', $type);
        if ($otherType !== null) {
            $this->root->setAttributeNS(Ns::CSIP, 'csip:OTHERTYPE', $otherType);
        }
        $this->root->setAttributeNS(Ns::CSIP, 'csip:CONTENTINFORMATIONTYPE', $contentInformationType);
        if ($otherContentInformationType !== null) {
            $this->root->setAttributeNS(Ns::CSIP, 'csip:OTHERCONTENTINFORMATIONTYPE', $otherContentInformationType);
        }
        $this->root->setAttribute('PROFILE', $profile);
    }

    /** @param  array<string, string>  $locations  namespace => schema href */
    public function schemaLocations(array $locations): void
    {
        $pairs = [];
        foreach ($locations as $namespace => $href) {
            $pairs[] = $namespace.' '.$href;
        }
        $this->root->setAttributeNS(Ns::XSI, 'xsi:schemaLocation', implode(' ', $pairs));
    }

    /**
     * @param  list<Agent>  $agents
     * @param  list<AltRecordId>  $altRecordIds
     */
    public function header(string $packageType, array $agents, array $altRecordIds = [], ?string $recordStatus = null): void
    {
        $header = $this->element('metsHdr', [
            'CREATEDATE' => $this->timestamp($this->created),
            'LASTMODDATE' => $this->timestamp($this->created),
        ]);
        if ($recordStatus !== null) {
            $header->setAttribute('RECORDSTATUS', $recordStatus);
        }
        $header->setAttributeNS(Ns::CSIP, 'csip:OAISPACKAGETYPE', $packageType);

        foreach ($agents as $agent) {
            $element = $this->element('agent', ['ROLE' => $agent->role], $header);
            if ($agent->otherRole !== null) {
                $element->setAttribute('OTHERROLE', $agent->otherRole);
            }
            $element->setAttribute('TYPE', $agent->type);
            if ($agent->otherType !== null) {
                $element->setAttribute('OTHERTYPE', $agent->otherType);
            }
            $this->element('name', [], $element, $agent->name);
            foreach ($agent->notes as $note) {
                $noteElement = $this->element('note', [], $element, $note->text);
                if ($note->type !== null) {
                    $noteElement->setAttributeNS(Ns::CSIP, 'csip:NOTETYPE', $note->type);
                }
            }
        }
        foreach ($altRecordIds as $id) {
            $this->element('altRecordID', ['TYPE' => $id->type], $header, $id->value);
        }
    }

    /** A dmdSec or amdSec/<section> pointing to a metadata file written at $path. */
    public function metadata(MetadataSection $section, MetadataFile $file, string $path, string $id): void
    {
        if ($section === MetadataSection::Descriptive) {
            $element = $this->element('dmdSec', ['ID' => $id, 'CREATED' => $this->timestamp($this->created), 'STATUS' => $file->status]);
            $this->dmdIds[] = $id;
        } else {
            $this->amdSec ??= $this->element('amdSec', ['ID' => $this->objIdPrefix('amd')]);
            $element = $this->element($section->value, ['ID' => $id, 'STATUS' => $file->status], $this->amdSec);
            $this->amdIds[] = $id;
        }

        $mdRef = $this->element('mdRef', ['ID' => $id.'-ref', 'LOCTYPE' => 'URL'], $element);
        $mdRef->setAttributeNS(Ns::XLINK, 'xlink:type', 'simple');
        $mdRef->setAttributeNS(Ns::XLINK, 'xlink:href', $this->href($path));
        $mdRef->setAttribute('MDTYPE', $file->mdType);
        if ($file->otherMdType !== null) {
            $mdRef->setAttribute('OTHERMDTYPE', $file->otherMdType);
        }
        if ($file->mdTypeVersion !== null) {
            $mdRef->setAttribute('MDTYPEVERSION', $file->mdTypeVersion);
        }
        $mdRef->setAttribute('MIMETYPE', $file->mimeType);
        $this->fileAttributes($mdRef, $path);
    }

    /**
     * A fileGrp with the given files (absolute paths).
     *
     * MIME types IANA does not register become application/octet-stream
     * (commons-ip rejects them); a PRONOM PUID keeps the format on record as
     * sip:FILEFORMATREGISTRY and sip:FILEFORMATKEY (SIP34, SIP35).
     *
     * @param  array<string, string|null>  $files  absolute path => MIME type or null to detect
     * @param  array<string, string>  $puids  absolute path => PRONOM PUID
     */
    public function fileGroup(string $use, string $id, array $files, ?string $contentInformationType = null, ?string $otherContentInformationType = null, array $puids = []): void
    {
        $this->fileSec ??= $this->element('fileSec', ['ID' => $this->objIdPrefix('fileSec')]);
        $group = $this->element('fileGrp', ['ID' => $id, 'USE' => $use], $this->fileSec);
        if ($contentInformationType !== null) {
            $group->setAttributeNS(Ns::CSIP, 'csip:CONTENTINFORMATIONTYPE', $contentInformationType);
        }
        if ($otherContentInformationType !== null) {
            $group->setAttributeNS(Ns::CSIP, 'csip:OTHERCONTENTINFORMATIONTYPE', $otherContentInformationType);
        }
        foreach ($files as $path => $mimeType) {
            $file = $this->element('file', ['ID' => Ids::for('file', $this->objId, $this->relative($path)), 'MIMETYPE' => MediaTypes::forMets($mimeType ?? MimeTypes::detect($path))], $group);
            $this->fileAttributes($file, $path);
            if (isset($puids[$path])) {
                $file->setAttributeNS(Ns::SIP, 'sip:FILEFORMATREGISTRY', 'PRONOM');
                $file->setAttributeNS(Ns::SIP, 'sip:FILEFORMATKEY', $puids[$path]);
            }
            $location = $this->element('FLocat', ['LOCTYPE' => 'URL'], $file);
            $location->setAttributeNS(Ns::XLINK, 'xlink:type', 'simple');
            $location->setAttributeNS(Ns::XLINK, 'xlink:href', $this->href($path));
        }
    }

    /**
     * The structMap labelled CSIP: a main division with child divisions
     * in the given order. Each child: label => [file group IDs, mptr
     * (href, title) or null]. The Metadata division gets DMDID/ADMID.
     *
     * @param  array<string, array{list<string>, array{string, string}|null}>  $divisions
     */
    public function structMap(array $divisions): void
    {
        $map = $this->element('structMap', ['ID' => $this->objIdPrefix('structMap'), 'TYPE' => 'PHYSICAL', 'LABEL' => 'CSIP']);
        $main = $this->element('div', ['ID' => $this->objIdPrefix('div'), 'LABEL' => $this->objId], $map);

        $metadata = $this->element('div', ['ID' => $this->objIdPrefix('div-metadata'), 'LABEL' => 'Metadata'], $main);
        if ($this->dmdIds !== []) {
            $metadata->setAttribute('DMDID', implode(' ', $this->dmdIds));
        }
        if ($this->amdIds !== []) {
            $metadata->setAttribute('ADMID', implode(' ', $this->amdIds));
        }

        foreach ($divisions as $label => [$fileIds, $pointer]) {
            $division = $this->element('div', ['ID' => Ids::for('div', $this->objId, $label), 'LABEL' => $label], $main);
            foreach ($fileIds as $fileId) {
                $this->element('fptr', ['FILEID' => $fileId], $division);
            }
            if ($pointer !== null) {
                $mptr = $this->element('mptr', ['LOCTYPE' => 'URL'], $division);
                $mptr->setAttributeNS(Ns::XLINK, 'xlink:type', 'simple');
                $mptr->setAttributeNS(Ns::XLINK, 'xlink:href', $this->href($pointer[0]));
                $mptr->setAttributeNS(Ns::XLINK, 'xlink:title', $pointer[1]);
            }
        }
    }

    /**
     * A further structMap with pointers to other packages, e.g. «parent
     * AIP» or «child AIPs» (AIP specification), or to the submission.
     *
     * @param  list<string>  $targets  identifiers, or absolute paths when $local
     */
    public function pointerMap(string $label, string $divisionLabel, array $targets, bool $local = false): void
    {
        $map = $this->element('structMap', ['ID' => Ids::for('structMap', $this->objId, $label), 'TYPE' => 'PHYSICAL', 'LABEL' => $label]);
        $outer = $this->element('div', ['ID' => Ids::for('div', $this->objId, $label), 'LABEL' => $label], $map);
        foreach ($targets as $target) {
            $division = $this->element('div', ['ID' => Ids::for('div', $this->objId, $label.$target), 'LABEL' => $divisionLabel], $outer);
            $mptr = $this->element('mptr', ['LOCTYPE' => $local ? 'URL' : 'OTHER'], $division);
            if (! $local) {
                $mptr->setAttribute('OTHERLOCTYPE', str_starts_with($target, 'urn:uuid:') ? 'UUID' : 'IDENTIFIER');
            }
            $mptr->setAttributeNS(Ns::XLINK, 'xlink:type', 'simple');
            $mptr->setAttributeNS(Ns::XLINK, 'xlink:href', $local ? $this->href($target) : $target);
        }
    }

    public function save(string $path): void
    {
        $this->document->save($path);
    }

    private function fileAttributes(DOMElement $element, string $path): void
    {
        $element->setAttribute('SIZE', (string) filesize($path));
        $element->setAttribute('CREATED', $this->timestamp((new \DateTimeImmutable('@'.(int) filemtime($path)))));
        $element->setAttribute('CHECKSUM', (string) hash_file('sha256', $path));
        $element->setAttribute('CHECKSUMTYPE', 'SHA-256');
    }

    private function href(string $path): string
    {
        return Path::toHref($this->relative($path));
    }

    /** Path relative to the folder of this METS document. */
    public function relative(string $path): string
    {
        return substr($path, strlen(rtrim($this->directory, '/')) + 1);
    }

    private function objIdPrefix(string $kind): string
    {
        return Ids::for($kind, $this->objId, '');
    }

    private function timestamp(\DateTimeImmutable $time): string
    {
        return $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /** @param  array<string, string>  $attributes */
    private function element(string $name, array $attributes = [], ?DOMElement $parent = null, ?string $text = null): DOMElement
    {
        $element = $this->document->createElementNS(Ns::METS, $name);
        foreach ($attributes as $attribute => $value) {
            $element->setAttribute($attribute, $value);
        }
        if ($text !== null) {
            $element->appendChild($this->document->createTextNode($text));
        }
        ($parent ?? $this->root)->appendChild($element);

        return $element;
    }
}
