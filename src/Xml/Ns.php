<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Xml;

/**
 * Namespaces used in E-ARK packages, with the prefixes this package
 * registers for XPath.
 */
final class Ns
{
    public const METS = 'http://www.loc.gov/METS/';

    public const CSIP = 'https://DILCIS.eu/XML/METS/CSIPExtensionMETS';

    public const SIP = 'https://DILCIS.eu/XML/METS/SIPExtensionMETS';

    public const XLINK = 'http://www.w3.org/1999/xlink';

    public const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public const PREMIS = 'http://www.loc.gov/premis/v3';

    public const VOCABULARY = 'https://DILCIS.eu/XML/Vocabularies/IP';

    /** @return array<string, string> prefix => namespace */
    public static function prefixes(): array
    {
        return [
            'mets' => self::METS,
            'csip' => self::CSIP,
            'sip' => self::SIP,
            'xlink' => self::XLINK,
            'xsi' => self::XSI,
            'premis' => self::PREMIS,
            'voc' => self::VOCABULARY,
        ];
    }
}
