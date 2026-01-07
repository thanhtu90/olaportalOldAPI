#!/bin/bash

# Check if filename argument is provided
if [ -z "$1" ]; then
    echo "Error: Please provide a filename as argument"
    echo "Usage: $0 filename"
    exit 1
fi

# Variables
FILENAME=$1
REMOTE_USER="tu"
REMOTE_HOST="is.teamsable.com"
REMOTE_BASE_PATH="/var/www/html/posliteweb/dist/api"
DATE_SUFFIX=$(date '+%d_%m_%Y')
TEMP_REMOTE_PATH="/tmp/deploy_temp_${RANDOM}"

# Validate that local file exists if we're not downloading first
if [ ! -f "$FILENAME" ]; then
    echo "Warning: Local file $FILENAME does not exist - will attempt to download from remote"
fi

# Get the remote path
if [[ "$FILENAME" == */* ]]; then
    # File is in a subdirectory
    REMOTE_PATH="${REMOTE_BASE_PATH}/${FILENAME}"
    REMOTE_DIR=$(dirname "${REMOTE_PATH}")
    BACKUP_FILE="${FILENAME%.*}_${DATE_SUFFIX}.${FILENAME##*.}"
else
    # File is in current directory
    REMOTE_PATH="${REMOTE_BASE_PATH}/${FILENAME}"
    REMOTE_DIR="${REMOTE_BASE_PATH}"
    BACKUP_FILE="${FILENAME%.*}_${DATE_SUFFIX}.${FILENAME##*.}"
fi

# Create backup directory if needed for files in subdirectories
if [[ "$FILENAME" == */* ]]; then
    mkdir -p "$(dirname "${BACKUP_FILE}")"
fi

# Create remote directory if it doesn't exist
if [[ "$FILENAME" == */* ]]; then
    echo "Ensuring remote directory exists..."
    ssh -t "${REMOTE_USER}@${REMOTE_HOST}" "sudo mkdir -p ${REMOTE_DIR} && sudo chown www-data:www-data ${REMOTE_DIR}"
fi

# Download remote file as backup if it exists
echo "Checking if remote file exists..."
if ssh "${REMOTE_USER}@${REMOTE_HOST}" "[ -f ${REMOTE_PATH} ]"; then
    echo "Downloading remote file as backup..."
    scp "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}" "${BACKUP_FILE}" || {
        echo "Error: Failed to download remote file for backup"
        exit 1
    }
    echo "Remote file backed up to: ${BACKUP_FILE}"
else
    echo "Remote file does not exist - skipping backup"
fi

# Check if we have a local file to upload
if [ -f "$FILENAME" ]; then
    # Upload current file to remote using a temporary file and sudo
    echo "Uploading current file to remote..."
    
    # First upload to temporary location
    scp "${FILENAME}" "${REMOTE_USER}@${REMOTE_HOST}:${TEMP_REMOTE_PATH}" || {
        echo "Error: Failed to upload file to temporary location"
        exit 1
    }
    
    # Then use sudo to move it to final destination with TTY allocation
    ssh -t "${REMOTE_USER}@${REMOTE_HOST}" "sudo mv ${TEMP_REMOTE_PATH} ${REMOTE_PATH} && sudo chown www-data:www-data ${REMOTE_PATH} && sudo chmod 644 ${REMOTE_PATH}" || {
        echo "Error: Failed to move file to final destination"
        # Cleanup temp file on failure
        ssh "${REMOTE_USER}@${REMOTE_HOST}" "rm -f ${TEMP_REMOTE_PATH}"
        exit 1
    }
    
    echo "Success! File operations completed:"
    [ -f "${BACKUP_FILE}" ] && echo "1. Remote file backed up to: ${BACKUP_FILE}"
    echo "2. Local ${FILENAME} uploaded to ${REMOTE_PATH}"
else
    if [ -f "${BACKUP_FILE}" ]; then
        echo "No local file to upload. Backup completed: ${BACKUP_FILE}"
    else
        echo "No local file to upload and no remote file exists."
    fi
fi 