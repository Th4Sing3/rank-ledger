#!/usr/bin/env sh
set -eu

# Migrations deliberately live in the one-shot `migrate` service. Running them
# here would let every web and worker replica race for schema ownership.
exec "$@"
