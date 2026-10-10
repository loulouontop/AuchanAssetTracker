#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/auchanassettracker-0.3.0.tar.gz}"
STAGE="$(mktemp -d)"
mkdir -p "$STAGE/auchanassettracker"
tar -C "$ROOT" \
  --exclude='.git' \
  --exclude='uploads' \
  --exclude='*.zip' \
  --exclude='*.tar.gz' \
  --exclude='.gitignore' \
  -cf - . | tar -C "$STAGE/auchanassettracker" -xf -
tar -czf "$OUT" -C "$STAGE" auchanassettracker
rm -rf "$STAGE"
echo "Built $OUT"
ls -lh "$OUT"
