<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

use DOMElement;
use KraenzleRitter\EarkIp\Catalog\Vocabulary;
use KraenzleRitter\EarkIp\Validation\MetsUnderTest;
use KraenzleRitter\EarkIp\Validation\Severity;
use KraenzleRitter\EarkIp\Validation\ValidationContext;

/** The OTHER/OTHERCONTENTINFORMATIONTYPE pairing, shared by CSIP5 (mets) and CSIP63 (fileGrp). */
final class ContentInformationType
{
    public static function checkOther(
        ValidationContext $context,
        string $id,
        MetsUnderTest $mets,
        DOMElement $element,
        ?string $type,
        ?string $other,
        string $path,
    ): void {
        if ($type === 'OTHER') {
            $context->expect(
                $id,
                trim((string) $other) !== '',
                "{$path}/@csip:CONTENTINFORMATIONTYPE is \"OTHER\" but {$path}/@csip:OTHERCONTENTINFORMATIONTYPE is missing or empty.",
                $mets->at($element),
                Severity::Error,
            );
        }
        if ($other === null) {
            return;
        }
        $context->expect(
            $id,
            $type === 'OTHER',
            "{$path}/@csip:OTHERCONTENTINFORMATIONTYPE is given but {$path}/@csip:CONTENTINFORMATIONTYPE is not \"OTHER\".",
            $mets->at($element),
            Severity::Error,
        );
        $context->expect(
            $id,
            ! Vocabulary::matches($context->version, Vocabulary::CONTENT_INFORMATION_TYPE, $other),
            "{$path}/@csip:OTHERCONTENTINFORMATIONTYPE \"{$other}\" is a term of the vocabulary and belongs in @csip:CONTENTINFORMATIONTYPE.",
            $mets->at($element),
            Severity::Error,
        );
    }
}
