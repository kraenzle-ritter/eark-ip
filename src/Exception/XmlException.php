<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Exception;

/**
 * An XML document could not be parsed, or was refused because it
 * declares a DOCTYPE.
 */
final class XmlException extends EarkIpException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($errors === [] ? $message : $message.' '.implode(' ', $errors));
    }
}
