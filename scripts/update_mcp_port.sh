#!/bin/bash
# Get the current database port from Lando
DB_PORT=$(lando info --service database --format=json | jq -r '.[].external_connection.port')

# Get the directory of this script
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Update .mcp.json with the current port
jq --arg port "$DB_PORT" '.database.port = ($port | tonumber)' "$PROJECT_DIR/.mcp.json" > "$PROJECT_DIR/.mcp.json.tmp" && \
mv "$PROJECT_DIR/.mcp.json.tmp" "$PROJECT_DIR/.mcp.json"

echo "✅ Updated .mcp.json with database port: $DB_PORT"
