#!/usr/bin/env bash
set -euo pipefail  # Exit on error, undefined vars, pipe failures

echo "🔧 Starting container build process..."

# Set up PATH for container environment
export PATH="$PATH:/app/vendor/bin:/app/.composer/vendor/bin"
export DEBIAN_FRONTEND=noninteractive

# Update system and install basic tools
echo "📦 Installing system packages..."
apt-get update -qq \
	&& apt-get install -y --no-install-recommends \
		less vim nano jq curl ca-certificates gnupg lsb-release

# Install Node.js from NodeSource repository
echo "📦 Installing Node.js..."
curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - \
	&& apt-get install -y --no-install-recommends nodejs

# Clean up apt cache
apt-get clean && rm -rf /var/lib/apt/lists/*

# Change to app directory for npm operations
cd /app || { echo "❌ Failed to change to /app directory"; exit 1; }

# Verify Node.js installation
echo "✅ Node.js version: $(node --version)"
echo "✅ npm version: $(npm --version)"

# Initialize npm project and install JavaScript testing dependencies
echo "📦 Setting up JavaScript testing environment..."
npm init -y --silent \
	&& npm install --save-dev --silent jest-environment-jsdom \
		jest@^29.0.0 \
		@jest/globals@^29.0.0 \
		jsdom@^22.0.0

echo "✅ Container build completed successfully!"
echo "📋 Installed packages:"
echo "   - Node.js $(node --version)"
echo "   - npm $(npm --version)"
echo "   - Jest testing framework"
echo "   - jsdom for DOM simulation"