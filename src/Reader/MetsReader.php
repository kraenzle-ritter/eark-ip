<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Reader;

use DOMDocument;
use DOMElement;
use DOMXPath;
use KraenzleRitter\EarkIp\Model\Agent;
use KraenzleRitter\EarkIp\Model\AgentNote;
use KraenzleRitter\EarkIp\Model\AltRecordId;
use KraenzleRitter\EarkIp\Model\Division;
use KraenzleRitter\EarkIp\Model\FileEntry;
use KraenzleRitter\EarkIp\Model\FileGroup;
use KraenzleRitter\EarkIp\Model\MetadataReference;
use KraenzleRitter\EarkIp\Model\MetadataSection;
use KraenzleRitter\EarkIp\Model\MetsDocument;
use KraenzleRitter\EarkIp\Model\MetsHeader;
use KraenzleRitter\EarkIp\Model\MetsPointer;
use KraenzleRitter\EarkIp\Model\StructMap;
use KraenzleRitter\EarkIp\Xml\Ns;
use KraenzleRitter\EarkIp\Xml\Xml;

/**
 * Turns a METS document into the model. Reads what is there and leaves
 * missing values null; judging the document is the validator's job.
 */
final class MetsReader
{
    private DOMXPath $xpath;

    public function read(DOMDocument $document, string $path): MetsDocument
    {
        $this->xpath = Xml::xpath($document);
        $root = $document->documentElement;
        assert($root instanceof DOMElement);

        return new MetsDocument(
            path: $path,
            objId: $this->attribute($root, 'OBJID'),
            profile: $this->attribute($root, 'PROFILE'),
            type: $this->attribute($root, 'TYPE'),
            header: $this->header(),
            metadata: $this->metadata(),
            fileGroups: $this->fileGroups(),
            structMaps: $this->structMaps(),
            label: $this->attribute($root, 'LABEL'),
            otherType: $this->attribute($root, 'OTHERTYPE', Ns::CSIP),
            contentInformationType: $this->attribute($root, 'CONTENTINFORMATIONTYPE', Ns::CSIP),
            otherContentInformationType: $this->attribute($root, 'OTHERCONTENTINFORMATIONTYPE', Ns::CSIP),
        );
    }

    private function header(): ?MetsHeader
    {
        $header = $this->first('/mets:mets/mets:metsHdr');
        if ($header === null) {
            return null;
        }

        $agents = [];
        foreach ($this->elements('mets:agent', $header) as $agent) {
            $notes = [];
            foreach ($this->elements('mets:note', $agent) as $note) {
                $notes[] = new AgentNote(trim($note->textContent), $this->attribute($note, 'NOTETYPE', Ns::CSIP));
            }
            $agents[] = new Agent(
                role: (string) $this->attribute($agent, 'ROLE'),
                type: (string) $this->attribute($agent, 'TYPE'),
                name: trim((string) $this->first('mets:name', $agent)?->textContent),
                notes: $notes,
                otherRole: $this->attribute($agent, 'OTHERROLE'),
                otherType: $this->attribute($agent, 'OTHERTYPE'),
            );
        }

        $altRecordIds = [];
        foreach ($this->elements('mets:altRecordID', $header) as $id) {
            $altRecordIds[] = new AltRecordId((string) $this->attribute($id, 'TYPE'), trim($id->textContent));
        }

        return new MetsHeader(
            createDate: $this->attribute($header, 'CREATEDATE'),
            oaisPackageType: $this->attribute($header, 'OAISPACKAGETYPE', Ns::CSIP),
            agents: $agents,
            lastModDate: $this->attribute($header, 'LASTMODDATE'),
            recordStatus: $this->attribute($header, 'RECORDSTATUS'),
            altRecordIds: $altRecordIds,
        );
    }

    /** @return list<MetadataReference> */
    private function metadata(): array
    {
        $references = [];
        foreach ($this->elements('/mets:mets/mets:dmdSec | /mets:mets/mets:amdSec/*') as $section) {
            $kind = MetadataSection::tryFrom($section->localName ?? '');
            if ($kind === null || $section->namespaceURI !== Ns::METS) {
                continue;
            }
            $mdRefs = $this->elements('mets:mdRef', $section);
            if ($mdRefs === []) {
                $references[] = new MetadataReference(
                    section: $kind,
                    sectionId: $this->attribute($section, 'ID'),
                    href: null,
                    mdType: $this->attribute($this->first('mets:mdWrap', $section), 'MDTYPE'),
                    status: $this->attribute($section, 'STATUS'),
                    sectionCreated: $this->attribute($section, 'CREATED'),
                    locType: null,
                );

                continue;
            }
            foreach ($mdRefs as $mdRef) {
                $references[] = new MetadataReference(
                    section: $kind,
                    sectionId: $this->attribute($section, 'ID'),
                    href: $this->attribute($mdRef, 'href', Ns::XLINK),
                    mdType: $this->attribute($mdRef, 'MDTYPE'),
                    otherMdType: $this->attribute($mdRef, 'OTHERMDTYPE'),
                    mdTypeVersion: $this->attribute($mdRef, 'MDTYPEVERSION'),
                    mimeType: $this->attribute($mdRef, 'MIMETYPE'),
                    size: $this->integer($this->attribute($mdRef, 'SIZE')),
                    created: $this->attribute($mdRef, 'CREATED'),
                    checksum: $this->attribute($mdRef, 'CHECKSUM'),
                    checksumType: $this->attribute($mdRef, 'CHECKSUMTYPE'),
                    status: $this->attribute($section, 'STATUS'),
                    sectionCreated: $this->attribute($section, 'CREATED'),
                    id: $this->attribute($mdRef, 'ID'),
                    locType: $this->attribute($mdRef, 'LOCTYPE'),
                    label: $this->attribute($mdRef, 'LABEL'),
                );
            }
        }

        return $references;
    }

    /** @return list<FileGroup> */
    private function fileGroups(): array
    {
        $groups = [];
        foreach ($this->elements('/mets:mets/mets:fileSec//mets:fileGrp') as $group) {
            $files = [];
            foreach ($this->elements('mets:file', $group) as $file) {
                $location = $this->first('mets:FLocat', $file);
                $files[] = new FileEntry(
                    id: $this->attribute($file, 'ID'),
                    href: $this->attribute($location, 'href', Ns::XLINK),
                    mimeType: $this->attribute($file, 'MIMETYPE'),
                    size: $this->integer($this->attribute($file, 'SIZE')),
                    created: $this->attribute($file, 'CREATED'),
                    checksum: $this->attribute($file, 'CHECKSUM'),
                    checksumType: $this->attribute($file, 'CHECKSUMTYPE'),
                    ownerId: $this->attribute($file, 'OWNERID'),
                    admId: $this->attribute($file, 'ADMID'),
                    dmdId: $this->attribute($file, 'DMDID'),
                    use: $this->attribute($file, 'USE'),
                    formatName: $this->attribute($file, 'FILEFORMATNAME', Ns::SIP),
                    formatVersion: $this->attribute($file, 'FILEFORMATVERSION', Ns::SIP),
                    formatRegistry: $this->attribute($file, 'FILEFORMATREGISTRY', Ns::SIP) ?? $this->attribute($file, 'FORMATREGISTRY', Ns::SIP),
                    formatKey: $this->attribute($file, 'FILEFORMATKEY', Ns::SIP) ?? $this->attribute($file, 'FORMATREGISTRYKEY', Ns::SIP),
                    locType: $this->attribute($location, 'LOCTYPE'),
                );
            }
            $groups[] = new FileGroup(
                id: $this->attribute($group, 'ID'),
                use: $this->attribute($group, 'USE'),
                files: $files,
                contentInformationType: $this->attribute($group, 'CONTENTINFORMATIONTYPE', Ns::CSIP),
                otherContentInformationType: $this->attribute($group, 'OTHERCONTENTINFORMATIONTYPE', Ns::CSIP),
                admId: $this->attribute($group, 'ADMID'),
            );
        }

        return $groups;
    }

    /** @return list<StructMap> */
    private function structMaps(): array
    {
        $maps = [];
        foreach ($this->elements('/mets:mets/mets:structMap') as $map) {
            $maps[] = new StructMap(
                id: $this->attribute($map, 'ID'),
                type: $this->attribute($map, 'TYPE'),
                label: $this->attribute($map, 'LABEL'),
                divisions: array_map(fn (DOMElement $div) => $this->division($div), $this->elements('mets:div', $map)),
            );
        }

        return $maps;
    }

    private function division(DOMElement $div): Division
    {
        return new Division(
            id: $this->attribute($div, 'ID'),
            label: $this->attribute($div, 'LABEL'),
            fileIds: array_values(array_filter(array_map(
                fn (DOMElement $fptr) => $this->attribute($fptr, 'FILEID'),
                $this->elements('mets:fptr', $div),
            ), fn (?string $id) => $id !== null)),
            pointers: array_map(fn (DOMElement $mptr) => new MetsPointer(
                href: $this->attribute($mptr, 'href', Ns::XLINK),
                title: $this->attribute($mptr, 'title', Ns::XLINK),
                locType: $this->attribute($mptr, 'LOCTYPE'),
                otherLocType: $this->attribute($mptr, 'OTHERLOCTYPE'),
                id: $this->attribute($mptr, 'ID'),
            ), $this->elements('mets:mptr', $div)),
            children: array_map(fn (DOMElement $child) => $this->division($child), $this->elements('mets:div', $div)),
            dmdId: $this->attribute($div, 'DMDID'),
            admId: $this->attribute($div, 'ADMID'),
        );
    }

    private function attribute(?DOMElement $element, string $name, ?string $namespace = null): ?string
    {
        if ($element === null) {
            return null;
        }
        $has = $namespace === null ? $element->hasAttribute($name) : $element->hasAttributeNS($namespace, $name);
        if (! $has) {
            return null;
        }

        return $namespace === null ? $element->getAttribute($name) : $element->getAttributeNS($namespace, $name);
    }

    private function integer(?string $value): ?int
    {
        return $value !== null && ctype_digit(trim($value)) ? (int) trim($value) : null;
    }

    private function first(string $query, ?DOMElement $context = null): ?DOMElement
    {
        return $this->elements($query, $context)[0] ?? null;
    }

    /** @return list<DOMElement> */
    private function elements(string $query, ?DOMElement $context = null): array
    {
        $elements = [];
        foreach ($this->xpath->query($query, $context) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }
}
