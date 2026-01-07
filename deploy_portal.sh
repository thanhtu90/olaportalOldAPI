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
REMOTE_HOST="3.18.227.3"
REMOTE_BASE_PATH="/home/olaportal/olaportal/api"
LOCAL_BACKUP="${FILENAME}.bk"
CONTROL_PATH="~/.ssh/ctrl-%C"

# Validate that file exists
if [ ! -f "$FILENAME" ]; then
    echo "Error: File $FILENAME does not exist"
    exit 1
fi

# Get the remote path
if [[ "$FILENAME" == */* ]]; then
    # File is in a subdirectory
    REMOTE_PATH="${REMOTE_BASE_PATH}/${FILENAME}"
    REMOTE_DIR=$(dirname "${REMOTE_PATH}")
else
    # File is in current directory
    REMOTE_PATH="${REMOTE_BASE_PATH}/${FILENAME}"
    REMOTE_DIR="${REMOTE_BASE_PATH}"
fi

# Start SSH agent and add key
eval $(ssh-agent -s)
ssh-add /Users/tu/.ssh/id_portal_api_v1

# Use a single SSH connection for all operations
echo "Establishing SSH connection..."
ssh -i /Users/tu/.ssh/id_portal_api_v1 \
    -o ControlMaster=auto \
    -o ControlPath=${CONTROL_PATH} \
    -o ControlPersist=yes \
    "${REMOTE_USER}@${REMOTE_HOST}" true

# Ensure remote directory exists if file is in subdirectory
if [[ "$FILENAME" == */* ]]; then
    echo "Ensuring remote directory exists..."
    ssh -i /Users/tu/.ssh/id_portal_api_v1 -o ControlPath=${CONTROL_PATH} "${REMOTE_USER}@${REMOTE_HOST}" "mkdir -p ${REMOTE_DIR}" || {
        echo "Error: Failed to create remote directory"
        exit 1
    }
fi

# Download remote file as backup (override if exists)
echo "Downloading remote file as backup..."
if ssh -i /Users/tu/.ssh/id_portal_api_v1 -o ControlPath=${CONTROL_PATH} "${REMOTE_USER}@${REMOTE_HOST}" "test -f ${REMOTE_PATH}"; then
    # Create local backup directory if needed
    if [[ "$FILENAME" == */* ]]; then
        mkdir -p "$(dirname "${LOCAL_BACKUP}")"
    fi
    scp -i /Users/tu/.ssh/id_portal_api_v1 -o ControlPath=${CONTROL_PATH} "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}" "${LOCAL_BACKUP}" || {
        echo "Error: Failed to download remote file for backup"
        exit 1
    }
    echo "Remote file backed up to: ${LOCAL_BACKUP}"
else
    echo "Remote file does not exist - skipping backup"
fi

# Upload current file to remote
echo "Uploading current file to remote..."
scp -i /Users/tu/.ssh/id_portal_api_v1 -o ControlPath=${CONTROL_PATH} "${FILENAME}" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}" || {
    echo "Error: Failed to upload file to remote"
    exit 1
}

echo "Success! File operations completed:"
if [ -f "${LOCAL_BACKUP}" ]; then
    echo "1. Remote file backed up to: ${LOCAL_BACKUP}"
fi
echo "2. Local ${FILENAME} uploaded to ${REMOTE_PATH}"

# Clean up SSH control connection
ssh -O exit -o ControlPath=${CONTROL_PATH} "${REMOTE_USER}@${REMOTE_HOST}" 