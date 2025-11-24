#!/bin/bash

# Check if mogrify is installed
if ! command -v mogrify &> /dev/null; then
    echo "Error: mogrify (ImageMagick) is not installed."
    exit 1
fi

# Load .env file
if [ -f .env ]; then
    # Source the .env file to get variables
    # Using set -a to export variables automatically
    set -a
    . .env
    set +a
else
    echo ".env file not found!"
    exit 1
fi

# Check if required variables are set
if [ -z "$SOURCE_DIR" ] || [ -z "$TEMPLATE_DIR" ] || [ -z "$TEMPLATE" ]; then
    echo "Error: Missing required configuration in .env"
    echo "Ensure SOURCE_DIR, TEMPLATE_DIR, and TEMPLATE are set."
    exit 1
fi

echo "Configuration loaded:"
echo "Content Directory: $SOURCE_DIR"
echo "Template Directory: $TEMPLATE_DIR/$TEMPLATE"

# Create originals directory if it doesn't exist
ORIGINALS_DIR="originals"
if [ ! -d "$ORIGINALS_DIR" ]; then
    echo "Creating $ORIGINALS_DIR directory..."
    mkdir -p "$ORIGINALS_DIR"
fi

echo "Starting conversion..."

process_directory() {
    local dir="$1"
    if [ -d "$dir" ]; then
        echo "Processing $dir..."
        find "$dir" -name "*.png" -print0 | while IFS= read -r -d '' file; do
            echo "Converting $file..."
            mogrify -format jpg -resize 1920x "$file"

            if [ $? -eq 0 ]; then
                # Create the directory structure in originals
                # This preserves the path (e.g., content/assets/images/) inside originals/
                target_dir="$ORIGINALS_DIR/$(dirname "$file")"
                mkdir -p "$target_dir"

                echo "Moving $file to $target_dir..."
                mv "$file" "$target_dir/"
            else
                echo "Failed to convert $file"
            fi
        done
    else
        echo "Warning: Directory $dir not found."
    fi
}

# Process Content Directory
process_directory "$SOURCE_DIR"

# Process Template Directory
process_directory "$TEMPLATE_DIR/$TEMPLATE"

echo "Conversion complete."
