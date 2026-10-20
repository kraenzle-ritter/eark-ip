# Sources

Everything under `spec/` and `schemas/` except the files marked as
written for this package is copied unchanged, or with the change noted,
from the sources below. Retrieved 2026-10-20.

## E-ARK specifications (DILCIS Board, CC-BY-4.0)

| Files | Repository | Tag / commit |
|---|---|---|
| `spec/2.2.0/profiles/E-ARK-CSIP-v2-2-0.xml`, `spec/2.1.0/profiles/E-ARK-CSIP-v2-1-0.xml` | [DILCISBoard/E-ARK-CSIP](https://github.com/DILCISBoard/E-ARK-CSIP) | v2.2.0, `a2364c5` (the 2.1.0 profile is the copy kept there) |
| `spec/2.2.0/profiles/E-ARK-SIP-v2-2-0.xml`, `spec/2.1.0/profiles/E-ARK-SIP-v2-1-0.xml` | [DILCISBoard/E-ARK-SIP](https://github.com/DILCISBoard/E-ARK-SIP) | v2.2.0, `a2dcdba` |
| `spec/2.2.0/profiles/E-ARK-AIP-v2-2-0.xml` | [DILCISBoard/E-ARK-AIP](https://github.com/DILCISBoard/E-ARK-AIP) | v2.2.0, `96f6631` |
| `spec/2.2.0/profiles/E-ARK-DIP-v2-2-0.xml`, `spec/2.1.0/profiles/E-ARK-DIP-v2-1-0.xml` | [DILCISBoard/E-ARK-DIP](https://github.com/DILCISBoard/E-ARK-DIP) | v2.2.0, `8f826c5` |
| `spec/2.1.0/schemas/*.xsd`, `spec/2.1.0/vocabularies/*` | CSIP and SIP repositories | v2.1.0, `5395094` (CSIP), `9cfb6ec` (SIP) |
| `spec/2.2.0/schemas/DILCISExtensionSIPMETS.xsd`, `spec/2.2.0/vocabularies/*` | CSIP and SIP repositories | v2.2.0 |
| `spec/2.2.0/schemas/DILCISExtensionMETS.xsd`, `spec/2.2.0/vocabularies/CSIPVocabularyContentInformationType.xml` | DILCISBoard/E-ARK-CSIP | `master`, `9ad7e22` (2.2.0 plus the value `cits3dpm_v1_0`, added 2024-12) |

## Other schemas

| File | Source | Change |
|---|---|---|
| `schemas/mets.xsd` | METS 1.12.1, from the CSIP repository (identical to loc.gov) | import of xlink points to the local `xlink.xsd` |
| `schemas/xlink.xsd` | http://www.loc.gov/standards/xlink/xlink.xsd | none |
| `schemas/xml.xsd` | https://www.w3.org/2001/xml.xsd | none |
| `schemas/premis-v3-0.xsd` | https://www.loc.gov/standards/premis/v3/premis-v3-0.xsd | none |

## Written for this package

- `spec/*/schemas/eark-ip-mets.xsd`: wrapper that imports METS and the
  DILCIS extensions, for offline validation.
- `spec/*/requirements.json`: built from the profiles by
  `tools/build-catalog.php`.
- `structure-requirements.json`: CSIPSTR1–16, transcribed from the CSIP
  specification text (section «Folder structure»).
- `eark-ip-requirements.json`: checks the specifications presuppose but do
  not number (integrity, references).

## IANA

`iana-media-types.txt`: the registered media types, built from the CSV
files at https://www.iana.org/assignments/media-types/ (retrieved
2026-10-20). One `type/subtype` per line.
