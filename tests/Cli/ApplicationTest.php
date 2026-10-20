<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Cli;

use KraenzleRitter\EarkIp\Cli\Application;
use KraenzleRitter\EarkIp\Tests\TestCase;

final class ApplicationTest extends TestCase
{
    /** @return array{int, string, string} exit code, output, errors */
    private function call(string ...$arguments): array
    {
        $out = fopen('php://memory', 'w+');
        $err = fopen('php://memory', 'w+');
        assert($out !== false && $err !== false);
        $code = (new Application($out, $err))->run(array_values($arguments));
        rewind($out);
        rewind($err);

        return [$code, (string) stream_get_contents($out), (string) stream_get_contents($err)];
    }

    public function test_validate_a_valid_package(): void
    {
        [$code, $out] = $this->call('validate', self::fixture('packages/sip-2.2.0'));

        $this->assertSame(0, $code);
        $this->assertStringContainsString('VALID sip-2.2.0, E-ARK CSIP+SIP 2.2.0', $out);
        $this->assertStringContainsString('0 errors, 0 warnings', $out);
    }

    public function test_validate_an_invalid_package_as_json(): void
    {
        $root = $this->temporaryDirectory();
        file_put_contents($root.'/readme.txt', 'x');

        [$code, $out] = $this->call('validate', '--format=json', $root);

        $this->assertSame(1, $code);
        $data = json_decode($out, true);
        $this->assertFalse($data['valid']);
        $this->assertSame(1, $data['summary']['errors']);
    }

    public function test_validate_lists_violations_in_text(): void
    {
        $root = $this->temporaryDirectory();
        file_put_contents($root.'/METS.xml', '<mets');

        [$code, $out] = $this->call('validate', $root);

        $this->assertSame(1, $code);
        $this->assertMatchesRegularExpression('/^ERROR\s+EARKIP-XML/m', $out);
    }

    public function test_info(): void
    {
        [$code, $out] = $this->call('info', self::fixture('packages/sip-2.2.0'));

        $this->assertSame(0, $code);
        $this->assertStringContainsString('OBJID:          sip-2.2.0', $out);
        $this->assertStringContainsString('Representation: rep1, 1 data files', $out);
    }

    public function test_create_then_validate(): void
    {
        $data = $this->temporaryDirectory('data');
        $this->writeFiles($data, ['letters/1901.txt' => 'Dear …']);
        $target = $this->temporaryFile();

        [$code, $out] = $this->call('create', $data, $target, '--id=cli-1', '--submitter=Example Office', '--label=Letters');
        $this->assertSame(0, $code, $out);

        [$code] = $this->call('validate', $target);
        $this->assertSame(0, $code);
    }

    public function test_usage_errors(): void
    {
        $this->assertSame(2, $this->call('validate')[0]);
        $this->assertSame(2, $this->call('create', 'a')[0]);
        $this->assertSame(2, $this->call('frobnicate')[0]);
        $this->assertSame(0, $this->call('help')[0]);
    }

    public function test_errors_are_reported_not_thrown(): void
    {
        [$code, , $err] = $this->call('validate', '/no/such/package');

        $this->assertSame(2, $code);
        $this->assertStringContainsString('No package found', $err);

        [$code, , $err] = $this->call('validate', '--version=1.0', self::fixture('packages/sip-2.2.0'));
        $this->assertSame(2, $code);
        $this->assertStringContainsString('not supported', $err);
    }
}
