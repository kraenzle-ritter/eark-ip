# Package structure

What the writer produces and the validator expects, after CSIP, SIP, AIP
and DIP 2.2.0.

```
<OBJID>/                                   root folder named after mets/@OBJID (CSIPSTR1, CSIPSTR2)
  METS.xml                                 package METS (CSIPSTR4)
  metadata/                                (CSIPSTR5)
    descriptive/                           dmdSec files: EAD, DC, …, other types (CSIPSTR7)
    preservation/                          digiprovMD files: PREMIS (CSIPSTR6)
    rights/                                rightsMD files (CSIPSTR8)
  documentation/                           fileGrp "Documentation" (CSIPSTR16)
  schemas/                                 fileGrp "Schemas": METS, xlink, DILCIS extensions, PREMIS, the caller's (CSIPSTR15)
  representations/                         (CSIPSTR9)
    <name>/                                one folder per representation (CSIPSTR10)
      METS.xml                             representation METS (CSIPSTR12)
      data/                                the content (CSIPSTR11)
      metadata/                            always created (CSIPSTR13)
        descriptive/  preservation/
      documentation/  schemas/             when given
  submission/                              AIP only: the SIP as received, fileGrp "Submission"
```

## Package METS

- Root: `OBJID`, `LABEL`, `TYPE` (Content Category), `csip:CONTENTINFORMATIONTYPE`
  (+ `OTHERCONTENTINFORMATIONTYPE`), `PROFILE` of the package type and version.
- `metsHdr`: `CREATEDATE`, `LASTMODDATE`, `RECORDSTATUS` (SIP: `NEW`),
  `csip:OAISPACKAGETYPE`; the software agent (`CREATOR`/`OTHER`/`SOFTWARE`
  with a `SOFTWARE VERSION` note), then the SIP agents; `altRecordID`s.
- One `dmdSec` per descriptive file, `STATUS="CURRENT"`; one `amdSec` with a
  `digiprovMD` per preservation file and a `rightsMD` per rights file. Every
  `mdRef` carries `LOCTYPE`, `xlink:type`, `xlink:href`, `MDTYPE`
  (`OTHERMDTYPE`, `MDTYPEVERSION`), `MIMETYPE`, `SIZE`, `CREATED`,
  `CHECKSUM` and `CHECKSUMTYPE` (SHA-256).
- `fileSec`: `Documentation`, `Schemas`, one `Representations/<name>` group
  per representation holding its METS file (divided METS, AIP3), with the
  representation's content information type; AIP: `Submission`.
- `structMap LABEL="CSIP" TYPE="PHYSICAL"`: main division labelled `OBJID`
  with `Metadata` (`DMDID`, `ADMID` listing all sections), `Documentation`,
  `Schemas`, `Representations` (fptr to every representation group), one
  division `Representations/<name>` per representation with an `mptr`
  (`xlink:title` = group ID), AIP: `Submission`.
- AIP: `structMap LABEL="parent AIP"` / `"child AIPs"` with `mptr
  LOCTYPE="OTHER" OTHERLOCTYPE="UUID"` when given.

## Representation METS

`OBJID` is the folder name; same content category and profile as the
package, the representation's content information type (MUST in a
representation METS, CSIP4); `metsHdr` with the software agent; `dmdSec` /
`amdSec` for its metadata; `fileSec` with `Data` (and `Documentation`,
`Schemas`); `structMap LABEL="CSIP"` with `Metadata`, `Data` and the
others.

## SIP agents

| Agent | ROLE | TYPE | Requirements |
|---|---|---|---|
| Software | `CREATOR` | `OTHER`, `OTHERTYPE="SOFTWARE"` | CSIP10–16 |
| Archival creator | `ARCHIVIST` | `ORGANIZATION` or `INDIVIDUAL` | SIP9–14 |
| Submitting agent (exactly one) | `CREATOR` | `ORGANIZATION` or `INDIVIDUAL` | SIP15–20 |
| Contact person | `OTHER`, `OTHERROLE="SUBMITTER"` | `INDIVIDUAL` | SIP21–25 |
| Preservation agent | `PRESERVATION` | `ORGANIZATION` | SIP26–31 |

See [spec-issues.md](spec-issues.md) for why the submitting agent and the
contact person are told apart this way.

## AIP

AIP 2.2.0 no longer requires the SIP inside the AIP; the examples of the
specification (earkweb) still keep it under `submission/`, and so does
`AipBuilder` unless told not to. The AIP gets PREMIS 3 as `digiprovMD`
with `MDTYPEVERSION="3.0"` and an `ingestion` event linking the SIP
(source) and the AIP (outcome) (AIPM5–7).

## DIP

The DIP takes over descriptive metadata with status `CURRENT` (DIP4) and
the chosen representations; it has no submission.
