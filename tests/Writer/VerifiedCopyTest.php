<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Writer;

use KraenzleRitter\EarkIp\Exception\CopyNotVerifiedException;
use KraenzleRitter\EarkIp\Source\DirectorySource;
use KraenzleRitter\EarkIp\Source\PackageSource;
use KraenzleRitter\EarkIp\Tests\TestCase;
use KraenzleRitter\EarkIp\Writer\FileContent;
use KraenzleRitter\EarkIp\Writer\SourceContent;
use KraenzleRitter\EarkIp\Writer\StringContent;
use KraenzleRitter\EarkIp\Writer\VerifiedCopy;

final class VerifiedCopyTest extends TestCase
{
    public function test_copy_is_identical_and_returns_the_checksum_of_the_source(): void
    {
        $dir = $this->temporaryDirectory();
        $data = random_bytes(3 * 1048576 + 17);

        $checksum = VerifiedCopy::string($data, $dir.'/copy.bin', 'data');

        $this->assertSame(hash('sha256', $data), $checksum);
        $this->assertSame($data, file_get_contents($dir.'/copy.bin'));
        $this->assertSame(['copy.bin'], $this->entries($dir));
    }

    public function test_short_read_leaves_nothing_and_keeps_an_earlier_target(): void
    {
        $dir = $this->temporaryDirectory();
        file_put_contents($dir.'/copy.bin', 'earlier');
        $in = $this->memory('only ten b');

        try {
            VerifiedCopy::stream($in, $dir.'/copy.bin', 20, 'source.bin');
            $this->fail('A short read must not pass.');
        } catch (CopyNotVerifiedException $e) {
            $this->assertStringContainsString('read 10 of 20 bytes', $e->getMessage());
        }

        $this->assertSame('earlier', file_get_contents($dir.'/copy.bin'));
        $this->assertSame(['copy.bin'], $this->entries($dir));
    }

    public function test_unwritable_target_fails(): void
    {
        $this->expectException(CopyNotVerifiedException::class);

        VerifiedCopy::string('data', $this->temporaryDirectory().'/missing/copy.bin', 'data');
    }

    public function test_file_content_copies_checked(): void
    {
        $dir = $this->temporaryDirectory();
        file_put_contents($dir.'/a.txt', 'alpha');
        touch($dir.'/a.txt', 1_000_000_000);

        (new FileContent($dir.'/a.txt'))->writeTo($dir.'/b.txt');

        $this->assertSame('alpha', file_get_contents($dir.'/b.txt'));
        $this->assertSame(1_000_000_000, filemtime($dir.'/b.txt'));
    }

    public function test_source_content_fails_when_the_package_delivers_less_than_its_size(): void
    {
        $dir = $this->temporaryDirectory();
        file_put_contents($dir.'/a.txt', 'alpha');
        $truncating = new TruncatingSource(new DirectorySource($dir));

        try {
            (new SourceContent($truncating, 'a.txt'))->writeTo($dir.'/b.txt');
            $this->fail('A truncated entry must not pass.');
        } catch (CopyNotVerifiedException $e) {
            $this->assertStringContainsString('read 5 of 6 bytes', $e->getMessage());
        }

        $this->assertSame(['a.txt'], $this->entries($dir));
    }

    public function test_source_and_string_content_copy_checked(): void
    {
        $dir = $this->temporaryDirectory();
        file_put_contents($dir.'/a.txt', 'alpha');

        (new SourceContent(new DirectorySource($dir), 'a.txt'))->writeTo($dir.'/b.txt');
        (new StringContent('beta'))->writeTo($dir.'/c.txt');

        $this->assertSame('alpha', file_get_contents($dir.'/b.txt'));
        $this->assertSame('beta', file_get_contents($dir.'/c.txt'));
        $this->assertSame(['a.txt', 'b.txt', 'c.txt'], $this->entries($dir));
    }

    /** @return resource */
    private function memory(string $data)
    {
        $stream = fopen('php://memory', 'r+b');
        $this->assertNotFalse($stream);
        fwrite($stream, $data);
        rewind($stream);

        return $stream;
    }

    /** @return list<string> every entry, hidden ones included */
    private function entries(string $dir): array
    {
        $entries = array_values(array_diff((array) scandir($dir), ['.', '..']));
        sort($entries);

        return $entries;
    }
}

/** Claims one byte more than it delivers, like a ZIP entry that ends early. */
final class TruncatingSource implements PackageSource
{
    public function __construct(private readonly PackageSource $source) {}

    public function name(): string
    {
        return $this->source->name();
    }

    public function location(): string
    {
        return $this->source->location();
    }

    public function isArchive(): bool
    {
        return $this->source->isArchive();
    }

    public function hasSingleRootFolder(): bool
    {
        return $this->source->hasSingleRootFolder();
    }

    public function files(): array
    {
        return $this->source->files();
    }

    public function directories(): array
    {
        return $this->source->directories();
    }

    public function hasFile(string $path): bool
    {
        return $this->source->hasFile($path);
    }

    public function hasDirectory(string $path): bool
    {
        return $this->source->hasDirectory($path);
    }

    public function size(string $path): int
    {
        return $this->source->size($path) + 1;
    }

    public function contents(string $path): string
    {
        return $this->source->contents($path);
    }

    public function hash(string $path, string $algorithm): string
    {
        return $this->source->hash($path, $algorithm);
    }

    public function stream(string $path)
    {
        return $this->source->stream($path);
    }
}
