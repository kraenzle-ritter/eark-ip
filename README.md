# kraenzle-ritter/eark-ip

[![CI](https://github.com/kraenzle-ritter/eark-ip/actions/workflows/ci.yml/badge.svg)](https://github.com/kraenzle-ritter/eark-ip/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/kraenzle-ritter/eark-ip)](https://packagist.org/packages/kraenzle-ritter/eark-ip)
[![Downloads](https://img.shields.io/packagist/dt/kraenzle-ritter/eark-ip)](https://packagist.org/packages/kraenzle-ritter/eark-ip)
[![GitHub Release](https://img.shields.io/github/v/release/kraenzle-ritter/eark-ip)](https://github.com/kraenzle-ritter/eark-ip/releases)
[![E-ARK](https://img.shields.io/badge/E--ARK-CSIP%20%7C%20SIP%20%7C%20AIP%20%7C%20DIP%202.2.0%20%2F%202.1.0-0b5394)](https://earkcsip.dilcis.eu/)
[![DILCIS test corpus](https://img.shields.io/badge/DILCIS%20test%20corpus-336%20cases%20passed-brightgreen)](https://github.com/DILCISBoard/eark-ip-test-corpus)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](https://phpstan.org/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Reads, validates and writes E-ARK information packages: the Common
Specification for Information Packages (CSIP) and the SIP, AIP and DIP
specifications built on it, versions 2.2.0 and 2.1.0 (there is no AIP
2.1.0). Used by agate (SIP preparation) and Anton (archival description).

The existing E-ARK tools are Java ([commons-ip](https://github.com/keeps/commons-ip))
or Python ([eark-validator](https://github.com/E-ARK-Software/eark-validator)).
This package needs nothing beyond PHP 8.2 with ext-dom, ext-libxml and
ext-zip: no Java, no network access, no Laravel.

- **Validation** is tested against all testable cases of the
  [E-ARK IP test corpus](https://github.com/DILCISBoard/eark-ip-test-corpus)
  of the DILCIS Board (336 cases; 3 where the corpus contradicts itself
  are documented and skipped).
- **Written packages** pass this validator without warnings and are
  reported VALID by commons-ip 2.12.1.

## Installation

```bash
composer require kraenzle-ritter/eark-ip
```

## Validate

```php
use KraenzleRitter\EarkIp\Validation\Validator;

$report = (new Validator)->validate('/path/to/package.zip'); // or a folder

$report->isValid();                 // no MUST requirement failed
foreach ($report->errors() as $finding) {
    echo $finding->requirement->id, ' ', $finding->requirement->name, "\n";   // CSIP68 File mimetype
    foreach ($finding->violations as $violation) {
        echo '  ', $violation->message, ' ', $violation->location, "\n";       // METS.xml:42 <file>
    }
}
file_put_contents('report.json', $report->toJson());
```

The version comes from `mets/@PROFILE` where the profile names it;
otherwise 2.2.0 is assumed (`$report->versionDetected` tells which). Pass
a version or package type to override:
`$validator->validate($path, SpecificationVersion::V2_1_0, PackageType::SIP)`.

Each requirement of the applicable specifications gets one finding with
an outcome:

| Outcome | Meaning |
|---|---|
| `passed` | checked, nothing wrong |
| `failed` | at least one violation; its severity is `error` (MUST), `warning` (SHOULD) or `info` (MAY) |
| `not_applicable` | nothing in the package the requirement applies to, e.g. no rightsMD |
| `not_testable` | cannot be decided by a program, e.g. CSIP95; the finding says why |
| `not_checked` | not implemented; currently none |

Besides the numbered requirements of the profiles (CSIP1–119, SIP1–35,
AIPM1–7, DIP1–4) and the folder structure (CSIPSTR1–16), the validator
reports checks the specifications presuppose under `EARKIP-…` IDs: METS
well-formed (`EARKIP-XML`) and schema-valid (`EARKIP-XSD`, offline),
representation METS readable, checksum algorithm computable, every file
referenced. Sizes, checksums and file references are reported under the
requirement of the attribute (CSIP69, CSIP71, CSIP79 …), as the corpus
does.

### Report format

`toJson()` (and `eark-ip validate --format=json`) writes:

```json
{
  "report_version": "1.0",
  "validator": "kraenzle-ritter/eark-ip",
  "date": "2026-10-20T10:00:00+00:00",
  "package": { "location": "…", "objid": "…", "type": "SIP" },
  "specification": { "names": ["CSIP", "SIP"], "version": "2.2.0", "version_detected": true },
  "valid": true,
  "summary": { "passed": 134, "failed": 0, "not_applicable": 34, "not_testable": 5, "not_checked": 0, "errors": 0, "warnings": 0 },
  "findings": [
    {
      "id": "CSIP68", "specification": "CSIP", "level": "MUST", "name": "File mimetype",
      "outcome": "failed", "severity": "error",
      "violations": [ { "message": "…", "severity": "error", "location": { "file": "METS.xml", "line": 42, "element": "file" } } ],
      "notes": []
    }
  ]
}
```

## Read

```php
use KraenzleRitter\EarkIp\Reader\PackageReader;

$package = (new PackageReader)->read('/path/to/package.zip');

$package->objId();                        // mets/@OBJID
$package->type;                           // PackageType::SIP
$package->version;                        // SpecificationVersion::V2_2_0
$package->header()->agentsWithRole('ARCHIVIST');
$package->mets->metadata(MetadataSection::Descriptive);   // dmdSec/mdRef, with MDTYPE, checksum …
foreach ($package->representations as $representation) {
    $representation->dataFiles();         // package paths below data/
    $representation->mets?->files();      // what its METS lists
}
$package->parentAips;                     // AIP: structMap «parent AIP»
$package->submission;                     // AIP: the SIP under submission/
```

Reading does not validate: a package with errors can still be read, as
long as its `METS.xml` is well-formed. ZIPs are read in place, not
unpacked. Packages in TAR archives (CSIPSTR3, AIP28) are not supported
yet; unpack them first.

### PREMIS and what the representations are for

```php
use KraenzleRitter\EarkIp\Reader\PremisReader;
use KraenzleRitter\EarkIp\RepresentationRoles;

$premis = (new PremisReader)->read($package, '/path/to/package.zip');
$premis->objects;        // files and representations, with identifiers, originalName, relationships
$premis->events;         // with the objects they link: $event->linked('source'), $event->linked('outcome')
$premis->notes;          // PREMIS files that are referenced but missing or not readable

foreach (RepresentationRoles::propose($package, $premis) as $role) {
    $role->name;         // rep1
    $role->function;     // RepresentationFunction::Master, Original, Access or Unknown
    $role->basis;        // premis-event, premis-relationship, name or default
    $role->reason;       // e.g. "outcome of a migration or normalization of files in rep2"
    $role->derived;      // PREMIS says its files come from elsewhere
}
```

The reader takes every PREMIS file (PREMIS 2 or 3) that the package METS or a representation METS points to from `digiprovMD` or `techMD`. CSIP does not say what a representation is for; `RepresentationRoles` proposes it, first rule that applies: a `migration` or `normalization` event makes its source the original and its outcome the master; a `derivation` relationship (`has source`, `is source of`) makes the source the master and the derived representation the access copy, or the master when the source is the original (a chain of three: original, master, access); then the name (`access`, `web`, `display` … → access; `original`, `submission`, `delivered` … → original; `master`, `preservation` → master); otherwise the first representation is the master and the others are unknown. There is always exactly one master and at most one access copy. Objects are placed in a representation by a path `representations/<name>/…` in their identifier, content location or original name, by the representation object's name, or by the folder of the PREMIS file. It is a proposal: let a person confirm it.

## Write

```php
use KraenzleRitter\EarkIp\Writer\{MetadataFile, PackageBuilder, PackageWriter, PremisBuilder, RepresentationBuilder, StringContent};

$sip = PackageBuilder::sip('urn:uuid:5b2e7c1a-…')
    ->label('Annual report 2025')
    ->contentCategory('Textual works – Digital')
    ->contentInformationType('OTHER', 'Folder of files')
    ->software('agate', '1.4.0')
    ->archivalCreator('Example Office', identificationCode: 'EX-1')
    ->submitter('Example Office')
    ->submissionAgreement('SA-2025-01')
    ->descriptiveMetadata(MetadataFile::of('/path/ead.xml', 'EAD', '3'))
    ->descriptiveMetadata(MetadataFile::of(new StringContent($json, 'metadata.json'), 'anton-import-format', '0.5'))
    ->premis((new PremisBuilder)->fileFromPath('representations/rep1/data/a.pdf', '/path/files/a.pdf', 'fmt/276'))
    ->documentation('/path/readme.txt');
$sip->representation('rep1', fn (RepresentationBuilder $rep) => $rep->directory('/path/files'));

(new PackageWriter)->write($sip, '/out/package.zip');   // or a folder path
```

The writer computes sizes, SHA-256 checksums, MIME types and IDs, writes
the package and representation METS, the structural maps and the
schemas, and lays the package out as CSIP prescribes
([docs/structure.md](docs/structure.md)). IDs are derived from the
package identifier and paths, so writing the same package again gives
the same METS. A ZIP has a single root folder named after `OBJID`.

Metadata types METS knows (`EAD`, `DC`, `PREMIS` …) go into `@MDTYPE`;
any other name becomes `MDTYPE="OTHER"` with `@OTHERMDTYPE`.

### Media types

`@MIMETYPE` holds only media types the IANA registers (shipped list in `resources/iana-media-types.txt`). commons-ip rejects any other value as an error of CSIP68, and some common formats have no registered type at all — WAV for one (`audio/x-wav`, `audio/wav` are not registered). The writer therefore writes `application/octet-stream` for an unregistered type. To keep the format on record, pass its PRONOM PUID; in a SIP it goes into `sip:FILEFORMATREGISTRY="PRONOM"` and `sip:FILEFORMATKEY` (SIP34, SIP35):

```php
$rep->file('/path/interview.wav', 'audio/interview.wav', 'audio/x-wav', 'fmt/141');
// <file MIMETYPE="application/octet-stream" sip:FILEFORMATREGISTRY="PRONOM" sip:FILEFORMATKEY="fmt/141" …>
```

The validator reports an unregistered type, `x-` types included, as a warning under CSIP68: CSIP allows another list by agreement between sender and archive.

### AIP and DIP

```php
use KraenzleRitter\EarkIp\Writer\{AipBuilder, DipBuilder};

$aip = AipBuilder::fromSip('/in/sip.zip', 'urn:uuid:…', parentAip: 'urn:uuid:…');
(new PackageWriter)->write($aip, '/archive/aip.zip');

$dip = DipBuilder::fromAip('/archive/aip.zip', 'urn:uuid:…', representations: ['access']);
(new PackageWriter)->write($dip, '/out/dip.zip');
```

The AIP takes over the SIP's metadata and representations, adds PREMIS 3
with the ingest event and keeps the SIP unchanged under `submission/`
(switch off with `keepSubmission: false`). The DIP takes the current
descriptive metadata and the chosen representations.

## Command line

```bash
vendor/bin/eark-ip validate package.zip [--format=json] [--version=2.1.0] [--type=SIP] [--verbose]
vendor/bin/eark-ip info package.zip
vendor/bin/eark-ip create /path/to/files out.zip --id=urn:uuid:… --submitter="Example Office" --metadata=ead.xml:EAD:3
```

`validate` exits with 0 for a valid package, 1 for an invalid one, 2 for
wrong usage or a package that cannot be opened.

## Safety

Packages from elsewhere are refused before anything is read when a ZIP
entry or folder leaves the package (`..`, absolute paths, symlinks) or
exceeds the limits (`new Validator(new Limits(maxEntries: …, maxUncompressedBytes: …))`).
XML is parsed without network access and without entity substitution;
documents with a DOCTYPE are refused. Schemas inside a package are never
used for validation.

Writing checks every copy: a file that cannot be copied completely, or whose copy does not match what was read, stops the package with `CopyNotVerifiedException`. `SIZE` and `CHECKSUM` in the METS therefore describe the source, not a damaged copy.

## Where the specifications disagree

Some requirements contradict each other, the corpus or commons-ip. The
validator takes a documented position for each; see
[docs/spec-issues.md](docs/spec-issues.md).

## Sources

The specifications, METS profiles, schemas and vocabularies are published
by the [DILCIS Board](https://github.com/DILCISBoard) under CC-BY-4.0
and shipped under `resources/`, with the exact commits in
[resources/SOURCES.md](resources/SOURCES.md). The IANA media type list is
shipped for checking `@MIMETYPE`.

## Testing

```bash
composer test
composer corpus      # fetch the DILCIS test corpus; the corpus tests are skipped without it
composer analyse
tools/commons-ip-compare.sh package.zip   # cross-check with commons-ip (Java 17+)
```

## License

MIT, see [LICENSE](LICENSE).
