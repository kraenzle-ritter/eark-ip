<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Source;

use KraenzleRitter\EarkIp\Source\Path;
use KraenzleRitter\EarkIp\Tests\TestCase;

final class PathTest extends TestCase
{
    public function test_normalize(): void
    {
        $this->assertSame('representations/rep1/data/a.txt', Path::normalize('./representations//rep1/./data/a.txt'));
        $this->assertSame('a/b', Path::normalize('a\\b'));
    }

    public function test_paths_that_leave_the_package_are_refused(): void
    {
        $this->assertNull(Path::normalize('../etc/passwd'));
        $this->assertNull(Path::normalize('a/../../b'));
        $this->assertNull(Path::normalize('/etc/passwd'));
        $this->assertNull(Path::normalize('C:/Windows'));
        $this->assertNull(Path::normalize(''));
    }

    public function test_href_relative_to_the_mets_folder(): void
    {
        $this->assertSame(['representations/rep1/data/a.txt'], Path::fromHref('./data/a.txt', 'representations/rep1'));
        $this->assertSame(['documentation/Doc1.txt'], Path::fromHref('documentation/Doc1.txt'));
    }

    public function test_href_with_percent_encoding_or_file_scheme(): void
    {
        $this->assertSame(['data/a b.txt'], Path::fromHref('file:data/a b.txt'));
        $this->assertSame(['data/a%20b.txt', 'data/a b.txt'], Path::fromHref('data/a%20b.txt'));
    }

    public function test_external_hrefs_have_no_package_path(): void
    {
        $this->assertSame([], Path::fromHref('https://example.org/a.xml'));
        $this->assertSame([], Path::fromHref('urn:uuid:3a487ce5-63cf-4000-9522-7288e208e2bc'));
        $this->assertSame([], Path::fromHref('file:///etc/passwd'));
        $this->assertSame([], Path::fromHref('../outside.txt'));
    }

    public function test_to_href(): void
    {
        $this->assertSame('data/a%20b.txt', Path::toHref('data/a b.txt'));
    }
}
