# Coin economy — proposed wording changes

Prepared alongside the coin economy build. These are **drafts for review**, not final copy — nothing here has been published. Per instructions, no legal page content was edited directly; this document is the deliverable.

A note on scope: the actual body text of Fair Play, Licenses & Security, Terms of Service, and the AML Policy is stored as admin-managed CMS content (the "Page Builder" / content tables), not as static files in this codebase — I don't have a copy of your current live wording to diff against. What follows is proposed *new or additional* language for each surface, written to slot into your existing structure and tone. Treat it as a first draft for your (or your lawyer's) edit pass, not a drop-in replacement.

## 1. Wallet labels (UI strings)

These are simple translatable strings (`resources/lang/*.json`, used via `@lang(...)` sitewide) — low-risk to change, but touching them means updating every language file, not just English, so I left them for your review rather than editing them myself.

| Current | Proposed (coin mode) | Where it shows |
|---|---|---|
| Balance | Coins | Dashboard widget, nav, anywhere the raw balance number is shown |
| Deposit | Buy Coins | Nav item, deposit page title/button |
| Withdraw | Sell Coins (or: Cash Out) | Nav item — this page's whole flow changes meaning, see below |
| Balance Transfer | *(remove from nav in coin mode — the feature is disabled)* | Nav item |
| Deposit History | Coin Purchase History | Page title |
| Withdraw History | *(repurpose as Trade History, or remove)* | Page title |

**The "Withdraw" page needs more than a label swap.** There is no platform withdrawal in coin mode — cashing out means selling coins to a verified agent on the marketplace. I'd suggest replacing the withdraw nav item with "Sell Coins" that links straight to the marketplace's sell-a-listing flow, rather than keeping a page called "Withdraw" that no longer does what its name says.

## 2. Fair Play

Fair Play pages are typically about RNG/game-fairness claims, not currency — likely little or no change needed. One addition worth considering:

> **Proposed addition:** "Game outcomes are determined by the same certified random-number processes regardless of whether you're playing with coins or (where still applicable) real-currency balances — converting to coins does not change how any game is won or lost."

Only add this if your current Fair Play page makes specific claims tied to "real money" wording that coin balances would now contradict. If it's written in currency-neutral terms already, no change is needed.

## 3. Licenses & Security

Proposed additions (this page currently covers organizations/standards/account-security advice — the coin-specific points below extend that, they don't replace anything):

> **Proposed addition — what coins are:** "Coins are an in-platform credit used for play, gifts and VIP perks. Coins are not a currency, deposit, or stored-value instrument, and they are not redeemable for cash directly from Winxpot. Cashing out happens by selling coins to a verified Winxpot agent, who pays you directly, outside the platform, using a payment method you both agree on."

> **Proposed addition — agent verification:** "Agents who buy and sell coins on the marketplace are identity-verified and subject to ongoing review. Winxpot holds coins in escrow during a trade and keeps a full record of every trade, but is not a party to the payment you and an agent arrange between yourselves."

> **Proposed addition — never share payment details insecurely:** this already exists as an in-chat warning ("never share your password, payment card number, or verification code") — worth echoing once on this page too, since agent trades now involve sharing real payment details (bank/wallet info) directly with another person.

## 4. Terms of Service

This is the page that changes the most. Proposed new/amended sections:

> **Proposed — Coins, not currency:** "The Service uses an internal unit called 'coins.' Coins are purchased through our payment partners or from other users acting as agents, at a rate set by Winxpot and shown to you before you buy. Coins may be used for play, virtual gifts, VIP benefits and competition entry. Coins have no cash value outside the Service, are non-transferable except through the agent marketplace described below, and are not deposits, electronic money, or a financial product."

> **Proposed — No platform withdrawals:** "Winxpot does not send money to users and does not process cash withdrawals. If you want to convert coins back to money, you list them for sale on the in-app marketplace, where a verified agent buys them from you and pays you directly, using a method you both agree on, outside the Service. Winxpot holds the listed coins in escrow until the trade completes or is cancelled, and keeps a record of the trade, but is not a party to, and does not guarantee, the payment itself."

> **Proposed — Agent trades, disputes and penalties:** "Trades between you and an agent (or between you and another user acting as an agent) are governed by the trade rules shown in-app: a payment window, a confirmation step, and a dispute process reviewed by a supervisor if either side reports a problem. Confirmed fraud, including falsely claiming non-payment, may result in a coin penalty and, for repeat conduct, suspension from the marketplace or the Service. Security: never share your password, card number, or a one-time verification code with anyone, including an agent, during a trade."

> **Proposed — cutover/legacy balances (one-time, if you still have users with pre-coin balances when you launch this):** "Existing account balances were converted to coins at the then-current purchase rate for each user's currency at the time of conversion. This conversion was one-time and recorded in your account history."

**AML Policy** (not drafted in detail here, since it's the one page I'd most strongly recommend a lawyer review before publishing): the key factual change to disclose is that cash-out now happens peer-to-peer through verified agents rather than a platform-controlled withdrawal, which changes who is "moving money" in the eyes of most AML frameworks. Section 15 of the project brief already flags this as needing specialist legal review before launch — this wording doc doesn't attempt to pre-empt that review.

---

*Everything above is proposed language for human review. No CMS content, language file, or legal page was modified by this build.*
