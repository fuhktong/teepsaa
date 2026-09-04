#!/bin/bash
# ── teepsaa deploy over SSH (replaces the lftp/FTP deploy) ────────────
# Why: plain FTP on port 21 with parallel connections trips Hostinger's
# automatic firewall ban, which blocks the whole source IP on every port.
# That is unusable from shared/coffee-shop wifi. This uses one encrypted
# SSH connection with key auth instead — no password attempts to fail,
# no connection flood to detect.
#
# Auth is by key only (see ~/.ssh/config host block "teepsaa"), so there
# are no credentials in this file and it is safe to commit.
#
# Usage:
#   ./deploy-sftp.sh --dry-run   # show what would upload, change nothing
#   ./deploy-sftp.sh             # upload
set -euo pipefail

REMOTE="teepsaa"                                  # ~/.ssh/config host alias
REMOTE_PATH="domains/teepsaa.com/public_html"     # relative to the SSH home dir
LOCAL_PATH="./"

# --dry-run passes straight through to rsync.
DRY=""
if [ "${1:-}" = "--dry-run" ] || [ "${1:-}" = "-n" ]; then
    DRY="--dry-run"
    echo "DRY RUN — nothing will be uploaded."
fi

# Same exclude list as the old lftp mirror, in rsync glob syntax rather
# than lftp regex. The three config/ files hold production-only values
# that live ONLY on the server — never let them be overwritten.
rsync -rlptvz $DRY \
    --exclude '.git/' \
    --exclude '.github/' \
    --exclude '.vscode/' \
    --exclude '.claude/' \
    --exclude 'node_modules/' \
    --exclude '.env' \
    --exclude '.DS_Store' \
    --exclude '.gitignore' \
    --exclude 'deploycode.txt' \
    --exclude 'deploy-sftp.sh' \
    --exclude 'print.md' \
    --exclude 'CLAUDE.md' \
    --exclude 'uploads/' \
    --exclude 'z-checklists/' \
    --exclude 'database/' \
    --exclude 'config/db.php' \
    --exclude 'config/smtp.php' \
    --exclude 'config/mapbox.php' \
    --exclude 'mail.log' \
    -e ssh \
    "$LOCAL_PATH" "$REMOTE:$REMOTE_PATH/"

echo
echo "Done. No --delete is used, so nothing on the server is ever removed;"
echo "delete stale files by hand if you need to."
