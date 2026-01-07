#!/bin/bash

# Variables
REMOTE_USER="ec2-user"
REMOTE_HOST="3.18.76.187"
REMOTE_BASE_PATH="/home/ec2-user/olaportal/api"
SSH_KEY_PATH="/Users/tu/Desktop/Workspace/teamsable/creds/tu-mac.pem"
CONTROL_PATH="~/.ssh/ctrl-staging-%C"

# Check if any arguments are provided
if [ $# -eq 0 ]; then
    echo "Error: Please provide one or more filenames as arguments"
    echo "Usage: $0 filename [filename2 ...]"
    echo "       $0 ./*.php"
    exit 1
fi

# Use a single SSH connection for all operations
echo "Establishing SSH connection to staging..."
ssh -i "${SSH_KEY_PATH}" \
    -o ControlMaster=auto \
    -o ControlPath=${CONTROL_PATH} \
    -o ControlPersist=yes \
    "${REMOTE_USER}@${REMOTE_HOST}" true || {
        echo "Error: Failed to establish SSH connection"
        exit 1
    }

# Process each file
for FILENAME in "$@"; do
    echo "Processing file: $FILENAME"
    
    # Validate that file exists
    if [ ! -f "$FILENAME" ]; then
        echo "Error: File $FILENAME does not exist, skipping"
        continue
    fi
    
    LOCAL_BACKUP="${FILENAME}.staging.bk"
    
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
    
    # Ensure remote directory exists if file is in subdirectory
    if [[ "$FILENAME" == */* ]]; then
        echo "Ensuring remote directory exists for $FILENAME..."
        ssh -i "${SSH_KEY_PATH}" -o ControlPath=${CONTROL_PATH} "${REMOTE_USER}@${REMOTE_HOST}" "mkdir -p ${REMOTE_DIR}" || {
            echo "Error: Failed to create remote directory for $FILENAME"
            continue
        }
    fi
    
    # Download remote file as backup (override if exists)
    echo "Downloading remote file as backup..."
    if ssh -i "${SSH_KEY_PATH}" -o ControlPath=${CONTROL_PATH} "${REMOTE_USER}@${REMOTE_HOST}" "test -f ${REMOTE_PATH}"; then
        # Create local backup directory if needed
        if [[ "$FILENAME" == */* ]]; then
            mkdir -p "$(dirname "${LOCAL_BACKUP}")"
        fi
        scp -i "${SSH_KEY_PATH}" -o ControlPath=${CONTROL_PATH} "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}" "${LOCAL_BACKUP}" || {
            echo "Error: Failed to download remote file for backup for $FILENAME"
            continue
        }
        echo "Remote file backed up to: ${LOCAL_BACKUP}"
    else
        echo "Remote file does not exist on staging - skipping backup"
    fi
    
    # Upload current file to remote
    echo "Uploading $FILENAME to staging..."
    scp -i "${SSH_KEY_PATH}" -o ControlPath=${CONTROL_PATH} "${FILENAME}" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}" || {
        echo "Error: Failed to upload $FILENAME to staging"
        continue
    }
    
    echo "Success! File operations completed for $FILENAME:"
    if [ -f "${LOCAL_BACKUP}" ]; then
        echo "1. Remote file backed up to: ${LOCAL_BACKUP}"
    fi
    echo "2. Local ${FILENAME} uploaded to ${REMOTE_PATH}"
    echo "-------------------------------------------"
done

# Clean up SSH control connection
ssh -O exit -o ControlPath=${CONTROL_PATH} "${REMOTE_USER}@${REMOTE_HOST}" 2>/dev/null || true

echo "Deployment to staging complete!" 