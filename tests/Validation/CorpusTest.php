<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Validation;

use KraenzleRitter\EarkIp\Catalog\RequirementCatalog;
use KraenzleRitter\EarkIp\SpecificationVersion;
use KraenzleRitter\EarkIp\Tests\TestCase;
use KraenzleRitter\EarkIp\Validation\Outcome;
use KraenzleRitter\EarkIp\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use SimpleXMLElement;

/**
 * Runs the E-ARK IP test corpus of the DILCIS Board (fetch it with
 * `composer corpus`). Each test case targets one requirement: a package
 * under invalid/ must fail that requirement, one under valid/ must not.
 * Packages are only valid with regard to the requirement they test, so
 * nothing else is asserted.
 */
#[Group('corpus')]
final class CorpusTest extends TestCase
{
    /**
     * Cases where this validator knowingly decides differently, with the
     * reason. Key: requirement ID and package name.
     */
    private const EXCEPTIONS = [
        'CSIP17 IP_18006_CSIP17_1' => 'Listed as valid for rule 1 ("there should be a dmdSec"), but the package METS has no dmdSec; the SHOULD is reported as a warning.',
        'CSIP61 fileGrp_ADMID_incorrect_ref2' => 'The wrong reference is in the @ADMID of the Metadata division of the structMap, not of a fileGrp; it is reported under CSIP91.',
        'CSIP21 IP_18000_CSIP21_2' => 'Listed as valid for rule 2 ("the mdRef element SHOULD be there"), but its dmdSec has no mdRef; the SHOULD is reported as a warning.',
    ];

    /** @return iterable<string, array{string, string, bool}> */
    public static function cases(): iterable
    {
        $root = dirname(__DIR__).'/corpus/corpus';
        if (! is_dir($root)) {
            return;
        }
        $catalog = RequirementCatalog::for(SpecificationVersion::V2_2_0);

        foreach (glob($root.'/{CSIP,SIP,DIP}/*/testCase.xml', GLOB_BRACE) ?: [] as $file) {
            $case = simplexml_load_file($file);
            if (! $case instanceof SimpleXMLElement || (string) $case['testable'] !== 'TRUE') {
                continue;
            }
            $id = basename(dirname($file));
            if (! $catalog->has($id)) {
                continue;
            }
            foreach ($case->rules->rule ?? [] as $rule) {
                $level = (string) ($rule->error['level'] ?? '');
                if (! in_array($level, ['ERROR', 'WARNING'], true)) {
                    continue;
                }
                foreach ($rule->corpusPackages->package ?? [] as $package) {
                    if ((string) $package['isImplemented'] !== 'TRUE') {
                        continue;
                    }
                    $path = self::locate(dirname($file), (string) $package->path);
                    if ($path === null) {
                        continue;
                    }
                    $valid = (string) $package['isValid'] === 'TRUE';
                    $name = sprintf('%s rule %s %s %s', $id, (string) $rule['id'], $valid ? 'valid' : 'invalid', (string) $package['name']);
                    yield $name => [$id, $path, $valid];
                }
            }
        }
    }

    #[DataProvider('cases')]
    public function test_corpus_case(string $id, string $path, bool $valid): void
    {
        $key = $id.' '.basename($path, '.zip');
        if (isset(self::EXCEPTIONS[$key])) {
            $this->markTestSkipped(self::EXCEPTIONS[$key]);
        }

        $finding = (new Validator)->validate($path)->finding($id);
        $this->assertNotNull($finding, "No finding for {$id}");

        $messages = implode("\n", array_map(fn ($violation) => '  '.$violation->message, $finding->violations));
        if ($valid) {
            $this->assertNotSame(Outcome::Failed, $finding->outcome, "{$id} fails for a package that satisfies it:\n{$messages}");
        } else {
            $this->assertSame(Outcome::Failed, $finding->outcome, "{$id} is {$finding->outcome->value} for a package that violates it.");
        }
    }

    public function test_corpus_is_present(): void
    {
        if (! is_dir(dirname(__DIR__).'/corpus/corpus')) {
            $this->markTestSkipped('Fetch the corpus with `composer corpus`.');
        }
        $this->assertNotEmpty(iterator_to_array(self::cases()));
    }

    private static function locate(string $caseDirectory, string $path): ?string
    {
        $path = trim($path);
        foreach ([$caseDirectory.'/'.$path, $caseDirectory.'/'.$path.'.zip'] as $candidate) {
            if (is_dir($candidate) || is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
