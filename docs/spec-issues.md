# Where the specifications disagree

The E-ARK specifications 2.2.0, the DILCIS test corpus and commons-ip
(the reference implementation) do not always say the same. For each
case, this package takes the position below. Worth reporting to the
[DILCIS Board](https://github.com/DILCISBoard).

## Profiles

1. **AIP profile URL.** The AIP profile names itself
   `https://earkdip.dilcis.eu/profile/E-ARK-AIP-v2-2-0.xml` (*earkdip*). The
   writer uses `earkaip.dilcis.eu`; the validator accepts both (AIPM2).
2. **DIP profile URL.** DIP2 prescribes `…/E-ARK-DIP.xml`, the profile's own
   URI is `…/E-ARK-DIP-v2-2-0.xml`. Both are accepted; the writer uses DIP2's.
3. **SIP format attributes.** The SIP profile speaks of
   `sip:FILEFORMATREGISTRY` and `sip:FILEFORMATKEY` (SIP34, SIP35), the SIP
   extension schema declares `sip:FORMATREGISTRY` and `sip:FORMATREGISTRYKEY`.
   Both spellings are read and checked.
4. **SIP agents.** The submitting agent (SIP15, 1..1) and the contact person
   (SIP21, role "CREATOR", type "INDIVIDUAL") cannot be told apart by the
   profile's text, and its own example writes the contact person as
   `ROLE="OTHER" OTHERROLE="SUBMITTER"`. Following commons-ip, the submitting
   agent is the one `CREATOR` with `TYPE` `ORGANIZATION` or `INDIVIDUAL`; a
   contact person is `OTHER`/`SUBMITTER`. SIP22 is reported as not testable.
5. **Mandatory file groups that may be empty.** CSIP60, CSIP113 and CSIP114
   require file groups "Documentation", "Schemas" and "Representations"
   (MUST, 1..n), CSIP66 requires every file group to contain a file. A package
   without documentation cannot satisfy both. As in the corpus, a missing
   group is a warning.
6. **CSIP62** (SHOULD) reads as if every file group needed a content
   information type in a `MIXED` package. Only representation groups are
   checked; schemas and documentation have no content type.
7. **Representation METS.** The profile does not say which requirements
   apply to a representation METS. Header and agent rules (CSIP10–16) are
   checked there, but as warnings: commons-ip and RODA write no agents into
   representation METS. Package-level requirements (CSIP60, CSIP88–112,
   SIP/AIP/DIP header rules) apply only to the package METS.
8. **"Submission" file group of an AIP.** CSIP64 allows `@USE` only to begin
   with Documentation, Schemas, Representations or Metadata; the AIP
   examples and commons-ip use "Submission". Accepted for AIPs.

## Test corpus

The corpus files size, checksum and file reference errors under the
attribute's requirement (CSIP27/29, CSIP41/43, CSIP54/56, CSIP69/71,
CSIP24/38/51/79); this package does the same. Its test cases are written
for 2.0.4 to 2.1.0 and many packages are valid only for the requirement
they test. Three cases contradict themselves and are skipped
(`tests/Validation/CorpusTest.php`):

- CSIP17 `IP_18006_CSIP17_1` and CSIP21 `IP_18000_CSIP21_2` are listed as
  valid for a SHOULD rule they violate.
- CSIP61 `fileGrp_ADMID_incorrect_ref2` has its wrong reference in the
  `ADMID` of the Metadata division (CSIP91), not of a file group.

Several corpus packages reference files with a different case
(`ead.xml` for `EAD.xml`). Such references are resolved, with a warning,
since they break on case-sensitive file systems.

## commons-ip 2.12.1

- **CSIP1.** commons-ip requires the root folder to be named exactly like
  `OBJID` (a MUST error otherwise). The specification only recommends it
  (CSIPSTR2) and allows a mapping for characters file systems refuse. The
  writer keeps `OBJID` as it is, apart from slashes; the validator compares
  leniently and warns.
- **CSIP101.** commons-ip warns when there is a division "Representations"
  but no file group with `@USE` exactly "Representations". CSIP114 asks for
  groups named "Representations" followed by the path, which the writer
  produces; commons-ip's own packages avoid the warning by writing no
  "Representations" division at all (missing CSIP101/104, both SHOULD).
  Packages from this writer therefore get this one warning from
  commons-ip.
- **Empty documentation.** commons-ip requires the label of every division
  to lead to a folder (its CSIP107). The writer always writes a
  Documentation division (CSIP93 SHOULD) and therefore always creates
  `documentation/`, empty when there is none (since 0.1.1).
- **Media types (CSIP68).** commons-ip accepts in `@MIMETYPE` only media types registered with the IANA and reports anything else as an error, `x-` types included. CSIP 2.2 only *strongly recommends* the IANA list and allows another one by agreement. Some common formats have no registered type: WAV (`audio/x-wav`, `audio/wav`) is the case that turned up, in a KOST test SIP (2026-10-20). Position: the validator warns for every unregistered type; the writer writes `application/octet-stream` instead and keeps the format as PRONOM PUID in `sip:FILEFORMATKEY` (SIP only) and, where the caller writes PREMIS, in PREMIS (since 0.1.2).
- **AIP9.** commons-ip requires AIP file IDs to start with "ID"; all IDs the
  writer produces do.
