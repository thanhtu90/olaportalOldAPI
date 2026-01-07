#!/bin/bash

# Check if a filename was provided
if [ -z "$1" ]; then
    echo "Error: Please provide a migration file name"
    echo "Usage: ./deploy_migration.sh <migration_file_name>"
    exit 1
fi

# Source directory
SOURCE_DIR="/Users/tu/Desktop/Workspace/teamsable/olaportalAPI/db/migrations"
# Remote details
REMOTE_USER="tu"
REMOTE_HOST="3.18.227.3"
REMOTE_PATH="/home/olaportal/olaportal/api/db/migrations"

# Check if the file exists
if [ ! -f "$SOURCE_DIR/$1" ]; then
    echo "Error: Migration file '$1' not found in $SOURCE_DIR"
    exit 1
fi

# Copy the file
echo "Copying migration file '$1' to remote server..."
scp "$SOURCE_DIR/$1" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_PATH/"

# Check if the copy was successful
if [ $? -eq 0 ]; then
    echo "Successfully copied migration file to remote server"
else
    echo "Error: Failed to copy migration file"
    exit 1
fi