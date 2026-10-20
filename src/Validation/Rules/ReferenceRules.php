<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

use KraenzleRitter\EarkIp\Validation\Location;
use KraenzleRitter\EarkIp\Validation\RuleSet;
use KraenzleRitter\EarkIp\Validation\ValidationContext;

/**
 * EARKIP-UNREFERENCED: every file is referenced from some METS document.
 * Runs after the rules that resolve references. Representation METS
 * files count as referenced when the package METS points to them.
 */
final class ReferenceRules implements RuleSet
{
    public function apply(ValidationContext $context): void
    {
        $context->checked('EARKIP-UNREFERENCED');
        $nested = $this->nestedPackages($context);
        foreach ($context->source->files() as $file) {
            if ($file === 'METS.xml' || $context->isReferenced($file)) {
                continue;
            }
            foreach ($nested as $folder) {
                if (str_starts_with($file, $folder)) {
                    continue 2;
                }
            }
            $context->fail('EARKIP-UNREFERENCED', "{$file} is not referenced from any METS document.", new Location($file));
        }
    }

    /**
     * Folders of packages the package METS points to with an mptr outside
     * representations/, e.g. the SIP an AIP keeps under submission/. They
     * describe themselves.
     *
     * @return list<string>
     */
    private function nestedPackages(ValidationContext $context): array
    {
        $mets = $context->packageMets();
        if ($mets === null) {
            return [];
        }
        $folders = [];
        foreach ($mets->model->structMaps as $structMap) {
            foreach ($structMap->pointers() as $pointer) {
                $path = $mets->model->resolve($pointer->href);
                if ($path !== null && str_ends_with($path, '/METS.xml') && ! str_starts_with($path, 'representations/') && $context->source->hasFile($path)) {
                    $context->markReferenced($path);
                    $folders[] = substr($path, 0, -strlen('METS.xml'));
                }
            }
        }

        return $folders;
    }
}
