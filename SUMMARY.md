# Kafeel — this session's changes

23 files changed (21 modified, 2 new). Full unified diff in `CHANGES.patch`
(generated with `git diff`, so you can `git apply CHANGES.patch` from your
repo root, or just drop these files over the matching paths and `git diff`
yourself before committing). Every PHP file was `php -l` linted, and the
nginx/routing fix, the pre-order flow, and the new policy page were all
tested end-to-end against a live copy of the app (real DB, real HTTP
requests) before being included here — not just read over.

## 1. Banner "doesn't work" — investigated, no code bug found

I logged into a local copy of your admin panel and uploaded a real banner
end-to-end (validation → resize → save → `og:image` tag → served file) and
it worked correctly at every step. The most likely explanation is
WhatsApp/Facebook caching the *old* preview for your URL — your own README
already documents the fix: paste the link into Facebook's [Sharing
Debugger](https://developers.facebook.com/tools/debug/) and press **Scrape
Again**. If it still doesn't show up after that, the next thing I'd want to
see is exactly what the debugger reports (it'll show the image URL it's
trying to fetch and any error), since that would point at something
domain/proxy-specific I can't see from here.

## 2. Categories/products/orders/invoices "gone" — root cause found & fixed

**Not data loss.** Your database and `uploads/` are untouched — both are
proper persistent volumes in `docker-compose.yml`.

The real bug: in `docker/nginx/default.conf`, the four pretty-URL routes for
product/category/order/invoice used `rewrite ^ /product.php?slug=$1 break;`.
`$1` there is *always empty* — nginx does not carry a capture group from the
`location ~` match into a separate `rewrite` directive whose own pattern
(`^`) has no capture group of its own. So every one of those pages was
requesting the underlying script with an empty slug/order number, which is
why they all came up "not found." I reproduced this in an isolated nginx
config to confirm the mechanism, then fixed it (and verified the fix) in
`docker/nginx/default.conf` by repeating the capture group in each
`rewrite`'s own pattern. I also fixed a smaller side-effect of the same
rewrites: they were dropping the original query string, so `?sort=` and
`?page=` on category pages, and `?rpage=` on product review pagination,
were silently ignored.

**Deploy note:** this is a `docker/nginx/default.conf` change, so it needs
an image rebuild (your GHCR CI/CD will pick it up on push to `main` as
usual) — a plain `docker compose restart` won't be enough since the file is
baked into the image.

## 3. Pre-order system (new)

- Admin → edit product: "Available for pre-order" checkbox, with an
  optional note (e.g. "Ships in 2–3 weeks") and expected-availability date.
- While a pre-order product's stock is 0, customers can still add it to
  cart and check out — a "Pre-order" badge/button replaces "Out of stock"
  everywhere (product cards, product page, cart, checkout summary).
- The order line records `is_preorder` at the moment of purchase, so it's
  still visible in admin/customer order views and on the invoice later,
  even if you've since turned the flag off because real stock arrived.
- Stock is never decremented for a pre-order line (there's nothing to
  decrement yet); regular in-stock lines on the same order are handled
  exactly as before.
- New migration: `sql/migrations/010_preorder.sql` (auto-applies on next
  deploy, same as every migration before it — nothing to run by hand).

I placed a real test order through this flow locally and confirmed the
database recorded everything as expected.

## 4. Shipping & Delivery Policy page (new)

`src/shipping-policy.php`, at `/shipping-policy`. Pulls delivery fees,
free-weight threshold, and delivery-day estimates from your existing
settings (the same constants `about.php` already uses), so it stays correct
if you change those numbers later — nothing hardcoded. Added to
`sitemap.php`. Your footer already linked to this URL (it was a dead link
until now).

## 5. Docs cleanup

- **README.md:** removed a duplicated paragraph, fixed a section
  ("Extending") that flatly contradicted the rest of the README about
  email being wired up, removed a dead link to a `CHANGES-phase5.md` file
  that doesn't exist (folded its content into a new "Feature changelog"
  section along with Phase 6 and the pretty-URL/pre-order work, none of
  which were documented anywhere before), and updated the feature list and
  project-layout tree to mention things that already existed in the code
  but not in the docs (subcategories, warranty, staff management, coupons,
  `sql/migrations/`, `uploads/branding/`).
- **terms.php:** added a line clarifying that pre-ordering an out-of-stock
  item is a confirmed order, not a maybe.
- **privacy-policy.php:** added a line noting that review name/text are
  shown publicly (they are, per `product_reviews.author_name`/`body`) —
  this wasn't disclosed anywhere before.
- refund-policy.php: read closely, didn't need changes — its existing
  cancellation clause already covers pre-orders correctly.

## 6. Other bugs found in the sweep

- `docker/nginx/default.conf`: the old-style-URL redirect block (the one
  that 301s `/checkout.php` → `/checkout` for old bookmarks/search-engine
  links) was missing `shipping-policy` from its list — fixed for
  consistency now that the page exists.
- Full-codebase `php -l` lint pass: clean, no syntax errors, before and
  after all changes.
- Checked for common issues (XSS via unescaped output, SQL built by string
  concatenation instead of prepared statements, leftover debug code, dead
  links) — nothing else turned up beyond what's listed above.

## Applying this

```bash
cd /Sites/Kafeel
git apply CHANGES.patch    # or copy the files over by hand
git add -A
git commit -m "fix pretty-url routing, add pre-orders + shipping policy, docs cleanup"
git push    # CI builds & pushes the image; Portainer redeploy picks it up
```

Since `docker/nginx/default.conf` changed, make sure the redeploy actually
pulls the new image (`docker compose pull && docker compose up -d`, or
Portainer's "Re-pull image and redeploy") rather than just restarting the
existing container.
