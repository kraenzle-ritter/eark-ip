<?php

/**
 * Builds resources/spec/<version>/requirements.json from the METS
 * profiles of the DILCIS Board. Run after updating a profile:
 *
 *     php tools/build-catalog.php
 */

declare(strict_types=1);

const PROFILE = 'http://www.loc.gov/METS_Profile/v2';
const XHTML = 'http://www.w3.org/1999/xhtml';

$root = dirname(__DIR__).'/resources';

foreach (['2.1.0', '2.2.0'] as $version) {
    $requirements = [];

    foreach (glob("{$root}/spec/{$version}/profiles/E-ARK-*.xml") ?: [] as $file) {
        preg_match('/E-ARK-(CSIP|SIP|AIP|DIP)-/', basename($file), $match);
        array_push($requirements, ...requirementsFrom($file, $match[1]));
    }

    usort($requirements, fn (array $a, array $b) => [order($a['specification']), $a['number']] <=> [order($b['specification']), $b['number']]);
    $requirements = array_map(function (array $requirement) {
        unset($requirement['number']);

        return $requirement;
    }, $requirements);

    $target = "{$root}/spec/{$version}/requirements.json";
    file_put_contents($target, json_encode($requirements, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    echo sprintf("%s: %d requirements\n", $target, count($requirements));
}

/**
 * @return list<array<string, mixed>>
 */
function requirementsFrom(string $file, string $specification): array
{
    $document = new DOMDocument;
    $document->load($file, LIBXML_NONET);
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('p', PROFILE);
    $xpath->registerNamespace('h', XHTML);

    $requirements = [];
    foreach ($xpath->query('//p:requirement[@ID]') ?: [] as $node) {
        assert($node instanceof DOMElement);
        $id = $node->getAttribute('ID');

        // REF_* entries only point back to CSIP or METS; they carry no test.
        if (! preg_match('/^(CSIP|SIP|AIPM|DIP)(\d+)$/', $id, $match)) {
            continue;
        }

        $requirements[] = [
            'id' => $id,
            'specification' => $specification,
            'level' => $node->getAttribute('REQLEVEL'),
            'name' => text($xpath, 'p:description/p:head', $node),
            'location' => definition($xpath, $node, 'METS XPath'),
            'cardinality' => definition($xpath, $node, 'Cardinality'),
            'description' => text($xpath, 'p:description/h:p', $node),
            'number' => (int) $match[2],
        ];
    }

    return $requirements;
}

function text(DOMXPath $xpath, string $query, DOMNode $context): string
{
    $parts = [];
    foreach ($xpath->query($query, $context) ?: [] as $node) {
        $parts[] = trim((string) preg_replace('/\s+/', ' ', $node->textContent));
    }

    return implode(' ', $parts);
}

function definition(DOMXPath $xpath, DOMNode $context, string $term): ?string
{
    $value = text($xpath, "p:description/h:dl/h:dt[normalize-space(.) = '{$term}']/following-sibling::h:dd[1]", $context);

    return $value === '' ? null : $value;
}

function order(string $specification): int
{
    return array_search($specification, ['CSIP', 'SIP', 'AIP', 'DIP'], true) ?: 0;
}
