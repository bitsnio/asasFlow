#!/bin/bash
# =============================================================================
# Fix ASASFLOW Namespaces: Bistsnio\AsasFlow → Bitsnio\AsasFlow
# =============================================================================
# Run from package root: bash fix-namespaces.sh
# =============================================================================

set -e

echo "🔧 Fixing namespaces from Bistsnio\\AsasFlow to Bitsnio\\AsasFlow..."

# Use # as sed delimiter to avoid conflicts with backslashes

# =============================================================================
# 1. FIX PHP FILES IN src/Features/Cache
# =============================================================================

echo "📁 Processing src/Features/Cache..."

find src/Features/Cache -type f -name "*.php" | while read -r file; do
    sed -i.bak 's#Bistsnio\\AsasFlow#Bitsnio\\AsasFlow#g' "$file"
    rm -f "$file.bak"
    echo "  ✓ $file"
done

# =============================================================================
# 2. FIX PHP FILES IN src/Features/Tenancy
# =============================================================================

echo "📁 Processing src/Features/Tenancy..."

find src/Features/Tenancy -type f -name "*.php" | while read -r file; do
    sed -i.bak 's#Bistsnio\\AsasFlow#Bitsnio\\AsasFlow#g' "$file"
    rm -f "$file.bak"
    echo "  ✓ $file"
done

# =============================================================================
# 3. FIX PHP FILES IN src/Generators/Cache
# =============================================================================

echo "📁 Processing src/Generators/Cache..."

find src/Generators/Cache -type f -name "*.php" | while read -r file; do
    sed -i.bak 's#Bistsnio\\AsasFlow#Bitsnio\\AsasFlow#g' "$file"
    rm -f "$file.bak"
    echo "  ✓ $file"
done

# =============================================================================
# 4. FIX ROOT SERVICE PROVIDER
# =============================================================================

# echo "📁 Processing src/AsasFlowServiceProvider.php..."

# if [ -f "src/AsasFlowServiceProvider.php" ]; then
#     sed -i.bak 's#Bistsnio\\AsasFlow#Bitsnio\\AsasFlow#g' src/AsasFlowServiceProvider.php
#     rm -f src/AsasFlowServiceProvider.php.bak
#     echo "  ✓ src/AsasFlowServiceProvider.php"
# fi

# =============================================================================
# 5. FIX STUB FILES
# =============================================================================

echo "📁 Processing stub files..."

find src/Features/Cache/Console/Commands/Stubs -type f -name "*.stub" | while read -r file; do
    sed -i.bak 's#Bistsnio\\AsasFlow#Bitsnio\\AsasFlow#g' "$file"
    rm -f "$file.bak"
    echo "  ✓ $file"
done

# =============================================================================
# 6. FIX CONFIG FILE
# =============================================================================

echo "📁 Processing config/asasflow.php..."

if [ -f "config/asasflow.php" ]; then
    sed -i.bak 's#Bistsnio\\AsasFlow#Bitsnio\\AsasFlow#g' config/asasflow.php
    rm -f config/asasflow.php.bak
    echo "  ✓ config/asasflow.php"
fi

# =============================================================================
# 7. FIX MIGRATIONS
# =============================================================================

echo "📁 Processing migrations..."

find database/migrations -type f -name "*.php" | while read -r file; do
    sed -i.bak 's#Bistsnio\\AsasFlow#Bitsnio\\AsasFlow#g' "$file"
    rm -f "$file.bak"
    echo "  ✓ $file"
done

# =============================================================================
# 8. VERIFY NO REMAINING INSTANCES
# =============================================================================

echo ""
echo "🔍 Verifying no remaining 'Bistsnio\\AsasFlow' references..."

REMAINING=$(grep -r "Bistsnio\\\\AsasFlow" --include="*.php" --include="*.stub" src/ config/ database/ 2>/dev/null || true)

if [ -n "$REMAINING" ]; then
    echo "⚠️  Found remaining references:"
    echo "$REMAINING"
else
    echo "✅ All namespaces fixed successfully!"
fi

# =============================================================================
# 9. SUMMARY
# =============================================================================

echo ""
echo "🎉 Namespace fix complete!"
echo ""
echo "Run 'composer dump-autoload' to refresh autoloading."