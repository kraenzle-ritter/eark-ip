<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** mets/metsHdr. */
final class MetsHeader
{
    /**
     * @param  list<Agent>  $agents
     * @param  list<AltRecordId>  $altRecordIds
     */
    public function __construct(
        public readonly ?string $createDate,
        public readonly ?string $oaisPackageType,
        public readonly array $agents = [],
        public readonly ?string $lastModDate = null,
        public readonly ?string $recordStatus = null,
        public readonly array $altRecordIds = [],
    ) {}

    /** @return list<Agent> */
    public function agentsWithRole(string $role): array
    {
        return array_values(array_filter($this->agents, fn (Agent $agent) => $agent->role === $role));
    }

    public function altRecordId(string $type): ?string
    {
        foreach ($this->altRecordIds as $id) {
            if ($id->type === $type) {
                return $id->value;
            }
        }

        return null;
    }
}
