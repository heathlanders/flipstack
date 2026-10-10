#!/bin/bash

# Label Printing System - Vue Components Deployment Script
# This script deploys all Vue.js components to the correct Laravel directories

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LARAVEL_ROOT="."
COMPONENTS_SOURCE="vue-components"

echo -e "${BLUE}🚀 Label Printing System - Vue Components Deployment${NC}"
echo "======================================================"

# Function to check if we're in a Laravel project
check_laravel_project() {
    if [[ ! -f "artisan" ]] || [[ ! -d "resources/js" ]]; then
        echo -e "${RED}❌ Error: This doesn't appear to be a Laravel project root directory.${NC}"
        echo "   Please run this script from your Laravel project root (where artisan file is located)."
        exit 1
    fi
}

# Function to create directory if it doesn't exist
create_dir() {
    local dir="$1"
    if [[ ! -d "$dir" ]]; then
        echo -e "${YELLOW}📁 Creating directory: $dir${NC}"
        mkdir -p "$dir"
    fi
}

# Function to copy file with backup if exists
copy_file() {
    local source="$1"
    local dest="$2"
    local dest_dir="$(dirname "$dest")"
    
    # Create destination directory
    create_dir "$dest_dir"
    
    # Backup existing file if it exists
    if [[ -f "$dest" ]]; then
        echo -e "${YELLOW}⚠️  Backing up existing file: $dest → $dest.backup$(date +%Y%m%d_%H%M%S)${NC}"
        cp "$dest" "$dest.backup$(date +%Y%m%d_%H%M%S)"
    fi
    
    # Copy new file
    echo -e "${GREEN}📄 Copying: $source → $dest${NC}"
    cp "$source" "$dest"
}

# Function to show deployment summary
show_summary() {
    echo ""
    echo -e "${GREEN}✅ DEPLOYMENT COMPLETE!${NC}"
    echo "======================================================"
    echo -e "${BLUE}📊 Summary:${NC}"
    echo "   • Pages deployed: 16"
    echo "   • Components deployed: 9" 
    echo "   • Integration components: 1"
    echo "   • Total files: 26"
    echo ""
    echo -e "${BLUE}📋 Next Steps:${NC}"
    echo "   1. Add routes to web.php (see IMPLEMENTATION_COMPLETE.md)"
    echo "   2. Install dependencies: composer require simplesoftwareio/simple-qrcode barryvdh/laravel-dompdf"
    echo "   3. Run pending migrations (warehouse_locations, products table updates)"
    echo "   4. Create default templates using: php artisan tinker"
    echo "   5. Configure your first printer via /printing/printers/discovery"
    echo ""
    echo -e "${GREEN}🎉 Your label printing system is ready to go!${NC}"
}

# Main deployment function
main() {
    echo -e "${BLUE}🔍 Checking Laravel project...${NC}"
    check_laravel_project
    
    echo -e "${BLUE}📂 Checking source components...${NC}"
    if [[ ! -d "$COMPONENTS_SOURCE" ]]; then
        echo -e "${RED}❌ Error: Components directory '$COMPONENTS_SOURCE' not found.${NC}"
        echo "   Please extract the zip file first or run this script from the extracted directory."
        exit 1
    fi
    
    echo -e "${BLUE}🚀 Starting deployment...${NC}"
    echo ""
    
    # Deploy Pages
    echo -e "${YELLOW}📱 Deploying Pages...${NC}"
    
    # Printing Dashboard
    copy_file "$COMPONENTS_SOURCE/printing-dashboard.vue" "resources/js/Pages/Printing/Dashboard.vue"
    
    # Printer Pages
    copy_file "$COMPONENTS_SOURCE/printer-index.vue" "resources/js/Pages/Printing/Printers/Index.vue"
    copy_file "$COMPONENTS_SOURCE/printer-create.vue" "resources/js/Pages/Printing/Printers/Create.vue"  
    copy_file "$COMPONENTS_SOURCE/printer-discovery.vue" "resources/js/Pages/Printing/Printers/Discovery.vue"
    copy_file "$COMPONENTS_SOURCE/printer-show.vue" "resources/js/Pages/Printing/Printers/Show.vue"
    copy_file "$COMPONENTS_SOURCE/printer-edit.vue" "resources/js/Pages/Printing/Printers/Edit.vue"
    
    # Template Pages  
    copy_file "$COMPONENTS_SOURCE/template-index.vue" "resources/js/Pages/Printing/Templates/Index.vue"
    copy_file "$COMPONENTS_SOURCE/template-create.vue" "resources/js/Pages/Printing/Templates/Create.vue"
    copy_file "$COMPONENTS_SOURCE/template-show.vue" "resources/js/Pages/Printing/Templates/Show.vue"
    copy_file "$COMPONENTS_SOURCE/template-edit.vue" "resources/js/Pages/Printing/Templates/Edit.vue"
    
    # Job Pages
    copy_file "$COMPONENTS_SOURCE/jobs-index.vue" "resources/js/Pages/Printing/Jobs/Index.vue" 
    copy_file "$COMPONENTS_SOURCE/job-show.vue" "resources/js/Pages/Printing/Jobs/Show.vue"
    
    # Location Pages
    copy_file "$COMPONENTS_SOURCE/locations-index.vue" "resources/js/Pages/Printing/Locations/Index.vue"
    copy_file "$COMPONENTS_SOURCE/locations-create.vue" "resources/js/Pages/Printing/Locations/Create.vue"
    copy_file "$COMPONENTS_SOURCE/location-show.vue" "resources/js/Pages/Printing/Locations/Show.vue"
    copy_file "$COMPONENTS_SOURCE/locations-edit.vue" "resources/js/Pages/Printing/Locations/Edit.vue"
    
    echo ""
    echo -e "${YELLOW}🧩 Deploying Components...${NC}"
    
    # Printing Components
    copy_file "$COMPONENTS_SOURCE/label-print-button.vue" "resources/js/Components/Printing/LabelPrintButton.vue"
    copy_file "$COMPONENTS_SOURCE/batch-print-button.vue" "resources/js/Components/Printing/BatchPrintButton.vue"
    copy_file "$COMPONENTS_SOURCE/print-settings-modal.vue" "resources/js/Components/Printing/PrintSettingsModal.vue"
    copy_file "$COMPONENTS_SOURCE/printer-status.vue" "resources/js/Components/Printing/PrinterStatus.vue"
    copy_file "$COMPONENTS_SOURCE/print-job-status.vue" "resources/js/Components/Printing/PrintJobStatus.vue"
    copy_file "$COMPONENTS_SOURCE/qr-code-generator.vue" "resources/js/Components/Printing/QRCodeGenerator.vue"
    copy_file "$COMPONENTS_SOURCE/label-preview.vue" "resources/js/Components/Printing/LabelPreview.vue"
    copy_file "$COMPONENTS_SOURCE/template-selector.vue" "resources/js/Components/Printing/TemplateSelector.vue"
    copy_file "$COMPONENTS_SOURCE/printer-discovery-component.vue" "resources/js/Components/Printing/PrinterDiscovery.vue"
    
    # Inventory Integration
    copy_file "$COMPONENTS_SOURCE/product-label-actions.vue" "resources/js/Components/Inventory/ProductLabelActions.vue"
    
    # Special handling for PrinterManagement.vue (if it exists)
    if [[ -f "$COMPONENTS_SOURCE/PrinterManagement.vue" ]]; then
        copy_file "$COMPONENTS_SOURCE/PrinterManagement.vue" "resources/js/Components/Printing/PrinterManagement.vue"
    fi
    
    echo ""
    show_summary
}

# Help function
show_help() {
    echo "Label Printing System - Vue Components Deployment Script"
    echo ""
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "OPTIONS:"
    echo "  -h, --help     Show this help message"
    echo "  -d, --dry-run  Show what would be copied without actually copying"
    echo ""
    echo "This script must be run from your Laravel project root directory."
    echo "Make sure you've extracted the label-printing-components.zip file first."
}

# Dry run function
dry_run() {
    echo -e "${BLUE}🔍 DRY RUN MODE - No files will be copied${NC}"
    echo "======================================================"
    check_laravel_project
    
    if [[ ! -d "$COMPONENTS_SOURCE" ]]; then
        echo -e "${RED}❌ Error: Components directory '$COMPONENTS_SOURCE' not found.${NC}"
        exit 1
    fi
    
    echo -e "${YELLOW}Files that would be deployed:${NC}"
    echo ""
    
    # List all the files that would be copied
    echo "📱 PAGES:"
    echo "   printing-dashboard.vue → resources/js/Pages/Printing/Dashboard.vue"
    echo "   printer-index.vue → resources/js/Pages/Printing/Printers/Index.vue"
    echo "   printer-create.vue → resources/js/Pages/Printing/Printers/Create.vue"
    echo "   printer-discovery.vue → resources/js/Pages/Printing/Printers/Discovery.vue" 
    echo "   printer-show.vue → resources/js/Pages/Printing/Printers/Show.vue"
    echo "   printer-edit.vue → resources/js/Pages/Printing/Printers/Edit.vue"
    echo "   template-index.vue → resources/js/Pages/Printing/Templates/Index.vue"
    echo "   template-create.vue → resources/js/Pages/Printing/Templates/Create.vue"
    echo "   template-show.vue → resources/js/Pages/Printing/Templates/Show.vue"
    echo "   template-edit.vue → resources/js/Pages/Printing/Templates/Edit.vue"
    echo "   jobs-index.vue → resources/js/Pages/Printing/Jobs/Index.vue"
    echo "   job-show.vue → resources/js/Pages/Printing/Jobs/Show.vue"
    echo "   locations-index.vue → resources/js/Pages/Printing/Locations/Index.vue"
    echo "   locations-create.vue → resources/js/Pages/Printing/Locations/Create.vue"
    echo "   location-show.vue → resources/js/Pages/Printing/Locations/Show.vue"
    echo "   locations-edit.vue → resources/js/Pages/Printing/Locations/Edit.vue"
    echo ""
    echo "🧩 COMPONENTS:"
    echo "   label-print-button.vue → resources/js/Components/Printing/LabelPrintButton.vue"
    echo "   batch-print-button.vue → resources/js/Components/Printing/BatchPrintButton.vue"
    echo "   print-settings-modal.vue → resources/js/Components/Printing/PrintSettingsModal.vue"
    echo "   printer-status.vue → resources/js/Components/Printing/PrinterStatus.vue"
    echo "   print-job-status.vue → resources/js/Components/Printing/PrintJobStatus.vue"
    echo "   qr-code-generator.vue → resources/js/Components/Printing/QRCodeGenerator.vue"
    echo "   label-preview.vue → resources/js/Components/Printing/LabelPreview.vue"
    echo "   template-selector.vue → resources/js/Components/Printing/TemplateSelector.vue"
    echo "   printer-discovery-component.vue → resources/js/Components/Printing/PrinterDiscovery.vue"
    echo "   product-label-actions.vue → resources/js/Components/Inventory/ProductLabelActions.vue"
    echo ""
    echo -e "${GREEN}✅ Total: 26 files would be deployed${NC}"
    echo ""
    echo "Run without --dry-run to perform the actual deployment."
}

# Parse command line arguments
case "${1:-}" in
    -h|--help)
        show_help
        exit 0
        ;;
    -d|--dry-run)
        dry_run
        exit 0
        ;;
    "")
        main
        ;;
    *)
        echo -e "${RED}❌ Unknown option: $1${NC}"
        echo ""
        show_help
        exit 1
        ;;
esac
