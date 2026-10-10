# PostgreSQL Gotchas

- PostgreSQL uses **double quotes for identifiers** (column/table names) and **single quotes for string values**.
- In `DB::raw()` / `selectRaw()`: use double-quoted PHP strings with single-quoted SQL values:
  ```php
  // CORRECT:
  DB::raw("COUNT(CASE WHEN status = 'active' THEN 1 END)")
  // WRONG (PG treats "active" as a column name):
  DB::raw('COUNT(CASE WHEN status = "active" THEN 1 END)')
  // ALSO WRONG (single quotes break the PHP string):
  DB::raw('COUNT(CASE WHEN status = 'active' THEN 1 END)')
  ```

# Service Constructor Pattern

Services that load credentials (e.g., `EbayBrowseService`) MUST wrap credential loading in try-catch in their constructors. If an `IntegrationCredential` accessor returns null for an encrypted field, it throws a `TypeError`. Always handle this gracefully so the service can still be instantiated (with an `isAvailable()` check before use).
