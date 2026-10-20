#!/usr/bin/env bash
# Deploy the committed HEAD (frontend build + backend) to the LAN home server.
# Run from Git Bash on the PC: deploy/deploy.sh
set -euo pipefail

HOST="${DEPLOY_HOST:-marcos@192.168.0.236}"
APP_DIR="${DEPLOY_DIR:-/srv/homeapp}"
# Windows OpenSSH uses the Windows ssh-agent; Git Bash's own ssh does not.
SSH="${SSH:-/c/Windows/System32/OpenSSH/ssh.exe}"
SCP="${SCP:-/c/Windows/System32/OpenSSH/scp.exe}"

cd "$(git rev-parse --show-toplevel)"

if [ -n "$(git status --porcelain)" ]; then
  echo "Working tree is not clean; commit or stash before deploying." >&2
  exit 1
fi

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

# Same-origin API behind nginx, so no CORS and no host baked into the bundle.
# MSYS2_ENV_CONV_EXCL stops Git Bash from rewriting /api into a Windows path.
MSYS2_ENV_CONV_EXCL=VITE_API_BASE_URL VITE_API_BASE_URL=/api pnpm build
if grep -rq 'Program Files' dist; then
  echo "Build baked a Windows path into dist/; aborting deploy." >&2
  exit 1
fi
tar -czf "$work/web.tar.gz" -C dist .
git archive --format=tar.gz -o "$work/backend.tar.gz" HEAD:home-backend

"$SCP" -q -o BatchMode=yes "$(cygpath -w "$work/web.tar.gz")" "$(cygpath -w "$work/backend.tar.gz")" "$HOST:homeapp/"

"$SSH" -o BatchMode=yes "$HOST" "APP_DIR='$APP_DIR' bash -s" <<'EOF'
set -euo pipefail
cd "$APP_DIR"
rm -rf backend.new web.new backend.old web.old
mkdir backend.new web.new
tar -xzf ~/homeapp/backend.tar.gz -C backend.new
tar -xzf ~/homeapp/web.tar.gz -C web.new
ln -s ../shared/.env backend.new/.env
(cd backend.new && composer install --no-dev --optimize-autoloader --no-interaction --quiet)
if [ -d backend ]; then mv backend backend.old; fi
if [ -d web ]; then mv web web.old; fi
mv backend.new backend
mv web.new web
rm -rf backend.old web.old ~/homeapp/backend.tar.gz ~/homeapp/web.tar.gz
echo "Deployed to $APP_DIR"
EOF
