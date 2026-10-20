#!/bin/sh
# Cross-checks packages with commons-ip, the Java validator of the E-ARK
# community, and prints what it reports as failed. Development only;
# needs Java 17 or later.
#
#     tools/commons-ip-compare.sh package.zip [more packages …]
#
# The JAR is downloaded once into tools/commons-ip/ (gitignored).
set -e
VERSION=2.12.1
DIR="$(cd "$(dirname "$0")" && pwd)/commons-ip"
JAR="$DIR/commons-ip2-cli-$VERSION.jar"
mkdir -p "$DIR"
if [ ! -f "$JAR" ]; then
  curl -sfL -o "$JAR" "https://github.com/keeps/commons-ip/releases/download/$VERSION/commons-ip2-cli-$VERSION.jar"
fi
for package in "$@"; do
  REPORTS="$(mktemp -d)"
  java -jar "$JAR" validate -i "$package" -o "$REPORTS" --specification-version 2.2.0 > /dev/null 2>&1 || true
  echo "== $package"
  php -r '
    foreach (glob($argv[1]."/*.json") as $file) {
        $report = json_decode(file_get_contents($file), true);
        echo "   ", $report["summary"]["result"] ?? "?", ": ", json_encode($report["summary"]), "\n";
        foreach ($report["validation"] ?? [] as $entry) {
            $outcome = $entry["testing"]["outcome"] ?? "";
            if (! in_array($outcome, ["PASSED", "SKIPPED", "NOT_APPLICABLE"], true)) {
                $messages = array_merge($entry["testing"]["issues"] ?? [], $entry["testing"]["warnings"] ?? []);
                echo "   ", $entry["id"], " ", $entry["level"], " ", $outcome, ": ", implode(" | ", array_map(fn ($m) => is_array($m) ? json_encode($m) : $m, $messages)), "\n";
            }
        }
    }' "$REPORTS"
  rm -rf "$REPORTS"
done
