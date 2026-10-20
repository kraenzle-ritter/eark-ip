<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Writer;

use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\Resources;
use KraenzleRitter\EarkIp\Tests\TestCase;
use KraenzleRitter\EarkIp\Writer\PremisBuilder;
use KraenzleRitter\EarkIp\Xml\Xml;

final class PremisBuilderTest extends TestCase
{
    public function test_normalization_event_is_schema_valid_premis_3(): void
    {
        $xml = (new PremisBuilder)
            ->agent('agate', 'agate', 'software', '1.4.0')
            ->agent('tiffcp', 'tiffcp', 'software', '4.5.1')
            ->file('representations/rep1/data/a.tif', 1024, str_repeat('a', 64), 'fmt/353', originalName: 'a.tif')
            ->file('original/a.tif', 2048, puid: 'fmt/353', fixity: ['SHA-512' => str_repeat('b', 128)])
            ->event(
                'normalization-1',
                'normalization',
                new \DateTimeImmutable('2025-03-01T10:00:00Z'),
                'tiff_zip',
                'success',
                ['agate' => 'executing program', 'tiffcp' => 'executing program'],
                ['original/a.tif' => 'source', 'representations/rep1/data/a.tif' => 'outcome'],
            )
            ->toXml();

        $document = Xml::load($xml);
        $this->assertSame([], Xml::schemaErrors($document, Resources::premisSchema()));

        $xpath = Xml::xpath($document);
        $this->assertSame('fmt/353', $xpath->evaluate('string(//premis:object[1]//premis:formatRegistryKey)'));
        $this->assertSame('SHA-512', $xpath->evaluate('string(//premis:object[2]//premis:messageDigestAlgorithm)'));
        $this->assertSame('source', $xpath->evaluate("string(//premis:linkingObjectIdentifier[premis:linkingObjectIdentifierValue='original/a.tif']/premis:linkingObjectRole)"));
        $this->assertSame('4.5.1', $xpath->evaluate("string(//premis:agent[premis:agentName='tiffcp']/premis:agentVersion)"));
    }

    public function test_file_from_path(): void
    {
        $path = $this->temporaryFile('txt');
        file_put_contents($path, 'abc');

        $document = Xml::load((new PremisBuilder)->fileFromPath('a', $path)->toXml());

        $this->assertSame(hash('sha256', 'abc'), Xml::xpath($document)->evaluate('string(//premis:messageDigest)'));
        $this->assertSame('3', Xml::xpath($document)->evaluate('string(//premis:size)'));
        $this->assertSame([], Xml::schemaErrors($document, Resources::premisSchema()));
    }

    public function test_representation_and_entity_objects(): void
    {
        $document = Xml::load((new PremisBuilder)->representation('rep1')->intellectualEntity('sip-1', 'SIP')->toXml());

        $this->assertSame([], Xml::schemaErrors($document, Resources::premisSchema()));
    }

    public function test_event_agents_must_be_described(): void
    {
        $this->expectException(EarkIpException::class);

        (new PremisBuilder)->event('e', 'ingestion', new \DateTimeImmutable, agents: ['nobody' => 'executing program'])->toXml();
    }
}
