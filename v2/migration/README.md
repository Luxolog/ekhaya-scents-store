# Ekhaya Scents v2 migration toolkit

The toolkit targets the 14 November 2026 UpdraftPlus snapshot. The first utility is a read-only preflight, not a data importer.

## Files
- config.example.php: environment-based source/target connection template.
- config.local.php: create on the server; never commit.
- audit.php: read-only check of required source tables, row counts and database versions.
- catalogue-preview.php: read-only extraction preview of published WooCommerce products, product metadata, category assignments and media attachment IDs. It never reads customer/order data and performs no writes.
- .gitignore: excludes local secrets and generated reports.

## Safe setup
1. Restore the supplied SQL backup into a dedicated private source/staging database. Never restore over the live store database.
2. Create a separate empty target database for Ekhaya v2.
3. Copy config.example.php to config.local.php on the server, preferably outside the public web root.
4. Set source and target credentials using environment variables or the local config file.
5. Run from command line: php audit.php or php audit.php --json.
6. Review missing tables and counts before implementing any import.

## Safeguards
- This script reads table metadata and row counts only. It does not select customer rows or print personal data.
- It performs no inserts, updates, deletes, payment operations or email actions.
- Source and target databases must be separate.
- Never expose local configuration, SQL dumps, customer exports, email payloads, payment tokens or gateway secrets in the public repository.
- Later importer phases will use dry-run by default, batch checkpoints, explicit source-to-target ID mapping, resumability and reconciliation.
- Historical bulk email jobs remain paused/audit-only. Never replay the old queue automatically.
- Unknown marketing consent remains non-marketable.
