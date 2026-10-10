#!/usr/bin/env bash
#
# Create GitHub labels for FlipStack project management.
# Requires: gh CLI authenticated (gh auth login)
#
# Usage: ./scripts/create-github-labels.sh
#
# Safe to re-run — gh label create with --force overwrites existing labels.

set -euo pipefail

REPO="heathlanders/flipstack"

echo "Creating labels for $REPO..."

# Type labels (red hues)
gh label create "type:feature"      --color "D93F0B" --description "New functionality"                    --repo "$REPO" --force
gh label create "type:enhancement"  --color "E36209" --description "Improvement to existing feature"      --repo "$REPO" --force
gh label create "type:bug"          --color "B60205" --description "Something is broken"                  --repo "$REPO" --force
gh label create "type:test"         --color "D93F0B" --description "Test creation or improvement"         --repo "$REPO" --force
gh label create "type:docs"         --color "E36209" --description "Documentation work"                   --repo "$REPO" --force
gh label create "type:refactor"     --color "D93F0B" --description "Code restructure, no behavior change" --repo "$REPO" --force
gh label create "type:chore"        --color "E36209" --description "Maintenance, deps, CI/CD"             --repo "$REPO" --force

# Priority labels (orange/yellow)
gh label create "priority:p0-critical" --color "B60205" --description "Drop everything"       --repo "$REPO" --force
gh label create "priority:p1-high"     --color "D93F0B" --description "Must do this sprint"   --repo "$REPO" --force
gh label create "priority:p2-medium"   --color "E99D42" --description "Should do this sprint" --repo "$REPO" --force
gh label create "priority:p3-low"      --color "FEF2C0" --description "Backlog"               --repo "$REPO" --force

# Severity labels (for bugs)
gh label create "severity:s1-blocker"  --color "B60205" --description "System down, data loss, no workaround"      --repo "$REPO" --force
gh label create "severity:s2-major"    --color "D93F0B" --description "Core feature broken, workaround exists"     --repo "$REPO" --force
gh label create "severity:s3-minor"    --color "E99D42" --description "Edge case incorrect behavior"               --repo "$REPO" --force
gh label create "severity:s4-cosmetic" --color "FEF2C0" --description "Visual/UI issue, no functional impact"      --repo "$REPO" --force

# Platform labels (blue hues)
gh label create "platform:web"            --color "0075CA" --description "Laravel/Vue/Inertia"     --repo "$REPO" --force
gh label create "platform:mobile"         --color "1D76DB" --description "Flutter app"             --repo "$REPO" --force
gh label create "platform:api"            --color "0E8A16" --description "REST API endpoints"      --repo "$REPO" --force
gh label create "platform:cross-platform" --color "5319E7" --description "Affects multiple platforms" --repo "$REPO" --force
gh label create "platform:infra"          --color "006B75" --description "CI/CD, Docker, deployment"  --repo "$REPO" --force

# Domain labels (green hues)
gh label create "domain:fulfillment"         --color "0E8A16" --description "Orders, pick/pack/ship, returns"     --repo "$REPO" --force
gh label create "domain:inventory"           --color "1B7F37" --description "Products, stock, categories"         --repo "$REPO" --force
gh label create "domain:marketplace-ebay"    --color "2DA44E" --description "eBay integration"                    --repo "$REPO" --force
gh label create "domain:marketplace-shopify" --color "3FB950" --description "Shopify integration"                 --repo "$REPO" --force
gh label create "domain:intake-queue"        --color "56D364" --description "Product intake processing"           --repo "$REPO" --force
gh label create "domain:analytics"           --color "0E8A16" --description "P&L, forecasting, KPI dashboards"   --repo "$REPO" --force
gh label create "domain:procurement"         --color "1B7F37" --description "Purchase orders, receiving"          --repo "$REPO" --force
gh label create "domain:pricing"             --color "2DA44E" --description "Repricing engine, rules"             --repo "$REPO" --force
gh label create "domain:customer"            --color "3FB950" --description "Customer CRM"                        --repo "$REPO" --force
gh label create "domain:communication"       --color "56D364" --description "Marketplace messages"                --repo "$REPO" --force
gh label create "domain:marketing"           --color "0E8A16" --description "Email campaigns"                     --repo "$REPO" --force
gh label create "domain:printing"            --color "1B7F37" --description "Label printing"                      --repo "$REPO" --force
gh label create "domain:auth-org"            --color "2DA44E" --description "Auth, teams, roles, org"             --repo "$REPO" --force
gh label create "domain:supplier"            --color "3FB950" --description "Supplier portal, submissions"        --repo "$REPO" --force
gh label create "domain:warehouse"           --color "56D364" --description "Zones, bins, layout"                 --repo "$REPO" --force

# Status labels (purple)
gh label create "status:blocked"          --color "5319E7" --description "Blocked by dependency or issue"  --repo "$REPO" --force
gh label create "status:needs-info"       --color "7057FF" --description "Needs more information"          --repo "$REPO" --force
gh label create "status:wontfix"          --color "CCCCCC" --description "Will not be addressed"           --repo "$REPO" --force
gh label create "status:duplicate"        --color "CCCCCC" --description "Duplicate of another issue"      --repo "$REPO" --force

# Effort labels (teal)
gh label create "effort:xs" --color "006B75" --description "1-2 hours"  --repo "$REPO" --force
gh label create "effort:s"  --color "0E8A16" --description "Half day"   --repo "$REPO" --force
gh label create "effort:m"  --color "1B7F37" --description "1-2 days"   --repo "$REPO" --force
gh label create "effort:l"  --color "2DA44E" --description "3-5 days"   --repo "$REPO" --force
gh label create "effort:xl" --color "3FB950" --description "1+ week"    --repo "$REPO" --force

echo ""
echo "Done. Created/updated all labels."
echo "View at: https://github.com/$REPO/labels"
