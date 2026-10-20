#!/bin/sh
# Fetches the E-ARK IP test corpus of the DILCIS Board into tests/corpus/
# at a fixed commit. The corpus test is skipped when it is not there.
set -e
COMMIT=1d02c1b298b38f75f0ee0d8c27b7b95f8ced5fa7
TARGET="$(dirname "$0")/../tests/corpus"
rm -rf "$TARGET"
mkdir -p "$TARGET"
curl -sfL "https://codeload.github.com/DILCISBoard/eark-ip-test-corpus/tar.gz/$COMMIT" | tar xz -C "$TARGET" --strip-components=1
echo "E-ARK IP test corpus $COMMIT in $TARGET"
