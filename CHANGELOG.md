# Changelog

All notable changes to kraenzle-ritter/eark-ip. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.2.1] - 2026-10-22

### Added
- `RepresentationRole` carries its reason as a code with values, `reasonCode` (`REASON_*`, e.g. `migration-outcome`) and `reasonParams` (e.g. `['source' => 'rep2']`), also in `toArray()` as `reason_code` and `reason_params`, so an application can show the reason in its own language. `RepresentationRole::reasonText()` makes the English text; `reason` is unchanged. A further master or access copy keeps the reason it would have had in `previous`.

## [0.2.0] - 2026-10-22

### Added
- `Reader\PremisReader` reads the PREMIS files (PREMIS 2 and 3) that the package METS and the representation METS documents point to: objects with identifiers, original name, content location and relationships, events with the objects they link and their roles, agents. Missing or unreadable files become notes; reading goes on (agate#14).
- `RepresentationRoles::propose()` proposes what each representation is for (`RepresentationFunction`: master, original, access, unknown) from migration and normalization events, derivation relationships, the names, else the order; exactly one master, at most one access copy. With the basis and a reason for showing to a person.
- `PremisBuilder::relationship()` writes relationships between objects, e.g. derivation / `has source` from an access copy to its master.

## [0.1.3] - 2026-10-20

### Fixed
- The writer checks every file it copies into a package. Before, `FileContent` only checked that `copy()` did not return false, `SourceContent` and `StringContent` did not check at all, and `SIZE` and `CHECKSUM` in the METS were computed from the copy: a copy cut short (disk full, read error, ZIP entry ending early) carried its own matching checksum and passed validation. Now the data goes to a temporary name, hashed while it is read, the copy is compared with what was read and with the size of the source, and only then renamed into place; otherwise `CopyNotVerifiedException` (an `EarkIpException`) and nothing is left at the target, so no package is written (agate#12).

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
