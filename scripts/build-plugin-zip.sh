#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
rm -f tn-other-plugins.zip
zip -qr tn-other-plugins.zip tn-other-plugins -x '*.DS_Store' -x '__MACOSX/*'
unzip -tq tn-other-plugins.zip
