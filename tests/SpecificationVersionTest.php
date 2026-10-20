<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests;

use KraenzleRitter\EarkIp\Exception\UnsupportedVersionException;
use KraenzleRitter\EarkIp\SpecificationVersion;

final class SpecificationVersionTest extends TestCase
{
    public function test_default_is_2_2_0(): void
    {
        $this->assertSame(SpecificationVersion::V2_2_0, SpecificationVersion::default());
    }

    public function test_from_string(): void
    {
        $this->assertSame(SpecificationVersion::V2_1_0, SpecificationVersion::fromString('2.1.0'));
    }

    public function test_unsupported_version_names_the_supported_ones(): void
    {
        $this->expectException(UnsupportedVersionException::class);
        $this->expectExceptionMessage('E-ARK version 2.0.4 is not supported. Supported versions: 2.1.0, 2.2.0.');

        SpecificationVersion::fromString('2.0.4');
    }

    public function test_file_suffix(): void
    {
        $this->assertSame('v2-2-0', SpecificationVersion::V2_2_0->fileSuffix());
    }
}
