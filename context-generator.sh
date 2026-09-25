#!/bin/bash

# ==========================================================
# Laravel AI Context Generator (History & Targeted)
# ==========================================================

# ----------------------------------------------------------
# CONFIGURATION VARIABLES (Modify these as needed)
# ----------------------------------------------------------

# 1. INCLUDE_DIRS: Directories to process for your CURRENT requirement.
#    Leave as empty array () to create context for the WHOLE PROJECT.
INCLUDE_DIRS=("src/Console/Commands/ControllerCommands")

# 2. EXTRA_EXCLUDE_DIRS: Directories to SKIP. 
#    These will be removed from BOTH the directory tree AND file scanning.
#    Works even if FULL_PROJECT_STRUCTURE is true!
EXTRA_EXCLUDE_DIRS=("stubs" "docs" "packages")

# 3. INCLUDE_FILES: Specific files to force-include even if their folder is skipped.
INCLUDE_FILES=("")
# 4. CONTEXT_DIR: Directory where generated context files are saved.
CONTEXT_DIR=".ai_contexts"

# 5. FULL_PROJECT_STRUCTURE: 
#    Set to true to show the FULL project directory tree (minus EXCLUDE_DIRS).
#    Set to false to only show the structure of the directories in INCLUDE_DIRS.
FULL_PROJECT_STRUCTURE=false

# ----------------------------------------------------------

# Check if a description argument was provided
if [ -z "$1" ]; then
    echo "❌ Error: Please provide a description for this context."
    echo "👉 Usage: ./generate_ai_context.sh \"Fixing settings module API\""
    echo ""
    echo "📁 Existing contexts in $CONTEXT_DIR:"
    if [ -d "$CONTEXT_DIR" ] && [ "$(ls -A $CONTEXT_DIR 2>/dev/null)" ]; then
        ls -1 "$CONTEXT_DIR" | sed 's/^/   - /'
    else
        echo "   (none yet)"
    fi
    exit 1
fi

DESCRIPTION="$1"
FILENAME=$(echo "$DESCRIPTION" | tr '[:upper:]' '[:lower:]' | sed 's/ /-/g' | sed 's/[^a-z0-9-]//g')

mkdir -p "$CONTEXT_DIR"
OUTPUT_FILE="$CONTEXT_DIR/$FILENAME.md"

if [ -f "$OUTPUT_FILE" ]; then
    echo "⚠️  Overwriting existing context file: $OUTPUT_FILE"
else
    echo "🤖 Generating new AI project context file: $OUTPUT_FILE"
fi

MAX_FILE_SIZE_KB=256

# Combine default and user-defined exclusions
DEFAULT_EXCLUDE_DIRS=(".git" "vendor" "node_modules" "storage" "dist" "build" ".idea" ".vscode" "$CONTEXT_DIR")
ALL_EXCLUDE_DIRS=("${DEFAULT_EXCLUDE_DIRS[@]}" "${EXTRA_EXCLUDE_DIRS[@]}")

# Set search paths
if [ ${#INCLUDE_DIRS[@]} -eq 0 ]; then
    SEARCH_PATHS=(".")
else
    SEARCH_PATHS=("${INCLUDE_DIRS[@]}")
fi

# Build the prune arguments for the find command using -path
# This correctly matches nested paths like "packages/laravel_modules_old"
PRUNE_ARGS=()
for dir in "${ALL_EXCLUDE_DIRS[@]}"; do
    if [ ${#PRUNE_ARGS[@]} -gt 0 ]; then
        PRUNE_ARGS+=(-o)
    fi
    PRUNE_ARGS+=(-path "*/$dir")
done

echo ""
if [ "$FULL_PROJECT_STRUCTURE" = true ]; then
    echo "🌲 Project Structure Mode: FULL"
    echo "🚫 Skipping from Tree & Files: ${ALL_EXCLUDE_DIRS[*]}"
else
    echo "🌲 Project Structure Mode: TARGETED ONLY"
    echo "🔍 Targeting: ${SEARCH_PATHS[*]}"
fi
echo ""

# Start writing to file
echo "# Laravel Project Context" > "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"
echo "**Task:** $DESCRIPTION" >> "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"

# 1. Inject Background Context if it exists
BACKGROUND_FILE="ai_background.md"
if [ -f "$BACKGROUND_FILE" ]; then
    echo "✅ Including background context from $BACKGROUND_FILE..."
    echo "## Background and Purpose" >> "$OUTPUT_FILE"
    echo "" >> "$OUTPUT_FILE"
    cat "$BACKGROUND_FILE" >> "$OUTPUT_FILE"
    echo "" >> "$OUTPUT_FILE"
    echo "---" >> "$OUTPUT_FILE"
    echo "" >> "$OUTPUT_FILE"
fi

# 2. Generate Directory Structure
echo "📁 Generating directory structure..."
echo "## Directory Structure" >> "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"
echo '```' >> "$OUTPUT_FILE"

# Determine if we should show the full project tree or just the included paths
if [ "$FULL_PROJECT_STRUCTURE" = true ]; then
    STRUCTURE_PATHS=(".")
else
    STRUCTURE_PATHS=("${SEARCH_PATHS[@]}")
fi

# Run the find command (Prunes the ALL_EXCLUDE_DIRS from the tree)
if [ ${#PRUNE_ARGS[@]} -gt 0 ]; then
    find "${STRUCTURE_PATHS[@]}" -type d \( "${PRUNE_ARGS[@]}" \) -prune -o -type d -print | sort | sed 's|^\./||' >> "$OUTPUT_FILE"
else
    find "${STRUCTURE_PATHS[@]}" -type d -print | sort | sed 's|^\./||' >> "$OUTPUT_FILE"
fi

echo '```' >> "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"
echo "---" >> "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"

# 3. Process and Append File Contents
echo "📝 Processing project files..."
echo "## Project Files" >> "$OUTPUT_FILE"
echo "" >> "$OUTPUT_FILE"

# Helper function to process a single file
process_file() {
    local file="$1"
    local base_name
    local file_size
    local ext
    local lang
    local clean_path
    
    case "$file" in
        *.png|*.jpg|*.jpeg|*.gif|*.svg|*.ico|*.pdf|*.zip|*.tar|*.gz|*.sqlite|*.log|*.lock|*.min.js|*.min.css|*.map)
            return
            ;;
    esac

    base_name=$(basename "$file")
    case "$base_name" in
        .env|.env.example|composer.lock|package-lock.json|yarn.lock|generate_ai_context.sh|ai_background.md)
            return
            ;;
    esac

    file_size=$(wc -c < "$file" | tr -d ' ')
    if [ "$file_size" -gt $((MAX_FILE_SIZE_KB * 1024)) ]; then
        return
    fi

    ext="${file##*.}"
    lang=""
    case "$ext" in
        php) lang="php" ;; js) lang="javascript" ;; ts) lang="typescript" ;; vue) lang="vue" ;;
        css) lang="css" ;; scss) lang="scss" ;; html) lang="html" ;; json) lang="json" ;;
        md) lang="markdown" ;; txt) lang="text" ;; sh) lang="bash" ;; yml|yaml) lang="yaml" ;; *) lang="" ;;
    esac

    clean_path="${file#./}"

    echo "   - Appending: $clean_path"
    echo "### $clean_path" >> "$OUTPUT_FILE"
    echo "" >> "$OUTPUT_FILE"
    echo '```'$lang >> "$OUTPUT_FILE"
    cat "$file" >> "$OUTPUT_FILE"
    echo "" >> "$OUTPUT_FILE"
    echo '```' >> "$OUTPUT_FILE"
    echo "" >> "$OUTPUT_FILE"
}

# Find files logic (Only searches inside INCLUDE_DIRS, and skips ALL_EXCLUDE_DIRS)
if [ ${#PRUNE_ARGS[@]} -gt 0 ]; then
    find "${SEARCH_PATHS[@]}" -type d \( "${PRUNE_ARGS[@]}" \) -prune -o -type f -print0 | sort -z | while IFS= read -r -d '' file; do
        process_file "$file"
    done
else
    find "${SEARCH_PATHS[@]}" -type f -print0 | sort -z | while IFS= read -r -d '' file; do
        process_file "$file"
    done
fi

# 4. Append specific manually included files (e.g., from docs)
if [ ${#INCLUDE_FILES[@]} -gt 0 ]; then
    echo "📎 Checking manually included files..."
    for md_file in "${INCLUDE_FILES[@]}"; do
        if [ -f "$md_file" ]; then
            process_file "$md_file"
        else
            echo "⚠️ Warning: Specific file not found: $md_file"
        fi
    done
fi

echo ""
echo "✅ Done! Context saved to $OUTPUT_FILE"