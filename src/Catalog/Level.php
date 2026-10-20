<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Catalog;

/** Requirement levels as the profiles use them (RFC 2119). */
enum Level: string
{
    case MUST = 'MUST';
    case SHOULD = 'SHOULD';
    case MAY = 'MAY';
}
