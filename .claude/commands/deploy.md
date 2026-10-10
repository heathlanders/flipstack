Run the full deploy pipeline for FlipStack. Execute each step sequentially, stopping on failure:

1. **Pint check** (code style):
   ```bash
   docker exec ecommerce_app ./vendor/bin/pint --test
   ```
   If dirty, run `./vendor/bin/pint` to fix, then show what changed.

2. **Tests**:
   ```bash
   docker exec ecommerce_app php artisan test
   ```
   Stop if any test fails. Show failure details.

3. **Build frontend**:
   ```bash
   npm run build
   ```

4. **Git commit & push** (if there are uncommitted changes):
   - Stage all changed files
   - Write a concise commit message describing the changes
   - Push to origin

5. **Monitor deployment**:
   GitHub Actions handles the full deployment automatically (container recreation, migrations, health checks, Horizon restart). After pushing:
   - Check GitHub Actions status: `gh run list --limit 1`
   - If the run fails, check logs: `gh run view --log-failed`
   - The pipeline includes auto-rollback on failure

Report a summary at each step. If any step fails, stop and explain what went wrong.
