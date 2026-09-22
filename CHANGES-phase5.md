# Phase 5 + Staff management — what changed and how to deploy

## Deploy
1. Copy these files over your project (they are the full codebase, minus `.git`, `vendor/` and `.env`).
2. Commit and push to `main` so GitHub Actions rebuilds the image, then redeploy in Portainer.
3. Nothing to run by hand: `sql/migrations/005_phase5.sql` and `006_staff.sql` are applied automatically on the first request
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


## Staff management (migration 006)
Admin > **Staff** (owners only) — add, edit, disable, enable, delete and reset the password of every admin/staff account.
Each person has: Name, Number, Email, Address, Blood group, Gender, NID number (all required) and one optional document
(PDF/JPG/PNG/WebP, up to 5 MB, e.g. an NID scan).

- **Staff see their own details, read-only,** on Admin > My account (with their document). Only owners can change them (Staff > Edit).
  Owners edit their own details the same way.
- **New staff get a temporary password** set by the owner; until they choose their own at first sign-in, only My account is open to them.
  "Reset password" does the same for someone who forgot theirs.
- **Disable** blocks sign-in and ends the person's open session on their next click; **Delete** removes the account and its document.
  Their past activity-log entries remain under their name. Owners cannot disable, delete, demote or reset the password of themselves,
  and there is always at least one active owner.
- **The document is private:** it is stored in the database (not in the public uploads folder) and served only by a permission-checked
  page to owners and to that person. The file type is checked from its contents, not its name. An owner opening someone else's
  document is written to the Activity log; opening your own is not.
- **NID numbers** are shown masked in lists and are never written to the Activity log (edits show "NID number ••••1234 → ••••5678").
  Email and NID must be unique per account.
- Everything is in the Activity log (Staff group): added, edited (before/after), disabled, enabled, deleted, password reset, document opened. Passwords are never logged.
- Existing admin accounts (e.g. the default `admin`) have no profile details yet: they show "Details incomplete" until an owner fills them in.

**Things to know:** NID numbers and documents are stored unencrypted in the database, protected by access control and logging,
so keep database backups private. Documents live in the database, so backups grow by their size. The default `admin`
account should be given a real name and a new password (My account) like any other.


## Staff profile additions (migration 007)
Four more fields on every staff member, and a bug fix found while building them:

- **Profile picture** — required for every staff member. Uploaded as JPG or PNG, automatically
  centre-cropped to a square, resized to 480×480, EXIF rotation corrected, and all metadata (e.g.
  GPS location) stripped — what's stored is always a clean re-encoded image, never the original
  file. Shown as an avatar in the staff list, the admin header, and on My account; falls back to
  the person's initials until a picture is added.
- **Date of birth** — required, validated as a real calendar date not in the future. No minimum
  age is enforced, as requested. Shown with a computed age on My account.
- **Facebook profile link** — required. Validated as a genuine facebook.com / fb.com / fb.me
  profile link: look-alike domains, `javascript:` links, and Facebook's own share/login/redirect
  endpoints (open-redirect vectors) are all rejected.
- **ID type** — the ID field is now "NID or birth certificate", with a type selector. NID enforces
  10/13/17 digits (Bangladesh's real NID lengths); a birth certificate number accepts 10–17 digits.
  Still unique per person, still masked in lists, still never written to the activity log.

All four join the existing required fields, so "Details incomplete" on the staff list now also
flags a missing photo, DOB, or Facebook link.

### Bug found and fixed: large combined uploads could fail silently
Uploading a profile picture (up to 8MB) together with a document (up to 5MB) in one submission
could exceed the server's `post_max_size` (12MB). PHP responds to that by silently discarding the
entire submission before the app ever runs — which looked like an unrelated "security check
failed" error, not a file-size problem. Fixed two ways:
- `docker/php/uploads.ini`: `post_max_size` raised from 12M to 30M, giving real headroom for the
  largest realistic combination of fields.
- `require_csrf()` now recognises this specific failure signature (empty `$_POST`/`$_FILES` with a
  non-zero `Content-Length`) and shows "That upload was too large..." instead of the generic CSRF
  message, as a safety net for any deployment with tighter limits.
