#!/bin/bash

# Simple helper to execute a remote PHP script by filename with parameters.
# Usage:
#   ./execute_remote_php.sh reconcile_order_items.php --from-id=1 --to-id=2
#   ./execute_remote_php.sh subdir/script.php "param value"
#
# Requirements:
# - SSH key at: /Users/tu/.ssh/id_portal_api_v1
# - Remote: tu@3.18.227.3
# - Base path: /home/olaportal/olaportal/api

if [ "$#" -lt 2 ]; then
    echo "Error: Expect at least 2 arguments (php_filename and params)."
    echo "Usage: $0 <php_filename> <params...>"
    exit 1
fi

PHP_FILENAME="$1"
shift
PHP_PARAMS=("$@")

REMOTE_USER="tu"
REMOTE_HOST="3.18.227.3"
REMOTE_BASE_PATH="/home/olaportal/olaportal/api"
SSH_KEY="/Users/tu/.ssh/id_portal_api_v1"

# Build full remote path (supports subdirectories in PHP_FILENAME)
REMOTE_PATH="${REMOTE_BASE_PATH}/${PHP_FILENAME}"

# Execute the PHP script remotely with all provided parameters.
# Safely quote each parameter to handle spaces/special characters.
quoted_params=()
for p in "${PHP_PARAMS[@]}"; do
    quoted_params+=("$(printf '%q' "$p")")
done

ssh -i "${SSH_KEY}" "${REMOTE_USER}@${REMOTE_HOST}" "php ${REMOTE_PATH} ${quoted_params[*]}"


