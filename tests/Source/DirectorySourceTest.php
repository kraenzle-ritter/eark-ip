<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Source;

use KraenzleRitter\EarkIp\Exception\PackageNotFoundException;
use KraenzleRitter\EarkIp\Exception\UnsafePackageException;
use KraenzleRitter\EarkIp\Source\DirectorySource;
use KraenzleRitter\EarkIp\Tests\TestCase;

final class DirectorySourceTest extends TestCase
{
    public function test_lists_files_and_folders(): void
    {
        $root = $this->temporaryDirectory('my-package');
        $this->writeFiles($root, [
            'METS.xml' => '<mets/>',
            'representations/rep1/data/a.txt' => 'abc',
            '.DS_Store' => 'x',
        ]);
        mkdir($root.'/documentation');

        $source = new DirectorySource($root);

        $this->assertSame('my-package', $source->name());
        $this->assertSame(['METS.xml', 'representations/rep1/data/a.txt'], $source->files());
        $this->assertSame(['documentation', 'representations', 'representations/rep1', 'representations/rep1/data'], $source->directories());
        $this->assertTrue($source->hasFile('METS.xml'));
        $this->assertTrue($source->hasDirectory('documentation'));
        $this->assertFalse($source->isArchive());
        $this->assertTrue($source->hasSingleRootFolder());
    }

    public function test_reads_size_contents_and_hash(): void
    {
        $root = $this->temporaryDirectory();
        $this->writeFiles($root, ['a.txt' => 'abc']);

        $source = new DirectorySource($root);

        $this->assertSame(3, $source->size('a.txt'));
        $this->assertSame('abc', $source->contents('a.txt'));
        $this->assertSame(hash('sha256', 'abc'), $source->hash('a.txt', 'sha256'));
        $stream = $source->stream('a.txt');
        $this->assertSame('abc', stream_get_contents($stream));
        fclose($stream);
    }

    public function test_symlink_is_refused(): void
    {
        $root = $this->temporaryDirectory();
        symlink('/etc/hosts', $root.'/hosts');

        $this->expectException(UnsafePackageException::class);

        new DirectorySource($root);
    }

    public function test_missing_folder(): void
    {
        $this->expectException(PackageNotFoundException::class);

        new DirectorySource('/nonexistent/package');
    }
}
