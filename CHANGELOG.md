# Changelog

All notable changes to kraenzle-ritter/eark-ip. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.2] - 2026-10-20

### Changed
- The writer writes `application/octet-stream` into `@MIMETYPE` when the IANA does not register the given type, e.g. `audio/x-wav`; commons-ip reports such types as an error of CSIP68 and WAV has no registered type at all. `RepresentationBuilder::file()` takes the PRONOM PUID as fourth argument; in a SIP it is written as `sip:FILEFORMATREGISTRY="PRONOM"` and `sip:FILEFORMATKEY`. `AipBuilder` and `DipBuilder` carry PUIDs over from the source package.
- The validator reports every media type the IANA does not register as a warning under CSIP68, `x-`, `vnd.` and `prs.` types included (before: those passed silently).
- `MediaTypes` moved from `KraenzleRitter\EarkIp\Validation\Rules` to `KraenzleRitter\EarkIp`.

### Fixed
- CSIPSTR15 no longer reports XML schemas that are content of a representation (`representations/*/data/`) as misplaced, e.g. the `header/xsd/` of an eCH-0160 delivery.

## [0.1.1] - 2026-10-20

### Fixed
- The writer always creates `documentation/` (also in each representation), so that the Documentation division of the structural map leads to a folder; commons-ip reported CSIP107 for packages without documentation.

## [0.1.0] - 2026-10-20

First release: reads, validates and writes E-ARK SIP, AIP and DIP (CSIP 2.2.0 and 2.1.0, no AIP 2.1.0); validator tested against the DILCIS IP test corpus; writer output reported VALID by commons-ip 2.12.1; CLI `eark-ip validate|info|create`.
