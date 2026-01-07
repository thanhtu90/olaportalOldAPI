#!/bin/bash

# MySQL Port Forwarding Script
# This script forwards localhost:3306 to prod-portal:3306 using SSH tunnel

set -e  # Exit on any error

echo "🔌 Starting MySQL port forwarding..."
echo "📡 Forwarding localhost:3306 → prod-portal:3306"
echo ""
echo "Press Ctrl+C to stop the tunnel"
echo ""

# Check if port 3306 is already in use locally
if lsof -Pi :3306 -sTCP:LISTEN -t >/dev/null 2>&1 ; then
    echo "⚠️  Warning: Port 3306 is already in use on localhost"
    echo "   You may need to stop the local MySQL service or use a different local port"
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Create SSH tunnel with local port forwarding
# -L [local_port]:[remote_host]:[remote_port] [user]@[ssh_host]
# -N: Don't execute remote commands, just forward ports
# -f: Run in background (commented out, running in foreground for visibility)
# -v: Verbose mode (optional, for debugging)

echo "🔗 Establishing SSH tunnel..."
ssh -L 3306:localhost:3306 -N tu@prod-portal

# If we get here, the tunnel was closed
echo ""
echo "✅ SSH tunnel closed"

