<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

use KraenzleRitter\EarkIp\Catalog\Vocabulary;
use KraenzleRitter\EarkIp\Validation\MetsUnderTest;
use KraenzleRitter\EarkIp\Validation\RuleSet;
use KraenzleRitter\EarkIp\Validation\Severity;
use KraenzleRitter\EarkIp\Validation\ValidationContext;
use KraenzleRitter\EarkIp\Xml\Ns;

/** CSIP1–CSIP6: attributes of the mets root element, in every METS document. */
final class MetsRootRules implements RuleSet
{
    public function apply(ValidationContext $context): void
    {
        foreach ($context->documents as $mets) {
            $this->identifier($context, $mets);
            $this->contentCategory($context, $mets);
            $this->contentInformationType($context, $mets);
            $this->profile($context, $mets);
        }
    }

    private function identifier(ValidationContext $context, MetsUnderTest $mets): void
    {
        $root = $mets->root();
        if (! $context->expect('CSIP1', $mets->filled($root, 'OBJID'), 'mets/@OBJID is missing or empty.', $mets->at($root))) {
            return;
        }

        $objId = (string) $mets->attribute($root, 'OBJID');
        $expected = $mets->representation ?? $context->source->name();
        if (! Names::match($objId, $expected)) {
            $context->fail(
                'CSIP1',
                $mets->isPackageMets()
                    ? "mets/@OBJID \"{$objId}\" differs from the package root folder name \"{$expected}\"."
                    : "mets/@OBJID \"{$objId}\" differs from the representation folder name \"{$expected}\".",
                $mets->at($root),
                Severity::Warning,
            );
        }
    }

    private function contentCategory(ValidationContext $context, MetsUnderTest $mets): void
    {
        $root = $mets->root();
        $type = $mets->attribute($root, 'TYPE');
        $otherType = $mets->attribute($root, 'OTHERTYPE', Ns::CSIP);

        if (! $context->expect('CSIP2', trim((string) $type) !== '', 'mets/@TYPE is missing or empty.', $mets->at($root))) {
            return;
        }
        $type = (string) $type;
        $isOther = strcasecmp($type, 'Other') === 0;

        $context->expect(
            'CSIP2',
            Vocabulary::matches($context->version, Vocabulary::CONTENT_CATEGORY, $type),
            "mets/@TYPE \"{$type}\" is not a term of the Content Category vocabulary.",
            $mets->at($root),
        );
        if ($isOther) {
            $context->expect('CSIP2', trim((string) $otherType) !== '', 'mets/@TYPE is "Other" but mets/@csip:OTHERTYPE is missing or empty.', $mets->at($root));
        }

        if ($otherType !== null) {
            $context->expect('CSIP3', $isOther, 'mets/@csip:OTHERTYPE is given but mets/@TYPE is not "Other".', $mets->at($root));
            $context->expect(
                'CSIP3',
                ! Vocabulary::matches($context->version, Vocabulary::CONTENT_CATEGORY, $otherType),
                "mets/@csip:OTHERTYPE \"{$otherType}\" is a term of the Content Category vocabulary and belongs in mets/@TYPE.",
                $mets->at($root),
            );
        } elseif (! $isOther) {
            $context->notApplicable('CSIP3');
        }
    }

    private function contentInformationType(ValidationContext $context, MetsUnderTest $mets): void
    {
        $root = $mets->root();
        $type = $mets->attribute($root, 'CONTENTINFORMATIONTYPE', Ns::CSIP);
        $other = $mets->attribute($root, 'OTHERCONTENTINFORMATIONTYPE', Ns::CSIP);

        if ($type === null) {
            $context->fail(
                'CSIP4',
                $mets->isPackageMets()
                    ? 'mets/@csip:CONTENTINFORMATIONTYPE is missing.'
                    : 'mets/@csip:CONTENTINFORMATIONTYPE is missing; a representation METS must state it.',
                $mets->at($root),
                $mets->isPackageMets() ? null : Severity::Error,
            );
        } else {
            $context->expect(
                'CSIP4',
                Vocabulary::matches($context->version, Vocabulary::CONTENT_INFORMATION_TYPE, $type),
                "mets/@csip:CONTENTINFORMATIONTYPE \"{$type}\" is not a term of the Content Information Type vocabulary.",
                $mets->at($root),
                Severity::Error,
            );
        }

        ContentInformationType::checkOther($context, 'CSIP5', $mets, $root, $type, $other, 'mets');
        if ($type === null && $other === null) {
            $context->notApplicable('CSIP5');
        }
    }

    private function profile(ValidationContext $context, MetsUnderTest $mets): void
    {
        $profile = trim((string) $mets->attribute($mets->root(), 'PROFILE'));

        $context->expect(
            'CSIP6',
            preg_match('#^https?://\S+$#i', $profile) === 1,
            $profile === '' ? 'mets/@PROFILE is missing or empty.' : "mets/@PROFILE \"{$profile}\" is not a URL.",
            $mets->at($mets->root()),
        );
    }
}
