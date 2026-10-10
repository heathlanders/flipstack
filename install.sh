#!/bin/bash

# =============================================================================
# E-COMMERCE SAAS - NAMESPACE ANALYZER INSTALLATION SCRIPT
# =============================================================================
# This script installs the namespace analysis tools in your Laravel project
# =============================================================================

PROJECT_DIR="${1:-$(pwd)}"
TOOLS_DIR="$PROJECT_DIR/namespace_tools"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🚀 E-Commerce SaaS Namespace Analyzer Installation${NC}"
echo "=================================================="
echo "Target Project: $PROJECT_DIR"
echo "Tools Directory: $TOOLS_DIR"
echo ""

# Check if we're in a Laravel project
if [[ ! -f "$PROJECT_DIR/artisan" ]]; then
    echo -e "${RED}❌ Error: This doesn't appear to be a Laravel project${NC}"
    echo "   Make sure you're running this from your Laravel project root"
    echo "   or specify the correct path: $0 /path/to/laravel/project"
    exit 1
fi

echo -e "${GREEN}✅ Laravel project detected${NC}"
echo ""

# Create tools directory
echo "📁 Setting up tools directory..."
mkdir -p "$TOOLS_DIR"

# Copy all the analysis tools
echo "📋 Installing analysis tools..."

# Check if files exist and copy them
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

copy_if_exists() {
    local source="$1"
    local dest="$2"
    local name="$3"
    
    if [[ -f "$source" ]]; then
        cp "$source" "$dest"
        chmod +x "$dest" 2>/dev/null || true
        echo "  ✅ $name installed"
    else
        echo "  ❌ $name not found at $source"
    fi
}

# Install the scripts
copy_if_exists "$SCRIPT_DIR/quick_import_detector.sh" "$TOOLS_DIR/quick_import_detector.sh" "Quick Import Detector"
copy_if_exists "$SCRIPT_DIR/namespace_audit_script.sh" "$TOOLS_DIR/namespace_audit_script.sh" "Comprehensive Namespace Auditor"  
copy_if_exists "$SCRIPT_DIR/NamespaceAnalyzer.php" "$TOOLS_DIR/NamespaceAnalyzer.php" "Advanced PHP Analyzer"
copy_if_exists "$SCRIPT_DIR/README_USAGE_GUIDE.md" "$TOOLS_DIR/README.md" "Usage Guide"

# Create a convenient runner script
cat > "$TOOLS_DIR/analyze.sh" << 'RUNNER_SCRIPT'
#!/bin/bash

TOOLS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$TOOLS_DIR/.." && pwd)"

echo "🔍 E-Commerce SaaS Namespace Analyzer"
echo "===================================="
echo "Project: $PROJECT_DIR"
echo ""

echo "Available tools:"
echo "1. Quick Import Detector (fixes critical issues)"
echo "2. Comprehensive Namespace Auditor (full analysis)"
echo "3. Advanced PHP Analyzer (detailed code analysis)"
echo "4. Run all tools"
echo "5. View usage guide"
echo ""

read -p "Select option (1-5): " choice

case $choice in
    1)
        echo ""
        echo "🚀 Running Quick Import Detector..."
        "$TOOLS_DIR/quick_import_detector.sh" "$PROJECT_DIR"
        echo ""
        read -p "Apply quick fixes automatically? (y/n): " apply_fixes
        if [[ "$apply_fixes" =~ ^[Yy] ]]; then
            if [[ -f "$PROJECT_DIR/namespace_audit/quick_fixes.sh" ]]; then
                bash "$PROJECT_DIR/namespace_audit/quick_fixes.sh" "$PROJECT_DIR"
            else
                echo "❌ Quick fixes script not found"
            fi
        fi
        ;;
    2)
        echo ""
        echo "📊 Running Comprehensive Namespace Auditor..."
        "$TOOLS_DIR/namespace_audit_script.sh" "$PROJECT_DIR"
        echo ""
        echo "📄 Review the reports in: $PROJECT_DIR/namespace_audit/"
        ;;
    3)
        echo ""
        echo "🧠 Running Advanced PHP Analyzer..."
        php "$TOOLS_DIR/NamespaceAnalyzer.php" "$PROJECT_DIR"
        echo ""
        echo "📄 Review the reports in: $PROJECT_DIR/namespace_audit/"
        ;;
    4)
        echo ""
        echo "🚀 Running all tools..."
        echo ""
        echo "1️⃣ Quick Import Detector..."
        "$TOOLS_DIR/quick_import_detector.sh" "$PROJECT_DIR"
        echo ""
        echo "2️⃣ Comprehensive Auditor..."
        "$TOOLS_DIR/namespace_audit_script.sh" "$PROJECT_DIR"
        echo ""
        echo "3️⃣ Advanced PHP Analyzer..."
        php "$TOOLS_DIR/NamespaceAnalyzer.php" "$PROJECT_DIR"
        echo ""
        echo "✅ All analyses complete!"
        echo "📄 Check reports in: $PROJECT_DIR/namespace_audit/"
        ;;
    5)
        if [[ -f "$TOOLS_DIR/README.md" ]]; then
            less "$TOOLS_DIR/README.md" || cat "$TOOLS_DIR/README.md"
        else
            echo "❌ Usage guide not found"
        fi
        ;;
    *)
        echo "❌ Invalid option"
        ;;
esac
RUNNER_SCRIPT

chmod +x "$TOOLS_DIR/analyze.sh"

echo "  ✅ Analyzer runner script created"
echo ""

# Create a quick fix alias
cat > "$TOOLS_DIR/quick-fix.sh" << 'QUICK_FIX_SCRIPT'
#!/bin/bash

TOOLS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$TOOLS_DIR/.." && pwd)"

echo "🚀 E-Commerce SaaS Quick Fix"
echo "============================"
echo "This will run the quick detector and apply fixes automatically"
echo "Project: $PROJECT_DIR"
echo ""

read -p "Continue? (y/n): " confirm
if [[ ! "$confirm" =~ ^[Yy] ]]; then
    echo "Cancelled"
    exit 0
fi

echo ""
echo "🔍 Running quick detection..."
"$TOOLS_DIR/quick_import_detector.sh" "$PROJECT_DIR"

echo ""
if [[ -f "$PROJECT_DIR/namespace_audit/quick_fixes.sh" ]]; then
    echo "🔧 Applying fixes..."
    bash "$PROJECT_DIR/namespace_audit/quick_fixes.sh" "$PROJECT_DIR"
    echo ""
    echo "✅ Quick fixes applied!"
    echo ""
    echo "🧪 Test your application:"
    echo "   1. Try logging in"
    echo "   2. Check dashboard loads"
    echo "   3. Look for errors in: tail -f storage/logs/laravel.log"
else
    echo "❌ Quick fixes script not generated"
fi
QUICK_FIX_SCRIPT

chmod +x "$TOOLS_DIR/quick-fix.sh"

echo "  ✅ Quick fix script created"
echo ""

# Add to .gitignore if it doesn't exist
if [[ -f "$PROJECT_DIR/.gitignore" ]]; then
    if ! grep -q "namespace_audit" "$PROJECT_DIR/.gitignore"; then
        echo "" >> "$PROJECT_DIR/.gitignore"
        echo "# Namespace analysis reports" >> "$PROJECT_DIR/.gitignore" 
        echo "namespace_audit/" >> "$PROJECT_DIR/.gitignore"
        echo "  ✅ Added namespace_audit/ to .gitignore"
    else
        echo "  ✅ .gitignore already configured"
    fi
fi

echo ""
echo -e "${GREEN}🎉 Installation Complete!${NC}"
echo ""
echo -e "${BLUE}Quick Start:${NC}"
echo "  cd $PROJECT_DIR"
echo "  ./namespace_tools/quick-fix.sh     # Fix critical issues now"
echo "  ./namespace_tools/analyze.sh       # Full analysis menu"
echo ""
echo -e "${YELLOW}Available Commands:${NC}"
echo "  🚀 Quick fix:    ./namespace_tools/quick-fix.sh"
echo "  📊 Full analysis: ./namespace_tools/analyze.sh"
echo "  📖 Usage guide:   cat ./namespace_tools/README.md"
echo ""
echo -e "${BLUE}Manual Usage:${NC}"
echo "  ./namespace_tools/quick_import_detector.sh"
echo "  ./namespace_tools/namespace_audit_script.sh"  
echo "  php ./namespace_tools/NamespaceAnalyzer.php"
echo ""

# Check for critical issues immediately
echo -e "${YELLOW}🔍 Quick Check:${NC}"
if grep -q "App\\\\Models\\\\User[^\\\\]" "$PROJECT_DIR/config/auth.php" 2>/dev/null; then
    echo "  ⚠️  Critical: config/auth.php needs updating"
    echo "     Run: ./namespace_tools/quick-fix.sh"
elif grep -q "App\\\\Models\\\\Team[^I]" "$PROJECT_DIR/config/jetstream.php" 2>/dev/null; then  
    echo "  ⚠️  Critical: config/jetstream.php needs updating"
    echo "     Run: ./namespace_tools/quick-fix.sh"
else
    echo "  ✅ Config files look OK"
fi

echo ""
echo "📁 Files installed in: $TOOLS_DIR/"
echo "📄 Reports will be saved to: $PROJECT_DIR/namespace_audit/"
echo ""
echo "Happy coding! 🚀"
