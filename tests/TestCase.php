<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @var list<string> */
    private array $temporary = [];

    protected function tearDown(): void
    {
        foreach ($this->temporary as $path) {
            self::remove($path);
        }
        $this->temporary = [];
        parent::tearDown();
    }

    protected function temporaryDirectory(string $name = 'package'): string
    {
        $base = sys_get_temp_dir().'/eark-ip-test-'.bin2hex(random_bytes(6));
        mkdir($base.'/'.$name, 0777, true);
        $this->temporary[] = $base;

        return $base.'/'.$name;
    }

    protected function temporaryFile(string $extension = 'zip'): string
    {
        $path = sys_get_temp_dir().'/eark-ip-test-'.bin2hex(random_bytes(6)).'.'.$extension;
        $this->temporary[] = $path;

        return $path;
    }

    /** @param  array<string, string>  $files  path => contents */
    protected function writeFiles(string $root, array $files): void
    {
        foreach ($files as $path => $contents) {
            $target = $root.'/'.$path;
            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0777, true);
            }
            file_put_contents($target, $contents);
        }
    }

    /** @param  array<string, string>  $entries  entry name => contents; a name ending in "/" is a folder */
    protected function zip(array $entries): string
    {
        $path = $this->temporaryFile();
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($entries as $name => $contents) {
            str_ends_with($name, '/') ? $zip->addEmptyDir(rtrim($name, '/')) : $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }

    protected static function fixture(string $path): string
    {
        return __DIR__.'/Fixtures/'.$path;
    }

    private static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }
        if (! is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') {
                self::remove($path.'/'.$item);
            }
        }
        rmdir($path);
    }
}
