# Multicurrency rollout boundary

The country/currency updater migration adds currency identities and tags existing account, ledger, deposit, withdrawal, and game records with the site's configured legacy currency. It does not convert existing amount columns, because the application currently stores wallet and ledger values as decimal major units and multiple payment, game, commission, and reporting paths still perform arithmetic on those values.

Do not enable non-legacy wallet currencies for real-money activity until the integer-minor-unit conversion is completed and rehearsed against a production database copy.

## Risks to review before conversion

- A rounding rule can change balances, wagers, fees, payouts, commissions, or refunds by one or more minor units.
- Currency precision varies: UGX, TZS, XAF, and RWF use zero decimals in this project; other seeded currencies use two.
- Historical rows need the currency that applied when each transaction occurred. Tagging every row as the current site currency is only safe when the installation has never mixed currencies.
- Third-party gateways may calculate charges and conversions in decimal major units. Their request and webhook amounts must be converted at a single documented boundary.
- Reports and exported files must be checked for mixed major/minor units before rollout.
- Large ledgers may need chunked backfill and a maintenance window; adding and populating integer columns in one transaction may lock tables or exceed hosting timeouts.

## Required rollout

1. Take a database backup and restore it to a staging copy.
2. Inventory every amount column and every wallet mutation path; record precision, scale, and historical currency attribution.
3. Add nullable integer minor-unit columns while keeping legacy decimal columns readable.
4. Backfill in chunks using the source currency's decimal count and an explicit rounding rule; record rows that do not round-trip exactly for manual review.
5. Reconcile per-user balances against the ledger and gateway settlements; require totals to match before cutover.
6. Dual-write and compare both representations in staging, then switch all reads and writes in one controlled release.
7. Keep a tested rollback plan and retain original decimal values until post-cutover reconciliation is signed off.

The current admin currency screen states this boundary. Its rates are for reporting only. Do not treat the present `currency_id` tags or formatter as proof that the integer conversion is complete.
