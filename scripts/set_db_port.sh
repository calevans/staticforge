#!/bin/bash
# Get the current database port from Lando
DB_PORT=$(cd "$(dirname "$0")/.." && lando info --service database --format=json | jq -r '.[].external_connection.port')

# Get the full path to the project directory
PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

# Update .mcp.json with the current port
jq --arg port "$DB_PORT" '.database.port = ($port | tonumber)' "$PROJECT_DIR/.mcp.json" > "$PROJECT_DIR/.mcp.json.tmp" && \
mv "$PROJECT_DIR/.mcp.json.tmp" "$PROJECT_DIR/.mcp.json"

echo "Updated .mcp.json with database port: $DB_PORT"
