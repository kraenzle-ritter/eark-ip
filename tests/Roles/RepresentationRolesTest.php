<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Roles;

use KraenzleRitter\EarkIp\Model\Package;
use KraenzleRitter\EarkIp\Model\Premis;
use KraenzleRitter\EarkIp\Model\RepresentationRole;
use KraenzleRitter\EarkIp\Reader\PackageReader;
use KraenzleRitter\EarkIp\Reader\PremisReader;
use KraenzleRitter\EarkIp\RepresentationFunction;
use KraenzleRitter\EarkIp\RepresentationRoles;
use KraenzleRitter\EarkIp\Tests\TestCase;
use KraenzleRitter\EarkIp\Writer\PackageBuilder;
use KraenzleRitter\EarkIp\Writer\PackageWriter;
use KraenzleRitter\EarkIp\Writer\PremisBuilder;
use KraenzleRitter\EarkIp\Writer\RepresentationBuilder;
use KraenzleRitter\EarkIp\Writer\StringContent;
use PHPUnit\Framework\Attributes\DataProvider;

final class RepresentationRolesTest extends TestCase
{
    public function test_a_normalization_makes_its_source_the_original_and_its_outcome_the_master(): void
    {
        $premis = $this->files(['rep1' => 'a.tif', 'rep2' => 'a.tif'])
            ->event('ev-1', 'normalization', new \DateTimeImmutable('2026-10-01T10:00:00Z'), objects: [
                'representations/rep2/data/a.tif' => 'source',
                'representations/rep1/data/a.tif' => 'outcome',
            ]);

        $roles = $this->propose(['rep1' => ['a.tif'], 'rep2' => ['a.tif']], $premis);

        $this->assertRole('rep1', RepresentationFunction::Master, RepresentationRole::BASIS_EVENT, $roles);
        $this->assertRole('rep2', RepresentationFunction::Original, RepresentationRole::BASIS_EVENT, $roles);
        $this->assertTrue($roles['rep1']->derived);
        $this->assertSame('rep2', $roles['rep1']->derivedFrom);
    }

    public function test_a_migration_event_type_is_read_without_regard_to_case(): void
    {
        $premis = $this->files(['rep1' => 'a.doc', 'rep2' => 'a.pdf'])
            ->event('ev-1', 'Migration', new \DateTimeImmutable('2026-10-01T10:00:00Z'), objects: [
                'representations/rep1/data/a.doc' => 'source',
                'representations/rep2/data/a.pdf' => 'outcome',
            ]);

        $roles = $this->propose(['rep1' => ['a.doc'], 'rep2' => ['a.pdf']], $premis);

        $this->assertRole('rep1', RepresentationFunction::Original, RepresentationRole::BASIS_EVENT, $roles);
        $this->assertRole('rep2', RepresentationFunction::Master, RepresentationRole::BASIS_EVENT, $roles);
    }

    public function test_a_derivation_makes_the_source_the_master_and_the_derived_the_access_copy(): void
    {
        $premis = $this->files(['rep1' => 'a.tif', 'rep2' => 'a.jpg'])
            ->relationship('representations/rep2/data/a.jpg', 'derivation', 'has source', 'representations/rep1/data/a.tif');

        $roles = $this->propose(['rep1' => ['a.tif'], 'rep2' => ['a.jpg']], $premis);

        $this->assertRole('rep1', RepresentationFunction::Master, RepresentationRole::BASIS_RELATIONSHIP, $roles);
        $this->assertRole('rep2', RepresentationFunction::Access, RepresentationRole::BASIS_RELATIONSHIP, $roles);
    }

    public function test_is_source_of_points_the_other_way(): void
    {
        $premis = $this->files(['rep1' => 'a.tif', 'rep2' => 'a.jpg'])
            ->relationship('representations/rep1/data/a.tif', 'derivation', 'is source of', 'representations/rep2/data/a.jpg');

        $roles = $this->propose(['rep1' => ['a.tif'], 'rep2' => ['a.jpg']], $premis);

        $this->assertRole('rep1', RepresentationFunction::Master, RepresentationRole::BASIS_RELATIONSHIP, $roles);
        $this->assertRole('rep2', RepresentationFunction::Access, RepresentationRole::BASIS_RELATIONSHIP, $roles);
    }

    public function test_a_chain_of_three_relationships_between_representation_objects(): void
    {
        // The layout agate writes: rep1 master, rep2 original, rep3 access.
        $premis = (new PremisBuilder)
            ->representation('rep1')->representation('rep2')->representation('rep3')
            ->relationship('rep1', 'derivation', 'has source', 'rep2')
            ->relationship('rep3', 'derivation', 'has source', 'rep1');

        $roles = $this->propose(['rep1' => ['a.tif'], 'rep2' => ['a.tif'], 'rep3' => ['a.jpg']], $premis);

        $this->assertRole('rep1', RepresentationFunction::Master, RepresentationRole::BASIS_RELATIONSHIP, $roles);
        $this->assertRole('rep2', RepresentationFunction::Original, RepresentationRole::BASIS_RELATIONSHIP, $roles);
        $this->assertRole('rep3', RepresentationFunction::Access, RepresentationRole::BASIS_RELATIONSHIP, $roles);
    }

    public function test_an_event_and_a_relationship_together(): void
    {
        $premis = $this->files(['rep1' => 'a.tif', 'rep2' => 'a.tif', 'rep3' => 'a.jpg'])
            ->event('ev-1', 'normalization', new \DateTimeImmutable('2026-10-01T10:00:00Z'), objects: [
                'representations/rep2/data/a.tif' => 'source',
                'representations/rep1/data/a.tif' => 'outcome',
            ])
            ->relationship('representations/rep3/data/a.jpg', 'derivation', 'has source', 'representations/rep1/data/a.tif');

        $roles = $this->propose(['rep1' => ['a.tif'], 'rep2' => ['a.tif'], 'rep3' => ['a.jpg']], $premis);

        $this->assertRole('rep1', RepresentationFunction::Master, RepresentationRole::BASIS_EVENT, $roles);
        $this->assertRole('rep2', RepresentationFunction::Original, RepresentationRole::BASIS_EVENT, $roles);
        $this->assertRole('rep3', RepresentationFunction::Access, RepresentationRole::BASIS_RELATIONSHIP, $roles);
    }

    public function test_names_when_premis_says_nothing(): void
    {
        $roles = $this->propose(['preservation' => ['a.tif'], 'access' => ['a.jpg'], 'Original_Lieferung' => ['a.tif']]);

        $this->assertRole('preservation', RepresentationFunction::Master, RepresentationRole::BASIS_NAME, $roles);
        $this->assertRole('access', RepresentationFunction::Access, RepresentationRole::BASIS_NAME, $roles);
        $this->assertRole('Original_Lieferung', RepresentationFunction::Original, RepresentationRole::BASIS_NAME, $roles);
    }

    public function test_without_premis_and_names_the_first_is_the_master(): void
    {
        $roles = $this->propose(['rep1' => ['a.tif'], 'rep2' => ['a.jpg']]);

        $this->assertRole('rep1', RepresentationFunction::Master, RepresentationRole::BASIS_DEFAULT, $roles);
        $this->assertRole('rep2', RepresentationFunction::Unknown, RepresentationRole::BASIS_DEFAULT, $roles);
        $this->assertFalse($roles['rep2']->derived);
    }

    public function test_the_first_is_counted_in_natural_order(): void
    {
        $roles = $this->propose(['rep10' => ['a.txt'], 'rep2' => ['a.txt']]);

        $this->assertSame(['rep2', 'rep10'], array_keys($roles));
        $this->assertSame(RepresentationFunction::Master, $roles['rep2']->function);
    }

    public function test_only_one_master_and_one_access_copy(): void
    {
        $roles = $this->propose(['master-a' => ['a.tif'], 'master-b' => ['a.tif'], 'web' => ['a.jpg'], 'web-small' => ['a.jpg']]);

        $this->assertSame(RepresentationFunction::Master, $roles['master-a']->function);
        $this->assertSame(RepresentationFunction::Unknown, $roles['master-b']->function);
        $this->assertStringContainsString('only one is allowed', $roles['master-b']->reason);
        $this->assertSame(RepresentationFunction::Access, $roles['web']->function);
        $this->assertSame(RepresentationFunction::Unknown, $roles['web-small']->function);
    }

    public function test_each_reason_comes_as_a_code_with_its_values(): void
    {
        $premis = $this->files(['rep1' => 'a.tif', 'rep2' => 'a.tif'])
            ->event('ev-1', 'normalization', new \DateTimeImmutable('2026-10-01T10:00:00Z'), objects: [
                'representations/rep2/data/a.tif' => 'source',
                'representations/rep1/data/a.tif' => 'outcome',
            ]);

        $roles = $this->propose(['rep1' => ['a.tif'], 'rep2' => ['a.tif'], 'rep3' => ['a.txt']], $premis);

        $this->assertSame([RepresentationRole::REASON_MIGRATION_OUTCOME, ['source' => 'rep2']], [$roles['rep1']->reasonCode, $roles['rep1']->reasonParams]);
        $this->assertSame([RepresentationRole::REASON_MIGRATION_SOURCE, ['outcome' => 'rep1']], [$roles['rep2']->reasonCode, $roles['rep2']->reasonParams]);
        $this->assertSame([RepresentationRole::REASON_UNSPECIFIED, []], [$roles['rep3']->reasonCode, $roles['rep3']->reasonParams]);
        $this->assertSame('outcome of a migration or normalization of files in rep2', $roles['rep1']->reason);
        $this->assertSame(
            ['reason_code' => RepresentationRole::REASON_MIGRATION_OUTCOME, 'reason_params' => ['source' => 'rep2']],
            array_intersect_key($roles['rep1']->toArray(), ['reason_code' => true, 'reason_params' => true])
        );
    }

    public function test_a_further_master_carries_the_reason_it_would_have_had(): void
    {
        $roles = $this->propose(['master-a' => ['a.tif'], 'master-b' => ['a.tif']]);

        $this->assertSame(RepresentationRole::REASON_FURTHER_MASTER, $roles['master-b']->reasonCode);
        $this->assertSame(
            ['first' => 'master-a', 'previous' => ['code' => RepresentationRole::REASON_NAMED, 'params' => ['name' => 'master-b']]],
            $roles['master-b']->reasonParams
        );
        $this->assertSame('a further master after master-a; only one is allowed (named master-b)', $roles['master-b']->reason);
    }

    /**
     * The English texts are those of 0.2.0, so a reader of `reason` sees no change.
     *
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    public static function reasonTexts(): array
    {
        return [
            'migration source' => [RepresentationRole::REASON_MIGRATION_SOURCE, ['outcome' => 'rep2'], 'source of a migration or normalization whose outcome is in rep2'],
            'migration outcome' => [RepresentationRole::REASON_MIGRATION_OUTCOME, ['source' => 'rep1'], 'outcome of a migration or normalization of files in rep1'],
            'chain step' => [RepresentationRole::REASON_CHAIN_STEP, ['step' => 2], 'step 2 of a chain of derivations'],
            'derived from original' => [RepresentationRole::REASON_DERIVED_FROM_ORIGINAL, ['original' => 'rep1'], 'derived from the original in rep1'],
            'source of master' => [RepresentationRole::REASON_SOURCE_OF_MASTER, ['master' => 'rep2'], 'source of the master in rep2'],
            'source of derived' => [RepresentationRole::REASON_SOURCE_OF_DERIVED, ['derived' => 'rep2'], 'source of files derived in rep2'],
            'derived from' => [RepresentationRole::REASON_DERIVED_FROM, ['source' => 'rep1'], 'derived from files in rep1'],
            'named' => [RepresentationRole::REASON_NAMED, ['name' => 'web'], 'named web'],
            'further access' => [RepresentationRole::REASON_FURTHER_ACCESS, ['first' => 'web', 'previous' => ['code' => 'named', 'params' => ['name' => 'web-small']]], 'a further access copy after web; only one is allowed (named web-small)'],
            'first representation' => [RepresentationRole::REASON_FIRST_REPRESENTATION, [], 'first representation; nothing names a master'],
            'unspecified' => [RepresentationRole::REASON_UNSPECIFIED, [], 'nothing says what it is for'],
        ];
    }

    /** @param  array<string, mixed>  $params */
    #[DataProvider('reasonTexts')]
    public function test_the_english_text_of_a_reason(string $code, array $params, string $text): void
    {
        $this->assertSame($text, RepresentationRole::reasonText($code, $params));
    }

    public function test_a_master_is_found_when_only_original_and_access_are_named(): void
    {
        $roles = $this->propose(['original' => ['a.tif'], 'access' => ['a.jpg']]);

        $this->assertRole('access', RepresentationFunction::Master, RepresentationRole::BASIS_DEFAULT, $roles);
        $this->assertSame(RepresentationFunction::Original, $roles['original']->function);
    }

    public function test_a_single_representation_derived_from_outside_the_package(): void
    {
        $premis = $this->files(['rep1' => 'a.jpg'])
            ->relationship('representations/rep1/data/a.jpg', 'derivation', 'has source', 'urn:archive:master/a.tif');

        $roles = $this->propose(['rep1' => ['a.jpg']], $premis);

        $this->assertRole('rep1', RepresentationFunction::Master, RepresentationRole::BASIS_DEFAULT, $roles);
        $this->assertTrue($roles['rep1']->derived);
        $this->assertNull($roles['rep1']->derivedFrom);
    }

    public function test_objects_identified_otherwise_are_placed_by_their_original_name_path(): void
    {
        // Identifiers that are no paths; the original name tells where the file is.
        $premis = (new PremisBuilder)
            ->file('obj-1', originalName: 'representations/rep1/data/a.tif')
            ->file('obj-2', originalName: 'representations/rep2/data/a.jpg')
            ->relationship('obj-2', 'derivation', 'has source', 'obj-1');

        $roles = $this->propose(['rep1' => ['a.tif'], 'rep2' => ['a.jpg']], $premis);

        $this->assertSame(RepresentationFunction::Master, $roles['rep1']->function);
        $this->assertSame(RepresentationFunction::Access, $roles['rep2']->function);
    }

    public function test_the_package_from_the_dilcis_corpus_with_premis_2(): void
    {
        $root = self::corpus('CSIP/CSIP42/valid/valid_IP_with_SHOULD_MAY_1_rep');
        $package = (new PackageReader)->read($root);
        $premis = (new PremisReader)->read($package, $root);

        $this->assertNotSame([], $premis->objects);
        $this->assertSame([], $premis->notes);
        $roles = RepresentationRoles::propose($package, $premis);
        $this->assertCount(1, $roles);
        $this->assertSame(RepresentationFunction::Master, $roles[0]->function);
        $this->assertFalse($roles[0]->derived, 'rep1 is the source of files outside the package, not derived');
    }

    public function test_no_representations_no_roles(): void
    {
        $package = (new PackageReader)->read(self::corpus('CSIP/CSIP42/valid/valid_IP_with_SHOULD_MAY_1_rep'));
        $empty = new Package($package->name, $package->mets, $package->type, $package->version, true);

        $this->assertSame([], RepresentationRoles::propose($empty, new Premis));
    }

    /**
     * Writes a package with these representations (name => file paths)
     * and PREMIS, reads it back and proposes.
     *
     * @param  array<string, list<string>>  $representations
     * @return array<string, RepresentationRole>
     */
    private function propose(array $representations, ?PremisBuilder $premis = null): array
    {
        $builder = PackageBuilder::sip('urn:uuid:5b2e7c1a-0000-4000-8000-000000000002')->submitter('Office');
        if ($premis !== null) {
            $builder->premis($premis);
        }
        foreach ($representations as $name => $files) {
            $builder->representation($name, function (RepresentationBuilder $representation) use ($files): void {
                foreach ($files as $path) {
                    $representation->file(new StringContent('content of '.$path, basename($path)), $path);
                }
            });
        }
        $zip = (new PackageWriter)->write($builder, $this->temporaryFile());

        $package = (new PackageReader)->read($zip);
        $roles = RepresentationRoles::propose($package, (new PremisReader)->read($package, $zip));

        return array_combine(array_map(fn (RepresentationRole $role) => $role->name, $roles), $roles);
    }

    /** @param  array<string, string>  $files  representation => file below data/ */
    private function files(array $files): PremisBuilder
    {
        $premis = new PremisBuilder;
        foreach ($files as $representation => $path) {
            $premis->file("representations/{$representation}/data/{$path}", 10);
        }

        return $premis;
    }

    /** @param  array<string, RepresentationRole>  $roles */
    private function assertRole(string $name, RepresentationFunction $function, string $basis, array $roles): void
    {
        $this->assertArrayHasKey($name, $roles);
        $this->assertSame($function, $roles[$name]->function, "{$name}: {$roles[$name]->reason}");
        $this->assertSame($basis, $roles[$name]->basis, "{$name}: {$roles[$name]->reason}");
    }

    private static function corpus(string $path): string
    {
        return dirname(__DIR__).'/corpus/corpus/'.$path;
    }
}
