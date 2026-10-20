<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

use KraenzleRitter\EarkIp\Reader\PackageReader;
use KraenzleRitter\EarkIp\Source\PackageSource;
use KraenzleRitter\EarkIp\Source\Sources;

/**
 * Builds an AIP from a SIP: the SIP's metadata and representations,
 * PREMIS 3 with the ingest event (AIPM5–AIPM7), and by default the SIP
 * itself under submission/, as the AIP examples of the specification
 * keep it.
 */
final class AipBuilder
{
    public static function fromSip(
        string|PackageSource $sip,
        string $aipId,
        bool $keepSubmission = true,
        ?string $parentAip = null,
        string $agentName = 'kraenzle-ritter/eark-ip',
        ?string $agentVersion = null,
        ?\DateTimeImmutable $ingested = null,
    ): PackageBuilder {
        $source = Sources::open($sip);
        $package = (new PackageReader)->read($source);
        $ingested ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $aip = PackageBuilder::aip($aipId);
        Derivation::copy($package, $source, $aip);
        if ($keepSubmission) {
            $aip->submission = $source;
        }
        if ($parentAip !== null) {
            $aip->parentAip($parentAip);
        }

        $sipId = $package->objId() ?? $package->name;
        $agentVersion ??= $aip->software->note('SOFTWARE VERSION') ?? 'dev';
        $premis = (new PremisBuilder)
            ->intellectualEntity($aipId)
            ->intellectualEntity($sipId)
            ->agent($agentName, $agentName, 'software', $agentVersion)
            ->event(
                'ingestion-'.$aipId,
                'ingestion',
                $ingested,
                "AIP {$aipId} created from SIP {$sipId}.",
                'success',
                [$agentName => 'executing program'],
                [$sipId => 'source', $aipId => 'outcome'],
            );
        $aip->premis($premis, 'aip-premis.xml');

        return $aip;
    }
}
