---
name: develop-vs-main
description: Audit the divergence between the develop and main branches and refresh the docs/DEVELOP_VS_MAIN.md tracker. Invoke when the user asks "what's pending merge", "what's on develop that's not in prod", "branch divergence", or wants to refresh the tracker before a release.
user-invocable: true
---

# Develop vs Main Tracker

Develop = staging branch deployed to `develop.flipstack.app`.
Main = production branch deployed to `flipstack.app`.

This skill keeps `docs/DEVELOP_VS_MAIN.md` in sync with reality. The doc is the durable artifact; this skill documents how to refresh it.

## When to use

- User asks: "what's on develop that's not in main?", "what's pending release?", "branch divergence", "what merges first"
- Before cutting a release / PR-ing develop → main
- After a develop → main merge to update the tracker baseline
- When CLAUDE wants to know what features are staged but unreleased

## Refresh procedure

Always start by fetching latest:

```bash
git fetch origin main develop
```

### 1. Counts (commits ahead/behind)

```bash
echo "ahead:  $(git rev-list --count origin/main..origin/develop)"
echo "behind: $(git rev-list --count origin/develop..origin/main)"
```

- `ahead` = commits on develop not yet in main → these are pending release.
- `behind` = commits on main not in develop → almost always the PR merge commits from prior develop→main merges. Flag if behind > 0 and clear them by syncing main into develop (see "Clearing a behind gap" below).

### Clearing a behind gap

After a develop→main PR merges, main gets a merge commit that develop doesn't have. To resolve:

- **If develop has zero commits past main** (rare — only true if no one merged to develop since the release PR opened):
  ```bash
  git checkout develop && git merge --ff-only origin/main && git push origin develop
  ```
- **If develop has new commits** (the usual case): a fast-forward is impossible. Do a regular merge — content-wise it's a no-op since main is a strict subset of develop, but it absorbs the merge commits into develop's history so the tracker reads "0 behind":
  ```bash
  git checkout develop && git merge origin/main --no-edit && git push origin develop
  ```
  Verify the diff is empty first with `git diff origin/develop..origin/main --stat` — if any files appear (with line removals), main has real content develop is missing and you need a closer look before merging.

### 2. Pending feature commits (develop → main)

```bash
git log --oneline origin/main..origin/develop
```

Group by feature using commit message prefixes/keywords. Cross-reference with memory project files (`memory/project_*.md`) — most features have a memory entry with status, blockers, and "shipped" dates.

### 3. Files changed

```bash
git diff --name-only origin/main..origin/develop | sort | uniq
git diff --stat origin/main..origin/develop | tail -5
```

### 4. Migration delta (high-risk)

```bash
git diff --name-only origin/main..origin/develop -- 'database/migrations/'
```

Any migration here = pending schema change. Production deploy will run these. Flag for review:
- New tables / columns
- NOT NULL adds without backfill
- Drops or renames

### 5. Env/config delta

```bash
git diff origin/main..origin/develop -- '.env.example' 'config/' | head -100
```

Look for new env vars that production needs before deploy.

### 6. Refresh the tracker doc

Update `docs/DEVELOP_VS_MAIN.md`:
- "Last refreshed" header → today's date
- "Counts" table → ahead/behind numbers
- "Pending features" → group commits by feature, mark status (Ready / Blocked / In progress), link memory file if one exists
- "Migrations pending" → list each migration file + 1-line description
- "New env keys" → any keys added to `.env.example` since last main
- "Behind develop?" warning → if behind > 0, note that develop needs to fast-forward post-merge

Keep the doc terse — it is a checklist, not a changelog.

## Memory cross-references

Many pending features map to entries in `~/.claude/projects/-home-user-ecommerce-saas/memory/`. When grouping commits, check for a matching `project_*.md` file and link it from the tracker. Examples:

- `project_amazon_integration_plan.md` — Amazon SP-API
- `project_ebay_tracking_fallback.md` — eBay tracking adapter
- `project_develop_sandbox_switchover.md` — staging sandbox routing
- `project_develop_testing_seeder.md` — QA seeder

If a feature has no memory file, suggest creating one for status tracking.

## Anti-patterns

- Don't list every commit individually — group by feature.
- Don't write a release-notes prose summary — the tracker is for status, not marketing.
- Don't invent feature groupings; if commits don't fit a clear feature, list them under "Misc".
- Don't update the tracker without running the actual git commands first — stale numbers are worse than no numbers.

## After running

Always finish by:
1. Showing the user the counts (ahead/behind).
2. Telling the user the path to the refreshed doc.
3. Calling out any high-risk items (pending migrations, new env vars, "behind develop" warning).
4. **If behind > 0 and `git diff origin/develop..origin/main --stat` shows no main-only content** (only merge commits): offer to clear it with `git merge origin/main --no-edit && git push origin develop` — it's a content-no-op that aligns the history. Do NOT auto-run if any prod-data validation is pending; tell the user first.

## Audit Logging

This is a meta/process skill -- no audit writes.

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from none via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

n/a

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
