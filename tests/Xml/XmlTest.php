<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Xml;

use KraenzleRitter\EarkIp\Exception\XmlException;
use KraenzleRitter\EarkIp\Resources;
use KraenzleRitter\EarkIp\SpecificationVersion;
use KraenzleRitter\EarkIp\Tests\TestCase;
use KraenzleRitter\EarkIp\Xml\Xml;

final class XmlTest extends TestCase
{
    public function test_loads_and_queries_with_registered_prefixes(): void
    {
        $document = Xml::load('<mets xmlns="http://www.loc.gov/METS/" OBJID="x"><metsHdr/></mets>');

        $this->assertSame('x', Xml::xpath($document)->evaluate('string(/mets:mets/@OBJID)'));
    }

    public function test_malformed_xml_reports_the_parser_errors(): void
    {
        try {
            Xml::load('<mets><unclosed></mets>', 'METS.xml');
            $this->fail('No exception');
        } catch (XmlException $exception) {
            $this->assertStringContainsString('METS.xml is not well-formed XML.', $exception->getMessage());
            $this->assertNotEmpty($exception->errors);
        }
    }

    public function test_doctype_is_refused(): void
    {
        $this->expectException(XmlException::class);
        $this->expectExceptionMessage('declares a DOCTYPE');

        Xml::load('<?xml version="1.0"?><!DOCTYPE mets [<!ENTITY x "y">]><mets>&x;</mets>');
    }

    public function test_external_entity_is_not_resolved(): void
    {
        $secret = $this->temporaryFile('txt');
        file_put_contents($secret, 'secret');

        try {
            Xml::load('<?xml version="1.0"?><!DOCTYPE m [<!ENTITY x SYSTEM "file://'.$secret.'">]><m>&x;</m>');
            $this->fail('No exception');
        } catch (XmlException $exception) {
            $this->assertStringNotContainsString('secret', $exception->getMessage());
        }
    }

    public function test_empty_document(): void
    {
        $this->expectException(XmlException::class);

        Xml::load('  ');
    }

    public function test_schema_errors_offline(): void
    {
        $document = Xml::load('<mets xmlns="http://www.loc.gov/METS/"><unknown/></mets>');

        $errors = Xml::schemaErrors($document, Resources::metsSchema(SpecificationVersion::V2_2_0));

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('unknown', $errors[0]);
    }
}
