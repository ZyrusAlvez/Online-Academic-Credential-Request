#!/usr/bin/env bash
# Double-click (Run as a Program) or run from a terminal: deploys the `production` branch.
exec "$(dirname "$0")/deploy.sh" production
