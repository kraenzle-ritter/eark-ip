<?php

/**
 * Writes the hand-made reference packages under tests/Fixtures/packages/.
 * The METS documents are spelled out here, not produced by the writer,
 * so that reader and validator are tested against an independent
 * reference. Run after changing a fixture:
 *
 *     php tests/Fixtures/build-fixtures.php
 */

declare(strict_types=1);

$base = __DIR__.'/packages';

writeSip($base.'/sip-2.2.0', 'sip-2.2.0', 'https://earksip.dilcis.eu/profile/E-ARK-SIP-v2-2-0.xml');
writeSip($base.'/sip-2.1.0', 'sip-2.1.0', 'https://earksip.dilcis.eu/profile/E-ARK-SIP.xml');

function writeSip(string $root, string $objId, string $profile): void
{
    remove($root);

    $content = [
        'metadata/descriptive/dc.xml' => <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
              <dc:title>Annual report 2025</dc:title>
              <dc:date>2025</dc:date>
            </metadata>

            XML,
        'metadata/preservation/premis.xml' => <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <premis xmlns="http://www.loc.gov/premis/v3" version="3.0">
              <object xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="file">
                <objectIdentifier>
                  <objectIdentifierType>local</objectIdentifierType>
                  <objectIdentifierValue>representations/rep1/data/report.txt</objectIdentifierValue>
                </objectIdentifier>
                <objectCharacteristics>
                  <format>
                    <formatRegistry>
                      <formatRegistryName>PRONOM</formatRegistryName>
                      <formatRegistryKey>x-fmt/111</formatRegistryKey>
                    </formatRegistry>
                  </format>
                </objectCharacteristics>
              </object>
            </premis>

            XML,
        'documentation/readme.txt' => "This package is a test fixture of kraenzle-ritter/eark-ip.\n",
        'schemas/xlink.xsd' => (string) file_get_contents(dirname(__DIR__, 2).'/resources/schemas/xlink.xsd'),
        'representations/rep1/data/report.txt' => "Annual report 2025\n",
        'representations/rep1/metadata/preservation/premis.xml' => <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <premis xmlns="http://www.loc.gov/premis/v3" version="3.0">
              <object xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:type="representation">
                <objectIdentifier>
                  <objectIdentifierType>local</objectIdentifierType>
                  <objectIdentifierValue>rep1</objectIdentifierValue>
                </objectIdentifier>
              </object>
            </premis>

            XML,
    ];

    foreach ($content as $path => $data) {
        @mkdir(dirname($root.'/'.$path), 0777, true);
        file_put_contents($root.'/'.$path, $data);
    }

    $file = function (string $id, string $path, string $mime, string $href) use ($root): string {
        $absolute = $root.'/'.$path;

        return sprintf(
            '<file ID="%s" MIMETYPE="%s" SIZE="%d" CREATED="2025-03-01T10:00:00Z" CHECKSUM="%s" CHECKSUMTYPE="SHA-256"><FLocat LOCTYPE="URL" xlink:type="simple" xlink:href="%s"/></file>',
            $id, $mime, filesize($absolute), hash_file('sha256', $absolute), $href,
        );
    };
    $mdRef = function (string $id, string $path, string $type, string $base = '') use ($root): string {
        $absolute = $root.'/'.$base.$path;

        return sprintf(
            '<mdRef ID="%s" LOCTYPE="URL" xlink:type="simple" xlink:href="%s" MDTYPE="%s" MIMETYPE="text/xml" SIZE="%d" CREATED="2025-03-01T10:00:00Z" CHECKSUM="%s" CHECKSUMTYPE="SHA-256"/>',
            $id, $path, $type, filesize($absolute), hash_file('sha256', $absolute),
        );
    };

    $namespaces = 'xmlns="http://www.loc.gov/METS/" xmlns:csip="https://DILCIS.eu/XML/METS/CSIPExtensionMETS" xmlns:sip="https://DILCIS.eu/XML/METS/SIPExtensionMETS" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"';
    $schemaLocation = 'xsi:schemaLocation="http://www.loc.gov/METS/ schemas/mets.xsd http://www.w3.org/1999/xlink schemas/xlink.xsd"';
    $softwareAgent = '<agent ROLE="CREATOR" TYPE="OTHER" OTHERTYPE="SOFTWARE"><name>eark-ip fixtures</name><note csip:NOTETYPE="SOFTWARE VERSION">1.0</note></agent>';

    $representation = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <mets {$namespaces} OBJID="rep1" TYPE="Textual works – Digital" csip:CONTENTINFORMATIONTYPE="OTHER" csip:OTHERCONTENTINFORMATIONTYPE="Plain files" PROFILE="{$profile}">
          <metsHdr CREATEDATE="2025-03-01T10:00:00Z" LASTMODDATE="2025-03-01T10:00:00Z" csip:OAISPACKAGETYPE="SIP">
            {$softwareAgent}
            <agent ROLE="CREATOR" TYPE="ORGANIZATION"><name>Example Office</name></agent>
          </metsHdr>
          <amdSec ID="rep1-amd">
            <digiprovMD ID="rep1-amd-premis" STATUS="CURRENT">
              {$mdRef('rep1-amd-premis-ref', 'metadata/preservation/premis.xml', 'PREMIS', 'representations/rep1/')}
            </digiprovMD>
          </amdSec>
          <fileSec ID="rep1-fileSec">
            <fileGrp ID="rep1-data" USE="Data">
              {$file('rep1-file-report', 'representations/rep1/data/report.txt', 'text/plain', 'data/report.txt')}
            </fileGrp>
          </fileSec>
          <structMap ID="rep1-structMap" TYPE="PHYSICAL" LABEL="CSIP">
            <div ID="rep1-div" LABEL="rep1">
              <div ID="rep1-div-metadata" LABEL="Metadata" ADMID="rep1-amd-premis"/>
              <div ID="rep1-div-data" LABEL="Data"><fptr FILEID="rep1-data"/></div>
            </div>
          </structMap>
        </mets>

        XML;
    file_put_contents($root.'/representations/rep1/METS.xml', $representation);

    $package = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <mets {$namespaces} {$schemaLocation} OBJID="{$objId}" LABEL="Annual report 2025" TYPE="Textual works – Digital" csip:CONTENTINFORMATIONTYPE="OTHER" csip:OTHERCONTENTINFORMATIONTYPE="Plain files" PROFILE="{$profile}">
          <metsHdr CREATEDATE="2025-03-01T10:00:00Z" LASTMODDATE="2025-03-01T10:00:00Z" RECORDSTATUS="NEW" csip:OAISPACKAGETYPE="SIP">
            {$softwareAgent}
            <agent ROLE="ARCHIVIST" TYPE="ORGANIZATION"><name>Example Office</name><note csip:NOTETYPE="IDENTIFICATIONCODE">EX-1</note></agent>
            <agent ROLE="CREATOR" TYPE="ORGANIZATION"><name>Example Office</name><note csip:NOTETYPE="IDENTIFICATIONCODE">EX-1</note></agent>
            <agent ROLE="PRESERVATION" TYPE="ORGANIZATION"><name>Example Archives</name></agent>
            <altRecordID TYPE="SUBMISSIONAGREEMENT">SA-2025-01</altRecordID>
          </metsHdr>
          <dmdSec ID="dmd-dc" CREATED="2025-03-01T10:00:00Z" STATUS="CURRENT">
            {$mdRef('dmd-dc-ref', 'metadata/descriptive/dc.xml', 'DC')}
          </dmdSec>
          <amdSec ID="amd">
            <digiprovMD ID="amd-premis" STATUS="CURRENT">
              {$mdRef('amd-premis-ref', 'metadata/preservation/premis.xml', 'PREMIS')}
            </digiprovMD>
          </amdSec>
          <fileSec ID="fileSec">
            <fileGrp ID="grp-documentation" USE="Documentation">
              {$file('file-readme', 'documentation/readme.txt', 'text/plain', 'documentation/readme.txt')}
            </fileGrp>
            <fileGrp ID="grp-schemas" USE="Schemas">
              {$file('file-xlink', 'schemas/xlink.xsd', 'application/xml', 'schemas/xlink.xsd')}
            </fileGrp>
            <fileGrp ID="grp-rep1" USE="Representations/rep1" csip:CONTENTINFORMATIONTYPE="OTHER" csip:OTHERCONTENTINFORMATIONTYPE="Plain files">
              {$file('file-rep1-mets', 'representations/rep1/METS.xml', 'application/xml', 'representations/rep1/METS.xml')}
            </fileGrp>
          </fileSec>
          <structMap ID="structMap" TYPE="PHYSICAL" LABEL="CSIP">
            <div ID="div-root" LABEL="{$objId}">
              <div ID="div-metadata" LABEL="Metadata" DMDID="dmd-dc" ADMID="amd-premis"/>
              <div ID="div-documentation" LABEL="Documentation"><fptr FILEID="grp-documentation"/></div>
              <div ID="div-schemas" LABEL="Schemas"><fptr FILEID="grp-schemas"/></div>
              <div ID="div-representations" LABEL="Representations"><fptr FILEID="grp-rep1"/></div>
              <div ID="div-rep1" LABEL="Representations/rep1">
                <mptr LOCTYPE="URL" xlink:type="simple" xlink:href="representations/rep1/METS.xml" xlink:title="grp-rep1"/>
              </div>
            </div>
          </structMap>
        </mets>

        XML;
    file_put_contents($root.'/METS.xml', $package);

    echo "{$root}\n";
}

function remove(string $path): void
{
    if (is_file($path)) {
        unlink($path);

        return;
    }
    if (! is_dir($path)) {
        return;
    }
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
        remove($path.'/'.$item);
    }
    rmdir($path);
}
