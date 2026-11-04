#!/usr/bin/env bash
# Fails when a tracked file holds something that looks like a secret or an internal address (WR-12): tokens of the
# Hub, the AI services, GitHub, AWS and Cloudflare, private IP ranges and the owner's own hosts. Push protection on
# GitHub only knows the common token shapes; this also catches the addresses.
set -uo pipefail
cd "$(dirname "$0")/.."
patterns=(
    'pnl_[A-Za-z0-9]{16,}'
    'sk-(ant-|proj-)?[A-Za-z0-9_-]{20,}'
    'AIza[0-9A-Za-z_-]{35}'
    'AKIA[0-9A-Z]{16}'
    'gh[pousr]_[A-Za-z0-9]{30,}'
    'Bearer [A-Za-z0-9._-]{24,}'
    'CLOUDFLARE_API_TOKEN=[^[:space:]]+'
    '(^|[^0-9.])(10\.[0-9]{1,3}|192\.168|172\.(1[6-9]|2[0-9]|3[01]))\.[0-9]{1,3}\.[0-9]{1,3}([^0-9.]|$)'
    '[a-z0-9-]+\.deckzwabbers\.nl'
)
# Made-up examples that look like the patterns on purpose, one per line (for example the Ollama placeholder address).
allowed=$(grep -vE '^(#|$)' tests/secrets-allow.txt 2>/dev/null || true)
found=0
for pattern in "${patterns[@]}"; do
    # This file names the patterns; tests/fixtures may hold made-up examples on purpose.
    hits=$(git grep -nIE "$pattern" -- . ':!tests/secrets.sh' ':!tests/secrets-allow.txt' ':!tests/fixtures' 2>/dev/null || true)
    if [ -n "$hits" ] && [ -n "$allowed" ]; then
        hits=$(grep -vF "$allowed" <<<"$hits" || true)
    fi
    if [ -n "$hits" ]; then
        printf 'FAIL something that looks like a secret or an internal address (%s):\n%s\n' "$pattern" "$(cut -c1-160 <<<"$hits")"
        found=1
    fi
done
[ "$found" = 0 ] && echo 'PASS no secrets or internal addresses in the tracked files'
exit "$found"
