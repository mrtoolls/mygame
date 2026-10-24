# Coin economy — deployment checklist

Everything below assumes the usual flow for this project: `git push` auto-deploys the code via `.cpanel.yml`, and you have **no SSH/terminal access** — every step that needs to "run" something means clicking the admin **Database Update** button, not a command line. `coin_mode` ships **OFF**, so none of this is live for real users until you explicitly turn it on.

## 1. Deploy the code

- [ ] Confirm the branch/PR for this work is merged and pushed to `main` (auto-deploys).
- [ ] Open the admin panel and click **Database Update** to run the new migrations. There are quite a few — they're all idempotent (safe to click twice), and each is a small, additive change (new tables/columns), never a destructive one.
- [ ] If the **Database Update** run reports an error on `2026_10_24_000001_add_double_credit_backstop_to_transactions.php` (added in the earlier Phase 0.5 hotfix pass), it means your live `transactions` table already has duplicate `(trx, remark)` rows from a past double-processing issue. The migration refuses to proceed rather than guess — it will name the offending rows. Investigate those specific rows before re-running; don't force it through.

## 2. Verify the foundation is inert (before touching coin_mode)

- [ ] Visit **Admin → Currencies & Countries** and your usual dashboard — confirm everything looks and behaves exactly as before. Nothing should look different yet; `coin_mode` is off.
- [ ] Visit **Admin → Coin Settings** (new page) — confirm it loads, shows "Coin mode: OFF", and the reconciliation line reads `OK` (there's nothing in circulation yet, so this should trivially balance).
- [ ] Do one real, ordinary deposit and one ordinary game play as a smoke test — confirm both behave exactly as before (balance in your account currency, no coin wallet created).

## 3. Set up pricing before going live with real coin trading

- [ ] On **Admin → Coin Settings**, for each enabled currency you care about, review/adjust the buy and sell margin percentages (defaults to 3% each), then save — this computes and locks in the live buy/sell price for that currency.
- [ ] Confirm no currency shows "Flagged - large swing, not auto-applied" unless you expect one (this guard exists specifically to stop an unattended bad FX refresh from mispricing coins).
- [ ] Confirm your exchange-rate refresh is actually running (the existing `currencies:update-rates` job) — coin pricing depends on a current `rate_to_usd` per currency; a stale rate blocks repricing entirely (by design).

## 4. Test with test accounts first (this is what the test kit is for)

- [ ] On **Admin → Coin Settings**, enable **"Marketplace test kit."**
- [ ] Open **Admin → Coin → Marketplace Test Kit** and create: at least one test payment agent, one test supervisor, and 2–3 test users.
- [ ] Grant test float to the agent and test coins to a user via the test kit (these are direct mints, clearly logged as test-kit actions — not the real two-approver flow).
- [ ] Use the manual test script below (section "Manual test script") to walk every flow by hand using these accounts, on both phone and desktop.
- [ ] Once you're satisfied, **turn the test kit off again** — it's meant to be a temporary scaffold, not a permanent feature.

## 5. Turn coin_mode on

- [ ] On **Admin → Coin Settings**, check the confirmation box and turn **Coin mode ON**. This requires a passing reconciliation check — if it's blocked, something is wrong; don't force it.
- [ ] Confirm **`allow_single_approver` is OFF** before any real money/agent activity — leaving it on means one admin account can approve their own float grants and large penalties, which defeats the two-person check. It should only ever be on while you're testing with a single admin account.
- [ ] Spot-check: balance transfer (User → Transfer) should now be disabled with a clear message, on both the web UI and the API.

## 6. Cutover (only when you're ready to convert real accounts — skip entirely if you're staying in coin_mode with test accounts for now)

- [ ] On **Admin → Coin → Cutover Tool**, run the **dry-run preview** first (either mode). Read the numbers — users affected, total coins to mint, any pricing errors (a pricing error usually means a user has no wallet currency set, or that currency has no coin price yet — fix those before running for real).
- [ ] Decide **convert** (real balances → coins at the locked buy price, plus any unclaimed VIP rakeback materialized into coins) vs **reset_test** (every account set to one flat test amount — use this if you're not ready to touch real balances yet and just want a clean test environment).
- [ ] Check the confirmation box and run it for real. **There is no undo** — this is explicitly a one-way operation.
- [ ] Review the logged summary (shown immediately, and always available afterward on the same page): users converted, games refunded, tables force-closed, deposits/withdrawals cancelled, final reconciliation status. Reconciliation should read `OK`; if it doesn't, stop and investigate before doing anything else.

## 7. Nightly jobs are already registered, nothing to configure

The coin ledger reconciliation (daily) and both marketplace timeout sweeps (every minute) are registered as rows in the existing `cron_jobs` table by their own migrations — they ride on whatever already hits your `/cron` URL (cPanel's own cron GUI, no shell needed). Nothing new to wire up here, but:

- [ ] Confirm your `/cron` URL really is being hit periodically (check **Admin → Cron Jobs** — `last_run` should be recent on the three new jobs: "Coin Reconciliation," "Coin Payment Window Sweep," "Coin Confirmation Window Sweep"). If it isn't, none of the time-based safety nets (reconciliation alerts, payment-window expiry, dispute escalation) will fire.

## 8. Ongoing operational notes

- [ ] If reconciliation ever reports a mismatch (you'll see an admin notification, and the Coin Settings page will show it), **do not try to "fix" it by manually editing a `coin_wallets` row.** Every legitimate coin movement goes through `CoinLedger`, which always keeps both sides of the ledger balanced — a real mismatch means something bypassed it, and that's a bug to find, not a number to patch over.
- [ ] Watch the dispute queue (**Admin → Coin → Disputes**, or the agent-side supervisor view) — a trade only lands here when a human needs to make a call; nothing resolves itself.
- [ ] Float grants above the configured threshold, and penalties above the configured threshold, require a **second, different admin** to approve — by design. If you're a single-admin operation for now, that's exactly what `allow_single_approver` is for (testing only — see step 5).

## Rollback notes

- Turning `coin_mode` back OFF (same confirmation-gated toggle) reverts gateway purchases, balance transfer, and VIP/rakeback crediting to their pre-coin behavior immediately. It does **not** undo a cutover that already ran (coins already minted stay minted) — there's no "un-cutover" button, by design, since this build targets test accounts with no real money at stake yet.
- Every coin-ledger migration is additive (new tables/columns) and has a working `down()` method, but rolling migrations back is not the same as reversing business state (minted coins, completed trades, etc.) — treat schema rollback and data rollback as two separate concerns.
