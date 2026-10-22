# Gift presentation effects

Covers the shop idle/hover/purchase animations and the live-game send overlay. This is a
presentation-only layer - it never calls a purchase or send endpoint itself. Purchase/send
logic lives in `GiftService` and is untouched by any of this.

Files:
- `assets/global/css/gift-effects.css`
- `assets/global/js/gift-effects.js` (exposes `window.GiftEffects`)
- `app/Models/Gift.php` - `webpImageUrl()` returns the `.webp` sibling of the gift's image, or
  `null` if one doesn't exist on disk (used to build a `<picture>` element with a PNG/JPEG
  fallback - never assume a gift has a `.webp` copy, including admin-uploaded ones).

## How a gift's tier is chosen

`GiftEffects.tierFor(key, price)` in `gift-effects.js`:

1. Lowercases `key` and looks it up directly in the `TIERS` map (`fox`, `rose`, `heart`,
   `crown`, `lion`, `diamond`).
2. If there's no match, falls back to a price-based tier (see `PRICE_FALLBACK` in the same
   file) so a brand new gift still gets a sensible effect without any code change.

The shop markup passes the gift's **slug** (`data-gift-fx="{{ $gift->slug }}"`). The live-game
overlay passes the gift's **name**, lowercased (that's what the existing gift chat-message
payload already carries - `gift_name` in `GiftService::send()`'s JSON body - so no backend
change was needed to wire this up). Since the seeded gift slugs and names both lowercase to the
same six tier keys, both paths resolve to the same tier for the stock gifts.

## Adding a new gift

1. Add it in Admin → Gifts (name, slug, prices, image) as normal. If you don't add a tier for
   it below, it automatically gets a tier from `PRICE_FALLBACK` based on `buy_price` - nothing
   else to do.
2. To give it a **bespoke** tier (its own extra effect, not just a fallback), add an entry to
   `TIERS` in `gift-effects.js` keyed by its slug:
   ```js
   TIERS.unicorn = { glow: 'rgba(200,140,255,1)', particles: 10, extra: 'petals', overlayClass: '' };
   ```
   - `glow`: CSS color (any valid value) used for the drop-shadow glow on the shop card and the
     overlay image.
   - `particles`: how many sparkle particles a hover burst spawns on the shop card (halved
     automatically on narrow/mobile viewports).
   - `extra`: which live-overlay extra effect to spawn - one of `null`, `'petals'`, `'hearts'`,
     `'gold'`, `'lion'`, `'diamond'`, or add a new one (see below).
   - `overlayClass`: an extra class added to `.gift-fx-overlay__card` for that tier (drives the
     CSS keyframes in `gift-effects.css`, e.g. `is-crown`'s gold sweep).
3. To add a genuinely new `extra` effect, add a branch in `spawnExtra()` in
   `initLiveOverlay()` (`gift-effects.js`) that appends the particle elements, and matching CSS
   keyframes in `gift-effects.css` (animate only `transform`/`opacity` - see the performance
   notes below).
4. Provide `-512.png` / `-512.webp` (and `-256.*` if it'll ever render small) copies alongside
   the full-resolution source, named `{slug}-512.webp` etc., so `Gift::webpImageUrl()` finds
   them. If you only upload one image via the admin form, that's fine too - it just won't get a
   `.webp` `<source>`, so it'll serve the PNG/JPEG directly everywhere.

## Where effects are wired in

- **Shop** (`resources/views/templates/nocturne/user/gifts/index.blade.php`): each
  `.gift-card` is also `.gift-fx` with `data-gift-fx="{slug}"` / `data-gift-price`.
  `GiftEffects.initShop(gridEl)` wires idle float/shine, hover sparkle bursts, and returns an
  object with `.pop(giftId)` for the purchase-success bounce.
  - The buy form is a normal (non-AJAX) POST - purchase logic is never touched. A tiny inline
    script remembers which gift was being bought in `sessionStorage` right before the real
    submit, then after the page reloads checks the real `session('notify')` success array
    (already rendered server-side) to decide whether to call `.pop()`. If the purchase failed,
    there's no 'success' entry and nothing plays.
- **Live game** (`resources/views/templates/nocturne/user/table_games/show.blade.php`): the
  `#gift-fx-overlay` element + `GiftEffects.initLiveOverlay(overlayEl)` replaces the old
  `#gift-burst` markup. `window.spawnGiftBurst(payload)` is still the call site used by the
  existing gift chat-message handler (`m.kind==='gift'`) - it now just forwards to
  `overlay.queue(payload)` instead of doing its own one-shot animation. The queue is capped at
  3 pending gifts (oldest dropped first) and plays them one at a time, full tumble-to-settle,
  before starting the next - a burst of gifts can never stack visual chaos on screen.
- **Admin preview** (`Admin\GiftController::previewEffects`, `admin/gift/preview.blade.php`,
  `admin.gift.preview-effects` route): plays every tier with no purchase/send calls, for
  reviewing before/after a tier tweak.

## Performance / accessibility rules already baked in

- Only `transform`, `opacity`, `box-shadow`, and `background-position` are animated - never
  `width`/`height`/`top`/`left` (would trigger layout).
- `prefers-reduced-motion: reduce` is checked at the JS level before spawning any particle
  (`reduceMotion()` in `gift-effects.js`) and via a CSS media query that kills the shop's
  CSS-only idle/pop keyframes.
- Shop cards pause their idle float/shine via `IntersectionObserver` (off-screen) and
  `visibilitychange` (hidden tab) - see `initShop()`.
- Particle counts are halved on `(max-width: 575px)`.
- The live overlay is `pointer-events: none` on its own root; only the card inside intercepts
  nothing either - it's purely informational and never blocks game input underneath it.
