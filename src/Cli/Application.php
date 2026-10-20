<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Cli;

use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\Reader\PackageReader;
use KraenzleRitter\EarkIp\SpecificationVersion;
use KraenzleRitter\EarkIp\Validation\Finding;
use KraenzleRitter\EarkIp\Validation\Outcome;
use KraenzleRitter\EarkIp\Validation\Severity;
use KraenzleRitter\EarkIp\Validation\ValidationReport;
use KraenzleRitter\EarkIp\Validation\Validator;
use KraenzleRitter\EarkIp\Writer\MetadataFile;
use KraenzleRitter\EarkIp\Writer\PackageBuilder;
use KraenzleRitter\EarkIp\Writer\PackageWriter;

/**
 * The eark-ip command line:
 *
 *     eark-ip validate <package> [--format=text|json] [--version=2.2.0] [--type=SIP] [--verbose]
 *     eark-ip info <package>
 *     eark-ip create <folder> <target.zip|folder> --id=<OBJID> [options]
 *
 * Exit codes: 0 valid / done, 1 invalid, 2 wrong usage or error.
 */
final class Application
{
    private const USAGE = <<<'TXT'
        Usage:
          eark-ip validate <package> [--format=text|json] [--version=2.1.0|2.2.0] [--type=SIP|AIP|DIP] [--verbose]
          eark-ip info <package>
          eark-ip create <folder> <target.zip|folder> --id=<OBJID> [--type=SIP|AIP|DIP] [--version=2.2.0]
                 [--label=<text>] [--submitter=<name>] [--archival-creator=<name>]
                 [--metadata=<file>:<TYPE>[:<version>]]... [--documentation=<file>]...
                 [--representation=<name>] [--content-information-type=<type>]

        A package is a folder or a ZIP. The folder given to create becomes the
        data of one representation (default "rep1").

        TXT;

    /**
     * @param  resource  $out
     * @param  resource  $err
     */
    public function __construct(private $out = STDOUT, private $err = STDERR) {}

    /** @param  list<string>  $argv  without the program name */
    public function run(array $argv): int
    {
        [$command, $arguments, $options] = $this->parse($argv);

        try {
            return match ($command) {
                'validate' => $this->validate($arguments, $options),
                'info' => $this->info($arguments),
                'create' => $this->create($arguments, $options),
                'help', '--help', '-h', null => $this->usage(0),
                default => $this->usage(2, "Unknown command \"{$command}\"."),
            };
        } catch (EarkIpException|\ValueError $exception) {
            fwrite($this->err, 'Error: '.$exception->getMessage()."\n");

            return 2;
        }
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, list<string>>  $options
     */
    private function validate(array $arguments, array $options): int
    {
        if (count($arguments) !== 1) {
            return $this->usage(2, 'validate needs exactly one package.');
        }
        $report = (new Validator)->validate(
            $arguments[0],
            isset($options['version']) ? SpecificationVersion::fromString($options['version'][0]) : null,
            isset($options['type']) ? PackageType::from(strtoupper($options['type'][0])) : null,
        );

        if (($options['format'][0] ?? 'text') === 'json') {
            fwrite($this->out, $report->toJson()."\n");
        } else {
            $this->printReport($report, isset($options['verbose']));
        }

        return $report->isValid() ? 0 : 1;
    }

    /** @param  list<string>  $arguments */
    private function info(array $arguments): int
    {
        if (count($arguments) !== 1) {
            return $this->usage(2, 'info needs exactly one package.');
        }
        $package = (new PackageReader)->read($arguments[0]);
        $mets = $package->mets;

        $lines = [
            'Package:        '.$package->location,
            'OBJID:          '.($package->objId() ?? '(none)'),
            'Type:           '.($package->type->value ?? '(unknown)'),
            'Version:        '.$package->version->value.($package->versionDetected ? '' : ' (not stated, assumed)'),
            'Profile:        '.($mets->profile ?? '(none)'),
            'Label:          '.($mets->label ?? ''),
            'Content:        '.($mets->type ?? '').' / '.($mets->contentInformationType ?? ''),
        ];
        foreach ($package->header()->agents ?? [] as $agent) {
            $lines[] = sprintf('Agent:          %s (%s%s, %s)', $agent->name, $agent->role, $agent->otherRole !== null ? '/'.$agent->otherRole : '', $agent->type);
        }
        foreach ($mets->metadata as $reference) {
            $lines[] = sprintf('Metadata:       %s %s %s', $reference->section->value, $reference->type(), $reference->href ?? '(embedded)');
        }
        foreach ($package->representations as $representation) {
            $lines[] = sprintf('Representation: %s, %d data files%s', $representation->name, count($representation->dataFiles()), $representation->mets === null ? ', no METS.xml' : '');
        }
        foreach ($package->parentAips as $parent) {
            $lines[] = 'Parent AIP:     '.$parent;
        }
        if ($package->submission !== null) {
            $lines[] = 'Submission:     '.($package->submission->objId() ?? '(no OBJID)');
        }
        fwrite($this->out, implode("\n", $lines)."\n");

        return 0;
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, list<string>>  $options
     */
    private function create(array $arguments, array $options): int
    {
        if (count($arguments) !== 2 || ! isset($options['id'])) {
            return $this->usage(2, 'create needs a folder, a target and --id.');
        }
        [$folder, $target] = $arguments;
        if (! is_dir($folder)) {
            throw new EarkIpException("{$folder} is not a folder.");
        }

        $builder = new PackageBuilder(PackageType::from(strtoupper($options['type'][0] ?? 'SIP')), $options['id'][0]);
        if (isset($options['version'])) {
            $builder->version(SpecificationVersion::fromString($options['version'][0]));
        }
        if (isset($options['label'])) {
            $builder->label($options['label'][0]);
        }
        foreach ($options['submitter'] ?? [] as $name) {
            $builder->submitter($name);
        }
        foreach ($options['archival-creator'] ?? [] as $name) {
            $builder->archivalCreator($name);
        }
        foreach ($options['metadata'] ?? [] as $metadata) {
            $parts = explode(':', $metadata);
            if (count($parts) < 2) {
                throw new EarkIpException("--metadata expects <file>:<TYPE>[:<version>], not \"{$metadata}\".");
            }
            $file = MetadataFile::of($parts[0], $parts[1], $parts[2] ?? null);
            str_starts_with($file->mdType, 'PREMIS') ? $builder->preservationMetadata($file) : $builder->descriptiveMetadata($file);
        }
        foreach ($options['documentation'] ?? [] as $file) {
            $builder->documentation($file);
        }
        if (isset($options['content-information-type'])) {
            $builder->contentInformationType($options['content-information-type'][0]);
        }
        $builder->representation($options['representation'][0] ?? 'rep1')->directory($folder);

        $written = (new PackageWriter)->write($builder, $target);
        fwrite($this->out, "Written {$written}\n");

        return 0;
    }

    private function printReport(ValidationReport $report, bool $verbose): void
    {
        fwrite($this->out, sprintf(
            "%s\n%s %s, E-ARK %s %s%s\n\n",
            $report->location,
            $report->isValid() ? 'VALID' : 'INVALID',
            $report->objId ?? '(no OBJID)',
            implode('+', $report->specifications),
            $report->version->value,
            $report->versionDetected ? '' : ' (version not stated, assumed)',
        ));

        $shown = array_filter($report->findings, fn (Finding $finding) => $finding->outcome === Outcome::Failed
            && ($verbose || $finding->severity() !== Severity::Info));
        foreach ($shown as $finding) {
            foreach ($finding->violations as $violation) {
                $severity = $violation->severity ?? Severity::forLevel($finding->requirement->level);
                if (! $verbose && $severity === Severity::Info) {
                    continue;
                }
                fwrite($this->out, sprintf(
                    "%-7s %-10s %s%s\n",
                    strtoupper($severity->value),
                    $finding->requirement->id,
                    $violation->message,
                    $violation->location !== null ? '  ['.$violation->location.']' : '',
                ));
            }
        }

        $summary = $report->summary();
        fwrite($this->out, sprintf(
            "\n%d errors, %d warnings; %d requirements passed, %d not applicable, %d not testable.\n",
            $summary['errors'],
            $summary['warnings'],
            $summary['passed'],
            $summary['not_applicable'],
            $summary['not_testable'],
        ));
    }

    private function usage(int $code, ?string $message = null): int
    {
        $stream = $code === 0 ? $this->out : $this->err;
        if ($message !== null) {
            fwrite($stream, $message."\n\n");
        }
        fwrite($stream, self::USAGE);

        return $code;
    }

    /**
     * @param  list<string>  $argv
     * @return array{string|null, list<string>, array<string, list<string>>}
     */
    private function parse(array $argv): array
    {
        $command = array_shift($argv);
        $arguments = [];
        $options = [];
        foreach ($argv as $argument) {
            if (preg_match('/^--([a-z-]+)(?:=(.*))?$/s', $argument, $match)) {
                $options[$match[1]][] = $match[2] ?? '';
            } else {
                $arguments[] = $argument;
            }
        }

        return [$command, $arguments, $options];
    }
}
