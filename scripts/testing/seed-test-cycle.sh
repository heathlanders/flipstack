#!/usr/bin/env bash
#
# Provision a GitHub Project v2 board for a test cycle and bulk-create test-case
# issues from docs/testing/cases/<domain>/TC-*.md. Idempotent: re-running with the
# same cycle id will skip existing labels, milestone, and issues (matched by title).
#
# Usage:
#   scripts/testing/seed-test-cycle.sh [cycle-id]
#
# Defaults to the marketplace sync v1 cycle if no argument is passed.
#
# Requirements: gh CLI authenticated (gh auth login), jq.

set -euo pipefail

REPO="heathlanders/flipstack"
OWNER="@me"

CYCLE_ID="${1:-v1-marketplace-sync}"
CYCLE_TITLE="Marketplace Sync v1"
PROJECT_TITLE="FlipStack QA: ${CYCLE_TITLE}"
MILESTONE_TITLE="${CYCLE_TITLE}"
LABEL_CYCLE="cycle:${CYCLE_ID}"

# Test cases included in this cycle. One per TC doc. Format:
#   TC_ID|Title|Domain|Priority|TestType|DocPath
TEST_CASES=(
  "TC-MKT-002|eBay Listing Sync|Marketplace - eBay|P0|Integration|docs/testing/cases/marketplace/TC-MKT-002-ebay-listing-sync.md"
  "TC-MKT-003|eBay Inventory Push|Marketplace - eBay|P0|Integration|docs/testing/cases/marketplace/TC-MKT-003-ebay-inventory-sync.md"
  "TC-MKT-007|Shopify Product Sync|Marketplace - Shopify|P0|Integration|docs/testing/cases/marketplace/TC-MKT-007-shopify-product-sync.md"
  "TC-MKT-008|Shopify Webhooks|Marketplace - Shopify|P0|Integration|docs/testing/cases/marketplace/TC-MKT-008-shopify-webhooks.md"
  "TC-MKT-009|Cross-Listing|Marketplace - eBay|P1|Feature|docs/testing/cases/marketplace/TC-MKT-009-cross-listing.md"
  "TC-MKT-011|Marketplace Sync Rules|Marketplace - eBay|P1|Unit|docs/testing/cases/marketplace/TC-MKT-011-marketplace-sync-rules.md"
  "TC-MKT-012|E2E Sync Lifecycle|Marketplace - eBay|P0|E2E|docs/testing/cases/marketplace/TC-MKT-012-marketplace-sync-e2e-lifecycle.md"
  "TC-MKT-013|Multi-Tenant Sync Isolation|Marketplace - eBay|P0|Feature|docs/testing/cases/marketplace/TC-MKT-013-multi-tenant-sync-isolation.md"
)

log() { printf '\033[1;34m[seed]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[seed]\033[0m %s\n' "$*" >&2; }
die() { printf '\033[1;31m[seed]\033[0m %s\n' "$*" >&2; exit 1; }

command -v gh >/dev/null || die "gh CLI not installed. Run: sudo apt install gh && gh auth login"
command -v jq >/dev/null || die "jq required. Run: sudo apt install jq"
gh auth status >/dev/null 2>&1 || die "gh not authenticated. Run: gh auth login"

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$REPO_ROOT"

#------------------------------------------------------------------------------
# Labels
#------------------------------------------------------------------------------
ensure_label() {
  local name="$1" color="$2" desc="$3"
  if gh label list --repo "$REPO" --json name --jq '.[].name' | grep -Fxq "$name"; then
    log "label exists: $name"
  else
    gh label create "$name" --repo "$REPO" --color "$color" --description "$desc"
    log "label created: $name"
  fi
}

ensure_label "test-case"           "0E8A16" "Manual or automated test case"
ensure_label "test-blocked"        "D93F0B" "Test blocked by environment / dependency"
ensure_label "domain:marketplace"  "1D76DB" "Marketplace integration domain"
ensure_label "$LABEL_CYCLE"        "FBCA04" "Test cycle: $CYCLE_ID"

#------------------------------------------------------------------------------
# Milestone
#------------------------------------------------------------------------------
MILESTONE_NUMBER="$(gh api "repos/${REPO}/milestones?state=all" --jq ".[] | select(.title == \"${MILESTONE_TITLE}\") | .number")"
if [[ -z "$MILESTONE_NUMBER" ]]; then
  MILESTONE_NUMBER="$(gh api -X POST "repos/${REPO}/milestones" \
    -f title="${MILESTONE_TITLE}" \
    -f description="First official feature test cycle: ${CYCLE_TITLE}" \
    -f state="open" --jq '.number')"
  log "milestone created: ${MILESTONE_TITLE} (#${MILESTONE_NUMBER})"
else
  log "milestone exists: ${MILESTONE_TITLE} (#${MILESTONE_NUMBER})"
fi

#------------------------------------------------------------------------------
# Project v2
#------------------------------------------------------------------------------
PROJECT_NUMBER="$(gh project list --owner "$OWNER" --format json --limit 50 \
  | jq -r ".projects[] | select(.title == \"${PROJECT_TITLE}\") | .number")"

if [[ -z "$PROJECT_NUMBER" ]]; then
  PROJECT_NUMBER="$(gh project create --owner "$OWNER" --title "${PROJECT_TITLE}" --format json | jq -r '.number')"
  log "project created: ${PROJECT_TITLE} (#${PROJECT_NUMBER})"
else
  log "project exists: ${PROJECT_TITLE} (#${PROJECT_NUMBER})"
fi

PROJECT_ID="$(gh project view "$PROJECT_NUMBER" --owner "$OWNER" --format json | jq -r '.id')"

#------------------------------------------------------------------------------
# Project fields
#------------------------------------------------------------------------------
field_id_by_name() {
  local name="$1"
  gh project field-list "$PROJECT_NUMBER" --owner "$OWNER" --format json \
    | jq -r ".fields[] | select(.name == \"${name}\") | .id"
}

ensure_single_select_field() {
  local name="$1"; shift
  local opts="$*"
  if [[ -n "$(field_id_by_name "$name")" ]]; then
    log "field exists: $name"
    return
  fi
  gh project field-create "$PROJECT_NUMBER" --owner "$OWNER" \
    --name "$name" --data-type SINGLE_SELECT --single-select-options "$opts" >/dev/null
  log "field created: $name"
}

ensure_text_field() {
  local name="$1"
  if [[ -n "$(field_id_by_name "$name")" ]]; then
    log "field exists: $name"
    return
  fi
  gh project field-create "$PROJECT_NUMBER" --owner "$OWNER" \
    --name "$name" --data-type TEXT >/dev/null
  log "field created: $name"
}

# Status field is built-in; ensure required options exist (cannot delete defaults).
# Add the cycle-specific columns by extending the existing Status field options.
log "configuring project Status options"
STATUS_FIELD_ID="$(field_id_by_name "Status")"
EXISTING_STATUS_OPTS="$(gh project field-list "$PROJECT_NUMBER" --owner "$OWNER" --format json \
  | jq -r ".fields[] | select(.name == \"Status\") | .options[].name" 2>/dev/null || true)"
log "existing Status options: $(echo "$EXISTING_STATUS_OPTS" | tr '\n' ' ')"
warn "Note: GH does not currently allow CLI-edit of the built-in Status field options."
warn "Manually add these statuses in the project UI if not already present:"
warn "  Backlog, Ready to Test, In Progress, Blocked / Bug Found, Verified, Done"

ensure_single_select_field "Priority"  "P0,P1,P2,P3"
ensure_single_select_field "Test Type" "Unit,Feature,Integration,E2E,UI"
ensure_single_select_field "Result"    "Pass,Fail,Blocked,Skipped,Not Run"
ensure_text_field          "Cycle"

PRIORITY_FIELD_ID="$(field_id_by_name "Priority")"
TYPE_FIELD_ID="$(field_id_by_name "Test Type")"
RESULT_FIELD_ID="$(field_id_by_name "Result")"
CYCLE_FIELD_ID="$(field_id_by_name "Cycle")"

option_id() {
  local field_name="$1" option_name="$2"
  gh project field-list "$PROJECT_NUMBER" --owner "$OWNER" --format json \
    | jq -r ".fields[] | select(.name == \"${field_name}\") | .options[] | select(.name == \"${option_name}\") | .id"
}

#------------------------------------------------------------------------------
# Issues
#------------------------------------------------------------------------------
issue_number_by_title() {
  local title="$1"
  gh issue list --repo "$REPO" --search "in:title \"${title}\"" --state all --json number,title --limit 50 \
    | jq -r ".[] | select(.title == \"${title}\") | .number" | head -1
}

extract_subcase_titles() {
  local doc="$1"
  grep -E '^## TC-[A-Z]+-[0-9]+-[0-9]+:' "$doc" 2>/dev/null | sed 's/^## //' || true
}

ITEMS_TO_CONFIGURE=()

for entry in "${TEST_CASES[@]}"; do
  IFS='|' read -r TC_ID TC_TITLE TC_DOMAIN TC_PRIORITY TC_TYPE TC_DOC <<< "$entry"
  ISSUE_TITLE="[${TC_ID}] ${TC_TITLE}"

  EXISTING_NUMBER="$(issue_number_by_title "$ISSUE_TITLE")"
  if [[ -n "$EXISTING_NUMBER" ]]; then
    log "issue exists: #${EXISTING_NUMBER} ${ISSUE_TITLE}"
    ISSUE_NUMBER="$EXISTING_NUMBER"
  else
    SUBCASES="$(extract_subcase_titles "$TC_DOC")"
    SUBCASE_CHECKLIST=""
    if [[ -n "$SUBCASES" ]]; then
      while IFS= read -r line; do
        SUBCASE_CHECKLIST+=$'- [ ] '"$line"$'\n'
      done <<< "$SUBCASES"
    fi

    REPO_BLOB_URL="https://github.com/${REPO}/blob/main"
    TC_TESTER_DOC="${TC_DOC%.md}-tester.md"
    TESTER_DOC_LINE=""
    if [[ -f "$TC_TESTER_DOC" ]]; then
      TESTER_DOC_LINE=$'\n- **Tester Doc (plain-language):** ['"\`${TC_TESTER_DOC}\`"']('"${REPO_BLOB_URL}/${TC_TESTER_DOC}"')'
    fi

    BODY=$(cat <<EOF
## Test Case
- **ID:** ${TC_ID}
- **Domain:** ${TC_DOMAIN}
- **Priority:** ${TC_PRIORITY}
- **Type:** ${TC_TYPE}
- **Cycle:** ${CYCLE_ID}
- **Doc:** [\`${TC_DOC}\`](${REPO_BLOB_URL}/${TC_DOC})${TESTER_DOC_LINE}

## Sub-cases
${SUBCASE_CHECKLIST:-(See linked doc.)}

## Run instructions
1. Move this item to **In Progress** on the [project board](https://github.com/users/$(gh api user --jq .login)/projects/${PROJECT_NUMBER}).
2. Open the linked TC doc and execute each sub-case.
3. Record results in the cycle run log: \`docs/testing/runs/<YYYY-MM-DD>-${CYCLE_ID}.md\`.
4. On failure, file a bug from the **bug-report** template and add \`Blocked by #<bug>\` here.
5. On success of every sub-case, set \`Result=Pass\` and move to **Verified**.

See [\`docs/testing/EXECUTION_LIFECYCLE.md\`](${REPO_BLOB_URL}/docs/testing/EXECUTION_LIFECYCLE.md) for the full cycle workflow.
EOF
)
    ISSUE_NUMBER="$(gh issue create --repo "$REPO" \
      --title "$ISSUE_TITLE" \
      --body "$BODY" \
      --label "test-case,domain:marketplace,${LABEL_CYCLE}" \
      --milestone "$MILESTONE_TITLE" \
      | grep -oE '[0-9]+$' || true)"
    if [[ -z "$ISSUE_NUMBER" ]]; then
      warn "failed to create issue: $ISSUE_TITLE"
      continue
    fi
    log "issue created: #${ISSUE_NUMBER} ${ISSUE_TITLE}"
  fi

  ISSUE_URL="https://github.com/${REPO}/issues/${ISSUE_NUMBER}"
  ITEM_ID="$(gh project item-add "$PROJECT_NUMBER" --owner "$OWNER" --url "$ISSUE_URL" --format json | jq -r '.id')"
  log "added to project: ${ISSUE_TITLE} (item ${ITEM_ID})"

  # Set custom field values
  gh project item-edit \
    --id "$ITEM_ID" --project-id "$PROJECT_ID" \
    --field-id "$PRIORITY_FIELD_ID" \
    --single-select-option-id "$(option_id Priority "$TC_PRIORITY")" >/dev/null

  gh project item-edit \
    --id "$ITEM_ID" --project-id "$PROJECT_ID" \
    --field-id "$TYPE_FIELD_ID" \
    --single-select-option-id "$(option_id "Test Type" "$TC_TYPE")" >/dev/null

  gh project item-edit \
    --id "$ITEM_ID" --project-id "$PROJECT_ID" \
    --field-id "$RESULT_FIELD_ID" \
    --single-select-option-id "$(option_id Result "Not Run")" >/dev/null

  gh project item-edit \
    --id "$ITEM_ID" --project-id "$PROJECT_ID" \
    --field-id "$CYCLE_FIELD_ID" \
    --text "$CYCLE_ID" >/dev/null
done

log "done. Project: $(gh project view "$PROJECT_NUMBER" --owner "$OWNER" --format json | jq -r '.url')"
log "milestone: https://github.com/${REPO}/milestone/${MILESTONE_NUMBER}"
log "issues:    gh issue list --repo ${REPO} --label '${LABEL_CYCLE}'"
