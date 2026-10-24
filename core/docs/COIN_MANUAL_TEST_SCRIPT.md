# Coin economy — manual test script

For walking every flow by hand with real test accounts (created via the admin **Marketplace Test Kit**, see the deployment checklist). Run each section on **both phone and desktop** — the marketplace UI is deliberately simple right now (functional, not the full nocturne visual treatment), so it's worth checking it's usable on a small screen, not just that it works.

Accounts you'll need: 1 test user acting as a coin seller, 1 test user acting as a coin buyer, 1 test payment agent, 1 test supervisor, 1 admin account (2 admin accounts if testing the two-approver flows with `allow_single_approver` off).

## A. Buying coins (gateway purchase)

1. As a test user with `coin_mode` on, go to Deposit and complete a purchase through any enabled gateway (or the manual/test gateway if that's what you have configured).
2. Confirm the coin balance (not real-currency balance) increases by the correct amount for the price you paid, at the currently posted buy price.
3. Confirm the deposit's history entry shows it as a coin purchase, with the rate that was used.
4. If referral commission is enabled (`gs('dc')`), and this user has a referrer, confirm the referrer's **coin** balance increased by the configured percentage.

## B. Selling coins to an agent (the main cash-out path)

1. As the **seller** user: go to the Coin Marketplace, enter an amount, submit "List for sale." Confirm your available coin balance drops by that amount and it moves to "held."
2. As the **agent**: go to the agent marketplace page, confirm the listing appears, click "Buy." Confirm the listing disappears from the list immediately (first-click-wins) and a trade opens.
3. Open the trade on both sides (seller and agent) — confirm the chat works: send a message as the agent (e.g. share payment details), confirm the seller sees it within a few seconds (polling), and vice versa.
4. As the agent: attach a small image file in chat (simulating a payment screenshot) — confirm it uploads and the seller can open it.
5. As the agent: click "I have paid." Confirm the trade moves to "paid_claimed" and a confirmation countdown appears.
6. **Before confirming**, as the seller, try clicking "Put back on sale" — confirm it's now blocked (the whole point of this rule: once payment is claimed, the seller can't quietly cancel and relist).
7. As the seller: click "I received payment — release coins." Confirm:
   - The trade shows "completed."
   - The agent's coin balance increased by the trade amount **minus the platform fee**.
   - The seller's held balance is now zero (not refunded back to available — it's gone, paid out).
8. Repeat the whole flow once more, but this time have the **seller** click "I have not received payment" instead of confirming, after the agent claims payment. Confirm the trade moves to "disputed" and appears in the supervisor queue — and confirm the held coins are **not** released to anyone automatically.

## C. Buying coins directly from an agent

1. As an agent: toggle "Start selling coins" on (requires the agent to be marked an active payment agent already).
2. As a (different) test user: go to the Coin Marketplace, pick that agent from the list, enter an amount, submit. Confirm a trade opens immediately — no listing step — and the **agent's** coin balance is held immediately (not the user's).
3. As the user: claim payment ("I have paid"), attach a proof file.
4. As the agent: confirm receipt. Confirm the user's coin balance increases (minus fee, depending on your fee-payer setting), and the agent's held balance clears.

## D. Timeouts — confirm nothing auto-releases

1. Create a listing, have an agent claim it, but **don't** claim payment on either side. Either wait out the payment window, or use the admin test kit's "simulate a payment-window timeout" button for a fast version.
2. Confirm the trade shows "expired" and the listing is back on sale — and confirm the seller's coins are still shown as held (against the reopened listing), not dumped back to "available" and then immediately re-held — the point is they were never actually released.
3. Separately: claim a listing, claim payment, then don't respond for longer than the confirmation window (or shorten the window in Coin Settings temporarily for testing). Confirm the trade escalates to "disputed" on its own — and again, confirm no coins moved.

## E. Disputes and penalties (supervisor + admin)

1. From either the admin dispute queue or a supervisor-flagged agent's dispute queue, open a disputed trade from section B or D above.
2. Resolve it once as "release to buyer" — confirm the trade completes and coins move exactly as a normal confirmation would have.
3. Create another dispute and resolve it as "return to seller" — confirm the listing (if any) reopens and the coins stay held against it, not released to "available."
4. As a **non-supervisor** agent, confirm you get a clear rejection if you try to resolve a dispute (should not be reachable from their UI at all, but worth confirming the backend rejects it too).
5. Issue a small (tier 1) penalty against a test account from a resolved dispute — confirm: the offending account's coins decrease, the wronged party receives half of the penalty amount, and the record shows up with a reason attached.
6. Issue a tier 3 penalty large enough to cross the "second approver" threshold (check the configured value in Coin Settings) **without** a second approver — confirm it's rejected. Then provide a second, different admin's approval — confirm it goes through. Try it again with the *same* admin as both issuer and approver — confirm it's still rejected unless `allow_single_approver` is on.

## F. Agent float grants

1. As admin A, request a float grant for a test agent with a reason.
2. As the **same** admin A, try to approve it — confirm it's rejected (needs a different admin), unless `allow_single_approver` is on.
3. As admin B, approve it — confirm the agent's coin balance increases by the granted amount.

## G. Balance transfer is disabled in coin mode

1. As a user, try the Balance Transfer page (if still linked in navigation) or hit its API endpoint directly — confirm it's rejected with a clear message, not a silent failure or a crash.

## H. Reconciliation and admin visibility

1. On the admin dashboard, confirm the coin-mode banner shows, with a circulating-supply figure and "Reconciliation: OK."
2. On Coin Settings, trigger "Recompute all rates" and confirm no errors (or, if a currency's rate is stale, confirm you get a clear error rather than a bad price).
3. (Optional, more invasive) If you want to actually see the reconciliation alert fire, you'd need to bypass `CoinLedger` to create an artificial mismatch — not recommended on anything but a disposable test database, since the point is this should never happen in practice.

## I. Cutover (only in a disposable test environment — see the deployment checklist for when to run this for real)

1. Run the dry-run preview in both modes, confirm the numbers look sane for your test accounts.
2. Run `reset_test` for real with a flat amount — confirm every test account's coin balance is now exactly that amount, regardless of what it was before.
3. (Separately, on a throwaway copy if possible) run `convert` for real on an account with a real balance, an in-progress game, and a pending deposit/withdrawal — confirm the game is refunded, the pending items are cancelled, VIP rakeback (if any was claimable) was added on top, and the final coin amount matches the real balance divided by the buy price.

## What "pass" looks like, overall

- No flow ever shows coins moving without an explicit confirmation from the receiving party or a supervisor ruling.
- Every amount shown matches what the ledger actually holds (spot-check a few against **Admin → Coin Settings**'s reconciliation figure).
- Nothing in sections A–H works when `coin_mode` is off (quick regression check: flip it off, confirm the marketplace pages 404 or redirect, confirm gateway purchases go back to crediting real-currency balance).
