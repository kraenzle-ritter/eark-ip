# kraenzle-ritter/eark-ip

[![CI](https://github.com/kraenzle-ritter/eark-ip/actions/workflows/ci.yml/badge.svg)](https://github.com/kraenzle-ritter/eark-ip/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://www.php.net/)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](https://phpstan.org/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

Reads, validates and writes E-ARK information packages: the Common
Specification for Information Packages (CSIP) and the SIP, AIP and DIP
specifications built on it, versions 2.2.0 and 2.1.0. Used by agate (SIP
preparation) and Anton (archival description).

There was no PHP implementation of E-ARK so far; the existing tools are
Java ([commons-ip](https://github.com/keeps/commons-ip)) or Python
([eark-validator](https://github.com/E-ARK-Software/eark-validator)).
This package needs nothing beyond PHP 8.2 with ext-dom, ext-libxml and
ext-zip: no Java, no network access, no Laravel.

> **Status:** in development, nothing usable yet. The plan is below.

## Plan

- **Read** a package from a folder or a ZIP into a model: package,
  representations, files, metadata references, agents. Reading does not
  validate, so broken packages from elsewhere can still be inspected.
- **Validate** folder structure, XSD (offline), the requirements of the
  METS profiles and integrity (checksums, sizes, references). The report
  names each requirement by its official ID (`CSIP68`, `SIP4`, `AIPM2` …)
  with its level (MUST, SHOULD, MAY). A package is valid when no MUST
  requirement fails.
- **Write** packages as a folder or a ZIP: package and representation
  METS, checksums, schemas, PREMIS. Descriptive metadata (EAD, Dublin
  Core …) is supplied by the caller as files.
- **Derive** an AIP from a SIP and a DIP from an AIP.
- **CLI** `vendor/bin/eark-ip validate|info|create`.

Validation is tested against the
[E-ARK IP test corpus](https://github.com/DILCISBoard/eark-ip-test-corpus)
of the DILCIS Board and cross-checked with commons-ip.

## Installation

```bash
composer require kraenzle-ritter/eark-ip
```

## Sources

The specifications, METS profiles, schemas and vocabularies are published
by the [DILCIS Board](https://github.com/DILCISBoard) under CC-BY-4.0. The
Schematron rules for 2.1.0 are taken from eark-validator (Apache-2.0).
Both will be shipped under `resources/` with their sources noted.

## Testing

```bash
composer test
composer analyse
```

## License

MIT, see [LICENSE](LICENSE).
