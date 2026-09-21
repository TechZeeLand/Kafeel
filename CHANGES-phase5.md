# Phase 5 — what changed and how to deploy

## Deploy
1. Copy these files over your project (they are the full codebase, minus `.git`, `vendor/` and `.env`).
2. Commit and push to `main` so GitHub Actions rebuilds the image, then redeploy in Portainer.
3. Nothing to run by hand: `sql/migrations/005_phase5.sql` is applied automatically on the first request
   (the check now runs when the database connection opens, so any page can be the first one hit).
   It is idempotent and keeps all existing data. Fresh installs get everything from `sql/schema.sql`.
4. Optional env vars (also editable in Admin > Settings): `CONTACT_PHONE_2`, `SOCIAL_WHATSAPP`.

## New features
- **Second phone number** (Admin > Settings). Shown in footer, mobile menu, contact page, invoices, email footer, structured data.
- **WhatsApp link** (Admin > Settings). Paste a wa.me link or just the number; it shows with the other social icons.
- **Product reviews.** Signed-in customers whose order for the product is Shipped or Completed can leave one 1-5 star review
  (guest orders count if the account's verified email matches). They can edit or delete their own. Admin > Reviews to hide/publish/delete.
  Ratings show on product pages, cards and in Google structured data.
- **Wishlist count** on each product page (updates live) and on cards; Wishlists and Rating columns in Admin > Products.
- **Coupons** (Admin > Coupons): percentage (optional cap) or fixed amount; optional minimum order, start/end date,
  total-use limit and per-customer limit; on/off switch. Coupon box on checkout. The discount is recalculated on the server inside the
  order transaction with the coupon row locked, so it cannot be forged or over-used. Cancelled orders give their use back.
  Discount appears on order pages, invoices, emails and the admin order page. Shipping is never discounted.
- **Activity log** (Admin > Activity log, owner accounts only): who did what and when, with before/after values for edits, filters,
  and CSV export. Read-only. Logs sign-ins, failed sign-ins, order status changes, invoice opens, products, categories, customers,
  coupons, reviews, settings, branding and theme. SMTP passwords are never written to it. Plain page views are not logged.
- **Order status attribution**: each status change stores what it changed from and which admin did it. Admin order page only.
  Older orders show the inferred "from" and "not recorded" for who.

## Mobile and other-device fixes
- Bottom tab bar now stays on every page; Cart tab highlights on cart and checkout, Account on order pages; sticky Add-to-cart / Checkout /
  Place-order bars sit above it.
- Order history and order detail no longer widen the page (which zoomed phones out and pushed the tab bar off-screen). Orders are cards on phones.
- Global overflow containment; active account tab scrolls into view.
- Audited 16 pages at 320, 360, 390, 414, 768, 1024 and 1280px: no overflow, tab bar present where expected.

## Other bugs fixed
- Behind a reverse proxy every visitor shared one IP: login throttling locked everyone out together and an audit log would record the proxy. Now reads X-Forwarded-For only when the direct peer is private.
- Disabled customers stayed signed in; deleted/disabled accounts now lose the session immediately.
- Admin sign-out could be triggered by any website; it now needs the CSRF token.
- Deleted admin accounts with a live session are signed out.
- Two admins changing one order at once could record a wrong "from" status; the status is now re-read under a lock.
- Favorite button label didn't change until reload; cart/wishlist failures were silent; now show a message.
- Settings form kept typed values on error only partly; phone numbers are now validated.

## Things to know
- Only accounts with role `owner` can open the Activity log. Promote a staff admin with:
  `UPDATE admins SET role='owner' WHERE username='...';`
- Tab bar also shows on checkout (previously hidden). To hide it there again, add `body.page-checkout .tabbar{display:none}` and set `--tabbar-h:0px` for that page.
- Not verified here: real mPDF PDF output and real SMTP delivery (checked the invoice HTML and email bodies instead).
