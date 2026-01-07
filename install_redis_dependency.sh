#!/bin/bash

# Install Redis dependency for rate limiting
echo "Installing Predis Redis client..."

# Check if composer is available
if ! command -v composer &> /dev/null; then
    echo "Composer not found. Please install Composer first."
    exit 1
fi

# Install the Predis library
composer require predis/predis

echo "Redis dependency installed successfully!"
echo "Rate limiting is now enabled with 30 requests per second per IP."
