#!/usr/bin/env bash

set -euo pipefail

createdb --username "$POSTGRES_USER" "$POSTGRES_TEST_DATABASE"
