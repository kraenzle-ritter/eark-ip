<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Source;

use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\Exception\UnsafePackageException;
use KraenzleRitter\EarkIp\Source\Limits;
use KraenzleRitter\EarkIp\Source\Sources;
use KraenzleRitter\EarkIp\Source\ZipSource;
use KraenzleRitter\EarkIp\Tests\TestCase;
use ZipArchive;

final class ZipSourceTest extends TestCase
{
    public function test_single_root_folder_is_the_package_root(): void
    {
        $source = new ZipSource($this->zip([
            'pkg-1/METS.xml' => '<mets/>',
            'pkg-1/representations/rep1/data/a.txt' => 'abc',
            '__MACOSX/pkg-1/._METS.xml' => 'x',
        ]));

        $this->assertSame('pkg-1', $source->name());
        $this->assertTrue($source->hasSingleRootFolder());
        $this->assertSame(['METS.xml', 'representations/rep1/data/a.txt'], $source->files());
        $this->assertSame(['representations', 'representations/rep1', 'representations/rep1/data'], $source->directories());
        $this->assertSame(3, $source->size('representations/rep1/data/a.txt'));
        $this->assertSame(hash('md5', 'abc'), $source->hash('representations/rep1/data/a.txt', 'md5'));
        $this->assertSame('<mets/>', $source->contents('METS.xml'));
    }

    public function test_without_root_folder(): void
    {
        $path = $this->zip(['METS.xml' => '<mets/>', 'documentation/' => '']);

        $source = new ZipSource($path);

        $this->assertFalse($source->hasSingleRootFolder());
        $this->assertSame(pathinfo($path, PATHINFO_FILENAME), $source->name());
        $this->assertSame(['METS.xml'], $source->files());
        $this->assertSame(['documentation'], $source->directories());
    }

    public function test_entry_outside_the_package_is_refused(): void
    {
        $path = $this->zip(['pkg/METS.xml' => '<mets/>', '../../evil.txt' => 'x']);

        $this->expectException(UnsafePackageException::class);
        $this->expectExceptionMessage('outside the package');

        new ZipSource($path);
    }

    public function test_absolute_entry_is_refused(): void
    {
        $this->expectException(UnsafePackageException::class);

        new ZipSource($this->zip(['/etc/evil' => 'x']));
    }

    public function test_symlink_is_refused(): void
    {
        $path = $this->zip(['pkg/METS.xml' => '<mets/>', 'pkg/link' => '/etc/passwd']);
        $zip = new ZipArchive;
        $zip->open($path);
        $zip->setExternalAttributesName('pkg/link', ZipArchive::OPSYS_UNIX, (0120777 << 16));
        $zip->close();

        $this->expectException(UnsafePackageException::class);
        $this->expectExceptionMessage('symbolic link');

        new ZipSource($path);
    }

    public function test_entry_limit(): void
    {
        $this->expectException(UnsafePackageException::class);

        new ZipSource($this->zip(['a' => '1', 'b' => '2', 'c' => '3']), new Limits(maxEntries: 2));
    }

    public function test_size_limit(): void
    {
        $this->expectException(UnsafePackageException::class);

        new ZipSource($this->zip(['pkg/a' => str_repeat('x', 100)]), new Limits(maxUncompressedBytes: 99));
    }

    public function test_not_a_zip(): void
    {
        $path = $this->temporaryFile('zip');
        file_put_contents($path, 'not a zip');

        $this->expectException(EarkIpException::class);

        new ZipSource($path);
    }

    public function test_sources_opens_folder_or_zip(): void
    {
        $this->assertInstanceOf(ZipSource::class, Sources::open($this->zip(['pkg/METS.xml' => '<mets/>'])));
        $this->assertFalse(Sources::open($this->temporaryDirectory())->isArchive());
    }
}
