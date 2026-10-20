<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Catalog;

use KraenzleRitter\EarkIp\Resources;
use KraenzleRitter\EarkIp\SpecificationVersion;
use KraenzleRitter\EarkIp\Xml\Xml;

/**
 * The controlled vocabularies of the DILCIS Board, e.g. Content
 * Category or OAIS Package Type, read from the shipped XML files.
 */
final class Vocabulary
{
    public const CONTENT_CATEGORY = 'CSIPVocabularyContentCategory';

    public const CONTENT_INFORMATION_TYPE = 'CSIPVocabularyContentInformationType';

    public const OAIS_PACKAGE_TYPE = 'CSIPVocabularyOAISPackageType';

    public const STATUS = 'CSIPVocabularyStatus';

    public const NOTE_TYPE = 'CSIPVocabularyNoteType';

    public const AGENT_OTHER_TYPE = 'CSIPVocabularyAgentOtherType';

    public const RECORD_STATUS = 'SIPVocabularyRecordStatus';

    public const RECORD_ID_TYPE = 'SIPVocabularyRecordIDType';

    /** @var array<string, list<string>> */
    private static array $terms = [];

    /** @return list<string> */
    public static function terms(SpecificationVersion $version, string $vocabulary): array
    {
        return self::$terms[$version->value.'/'.$vocabulary] ??= self::load($version, $vocabulary);
    }

    public static function contains(SpecificationVersion $version, string $vocabulary, string $term): bool
    {
        return in_array($term, self::terms($version, $vocabulary), true);
    }

    /**
     * Lenient match for values people type by hand: case-insensitive and
     * with en and em dashes treated as hyphens, so "Textual works -
     * Digital" and "OTHER" count as the terms "Textual works – Digital"
     * and "Other".
     */
    public static function matches(SpecificationVersion $version, string $vocabulary, string $value): bool
    {
        $normalize = fn (string $term) => mb_strtolower(trim(str_replace(['–', '—'], '-', $term)));
        $needle = $normalize($value);
        foreach (self::terms($version, $vocabulary) as $term) {
            if ($normalize($term) === $needle) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function load(SpecificationVersion $version, string $vocabulary): array
    {
        $document = Xml::load(
            (string) file_get_contents(Resources::specPath($version, "vocabularies/{$vocabulary}.xml")),
            $vocabulary,
        );

        $terms = [];
        foreach (Xml::xpath($document)->query('//voc:Entry/voc:Term') ?: [] as $term) {
            $terms[] = trim((string) $term->nodeValue);
        }

        return $terms;
    }
}
