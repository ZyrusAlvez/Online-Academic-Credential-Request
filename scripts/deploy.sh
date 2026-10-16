#!/usr/bin/env bash
# Deploys a GitHub branch to its server: `staging` or `production`.
# Usage: scripts/deploy.sh staging|production   (or double-click deploy-staging.sh / deploy-production.sh)
# Needs the `uphsl` SSH alias, or UPHSL_SSH_HOST set to another one (docs/deployment/02-environments.md).
set -uo pipefail

# Double-clicked from the file manager: reopen in a terminal so the output stays visible.
if [ ! -t 1 ] && command -v gnome-terminal >/dev/null; then
  exec gnome-terminal -- "$0" "$@"
fi

ENV="${1:-}"
HOST="${UPHSL_SSH_HOST:-uphsl}"

finish() {
  echo
  read -rp "Press Enter to close..." _
  exit "$1"
}

case "$ENV" in
  staging|production) ;;
  *) echo "Usage: $0 staging|production"; finish 1 ;;
esac

cd "$(dirname "$0")/.." || finish 1

if git fetch --quiet origin "$ENV" 2>/dev/null; then
  echo "Deploying to $ENV: $(git log -1 --format='%h %s (%an, %cr)' "origin/$ENV")"
fi

if [ "$ENV" = production ]; then
  read -rp "This updates the live system. Type 'production' to continue: " answer
  if [ "$answer" != production ]; then
    echo "Cancelled."
    finish 1
  fi
fi

if ssh -t "$HOST" "uphsl-deploy $ENV"; then
  echo
  echo "Deploy to $ENV finished."
  finish 0
fi

echo
echo "Deploy to $ENV FAILED. Read the messages above."
finish 1
