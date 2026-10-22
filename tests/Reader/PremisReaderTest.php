<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Reader;

use KraenzleRitter\EarkIp\Reader\PackageReader;
use KraenzleRitter\EarkIp\Reader\PremisReader;
use KraenzleRitter\EarkIp\Tests\TestCase;
use KraenzleRitter\EarkIp\Writer\PackageBuilder;
use KraenzleRitter\EarkIp\Writer\PackageWriter;
use KraenzleRitter\EarkIp\Writer\PremisBuilder;
use KraenzleRitter\EarkIp\Writer\RepresentationBuilder;
use KraenzleRitter\EarkIp\Writer\StringContent;

final class PremisReaderTest extends TestCase
{
    public function test_objects_relationships_events_and_agents_of_premis_3(): void
    {
        $folder = $this->package();
        $package = (new PackageReader)->read($folder);

        $premis = (new PremisReader)->read($package, $folder);

        $this->assertSame(['metadata/preservation/premis.xml'], $premis->files);
        $this->assertSame([], $premis->notes);
        $this->assertCount(2, $premis->objects);
        $jpeg = $premis->objects[1];
        $this->assertSame('file', $jpeg->category);
        $this->assertSame(['representations/rep2/data/a.jpg'], $jpeg->identifiers);
        $this->assertSame('a.jpg', $jpeg->originalName);
        $this->assertSame('derivation', $jpeg->relationships[0]->type);
        $this->assertSame('has source', $jpeg->relationships[0]->subType);
        $this->assertSame(['representations/rep1/data/a.tif'], $jpeg->relationships[0]->related);

        $event = $premis->events[0];
        $this->assertSame('creation', $event->type);
        $this->assertSame('ev-1', $event->identifier);
        $this->assertSame('thumbnails', $event->detail);
        $this->assertSame('success', $event->outcome);
        $this->assertSame(['representations/rep1/data/a.tif'], $event->linked('source'));
        $this->assertSame(['representations/rep2/data/a.jpg'], $event->linked('OUTCOME'));
        $this->assertSame('agate', $premis->agents[0]->name);
    }

    public function test_a_missing_premis_file_is_a_note(): void
    {
        $folder = $this->package();
        unlink($folder.'/metadata/preservation/premis.xml');
        $package = (new PackageReader)->read($folder);

        $premis = (new PremisReader)->read($package, $folder);

        $this->assertTrue($premis->isEmpty());
        $this->assertSame(['metadata/preservation/premis.xml is referenced as PREMIS but not in the package.'], $premis->notes);
    }

    public function test_a_broken_premis_file_is_a_note(): void
    {
        $folder = $this->package();
        file_put_contents($folder.'/metadata/preservation/premis.xml', '<premis');
        $package = (new PackageReader)->read($folder);

        $premis = (new PremisReader)->read($package, $folder);

        $this->assertTrue($premis->isEmpty());
        $this->assertStringContainsString('not well-formed', $premis->notes[0]);
    }

    public function test_premis_2_from_the_dilcis_corpus(): void
    {
        $root = dirname(__DIR__).'/corpus/corpus/CSIP/CSIP42/valid/valid_IP_with_SHOULD_MAY_1_rep';
        $package = (new PackageReader)->read($root);

        $premis = (new PremisReader)->read($package, $root);

        $this->assertContains('representations/rep1/metadata/preservation/rep1_preservation_meta_premis_v2-1.xml', $premis->files);
        $file = $premis->objects[array_key_first(array_filter($premis->objects, fn ($object) => $object->identifiers === ['loc.music/gottlieb.09601']))];
        $this->assertSame('file', $file->category);
        $this->assertSame('0001h.tif', $file->originalName);
        $this->assertSame(['amserver/'], $file->contentLocations);
        $derivations = array_values(array_filter($file->relationships, fn ($relationship) => $relationship->type === 'derivation'));
        $this->assertSame('is source of', $derivations[0]->subType);
        $this->assertStringEndsWith('0001v.jpg', $derivations[0]->related[0]);
        $this->assertContains('migration', array_map(fn ($event) => $event->type, $premis->events));
        $this->assertSame('jhove1_1e', $premis->events[0]->detail);
    }

    private function package(): string
    {
        $premis = (new PremisBuilder)
            ->agent('agate', 'agate', 'software', '1.0')
            ->file('representations/rep1/data/a.tif', 10, originalName: 'a.tif')
            ->file('representations/rep2/data/a.jpg', 10, originalName: 'a.jpg')
            ->relationship('representations/rep2/data/a.jpg', 'derivation', 'has source', 'representations/rep1/data/a.tif')
            ->event('ev-1', 'creation', new \DateTimeImmutable('2026-10-01T10:00:00Z'), 'thumbnails', 'success', ['agate' => 'executing program'], [
                'representations/rep1/data/a.tif' => 'source',
                'representations/rep2/data/a.jpg' => 'outcome',
            ]);
        $builder = PackageBuilder::sip('urn:uuid:5b2e7c1a-0000-4000-8000-000000000003')->submitter('Office')->premis($premis);
        $builder->representation('rep1', fn (RepresentationBuilder $r) => $r->file(new StringContent('tif', 'a.tif'), 'a.tif'));
        $builder->representation('rep2', fn (RepresentationBuilder $r) => $r->file(new StringContent('jpg', 'a.jpg'), 'a.jpg'));
        $target = $this->temporaryDirectory('out');

        return (new PackageWriter)->write($builder, $target);
    }
}
