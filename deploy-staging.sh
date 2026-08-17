#!/usr/bin/env bash
#
# Deploy a static preview to Surge → https://staging.pauperduelcommander.fr
#
# Staging previews a branch (e.g. a redesign) BEFORE it reaches prod. `main` is
# prod and is never touched here. By default this deploys the checkout you run it
# from, so the natural workflow is: cd into your branch's worktree, run it.
#
#   cd .claude/worktrees/<branch>   # the branch you want to preview
#   /path/to/pdc/deploy-staging.sh  # builds THIS checkout, ships it to staging
#
# Or pass an explicit checkout path:
#   ./deploy-staging.sh .claude/worktrees/surge-sites-list-27c176
#
# Static only: PHP (validator + submissions) does NOT run on Surge, so the
# submission form is forced hidden here. The preview is marked noindex so search
# engines don't crawl it. It reflects the working tree at build time.
#
# Requires being logged into Surge once (`npx surge login`); the token is then
# stored locally, so this script never handles a credential.
#
set -euo pipefail

DOMAIN="staging.pauperduelcommander.fr"

# Which checkout to deploy: an explicit path argument, else the git worktree the
# script is invoked from — NOT the script's own location, so the main copy can
# ship whatever branch you're standing in.
if [ "$#" -ge 1 ]; then
  ROOT="$(cd "$1" && pwd)"
else
  ROOT="$(git rev-parse --show-toplevel)"
fi
SITE="$ROOT/site"

BRANCH="$(git -C "$ROOT" rev-parse --abbrev-ref HEAD 2>/dev/null || echo '?')"
echo "→ Checkout : $ROOT  (branche : $BRANCH)"
if [ "$BRANCH" = "main" ]; then
  echo "  ⚠ tu deploies main (= la prod) sur staging. Pour previsualiser une autre"
  echo "    version, lance ce script depuis le worktree de sa branche."
fi

echo "→ Build (staging: submissions off)…"
# Force the submission flag off for staging, whatever the local env holds.
( cd "$SITE" && PUBLIC_SUBMISSIONS_ENABLED= PUBLIC_TURNSTILE_SITE_KEY= npm run build )

STAGING="$(mktemp -d)"
trap 'rm -rf "$STAGING"' EXIT

echo "→ Copie statique (sans le PHP api/)…"
rsync -a --exclude 'api' "$SITE/dist/" "$STAGING/"

# noindex: never let the preview compete with prod in search results.
printf 'User-agent: *\nDisallow: /\n' > "$STAGING/robots.txt"

echo "→ Déploiement Surge → $DOMAIN…"
npx --yes surge "$STAGING" "$DOMAIN"

echo "✓ En ligne : https://$DOMAIN"
