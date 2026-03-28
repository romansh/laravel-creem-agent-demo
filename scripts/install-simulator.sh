#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SIMULATOR_DIR="${ROOT_DIR}/creem-simulator"
SIMULATOR_PACKAGE="${CREEM_SIMULATOR_PACKAGE:-romansh/laravel-creem-store-simulator}"
SIMULATOR_VERSION="${CREEM_SIMULATOR_VERSION:-^1.0}"

if ! command -v composer >/dev/null 2>&1; then
  echo "composer is required to install ${SIMULATOR_PACKAGE}" >&2
  exit 1
fi

if [ -f "${SIMULATOR_DIR}/composer.json" ] && grep -q '"name": "romansh/laravel-creem-store-simulator"' "${SIMULATOR_DIR}/composer.json"; then
  echo "Simulator already installed at ${SIMULATOR_DIR}"
  exit 0
fi

if [ -e "${SIMULATOR_DIR}" ] && [ -n "$(find "${SIMULATOR_DIR}" -mindepth 1 -maxdepth 1 2>/dev/null)" ]; then
  echo "${SIMULATOR_DIR} already exists and is not a generated laravel-creem-store-simulator install." >&2
  echo "Remove it first or move it aside, then rerun this script." >&2
  exit 1
fi

rm -rf "${SIMULATOR_DIR}"

composer create-project \
  --no-interaction \
  --prefer-dist \
  "${SIMULATOR_PACKAGE}" \
  "${SIMULATOR_DIR}" \
  "${SIMULATOR_VERSION}"

echo "Installed ${SIMULATOR_PACKAGE} into ${SIMULATOR_DIR}"