# Dignity Jewels — Affiliate Program

PHP 7.4+/8.x · MySQL/MariaDB · Bootstrap 5 · Brevo email. Mobile-first.

## Features
- **Admin panel** (`/admin/`): dashboard, client referrals, invoices & payments, agents, bonuses/payouts, settings, email log.
- **Agents**: created by admin (welcome email with set-password link or credentials) or self-register from the home page.
- **Unique QR code per agent** → `/r/CODE` opens a short client form; the referral is credited to that agent. QR is emailed on registration and always available in the agent dashboard.
- **One client = one agent**: duplicates are blocked by phone (last 10 digits) or email. A second agent can't claim the client.
- **Admin email on every referral** (agent name, phone, and the client's note).
- **Contact client** via WhatsApp (pre-filled message), call, or email (sent through Brevo, logged on the client timeline).
- **Invoices** with items, qty, price, discount, tax → start *Pending*, record payments (date, amount, type), status moves to *Partially paid* → *Paid*.
- **Referral bonus** (default 5%, per-agent override) is credited automatically when the invoice is fully paid; agent is emailed.
- **Bonus payouts** — mark paid in full or partially; agent sees balance, payments and deals in progress.
- Forgot / reset password for everyone; login with email or username; rate-limited logins.
- All tables turn into cards on phones (no horizontal scroll).

## Configuration files (not in git)
Sensitive files are listed in `.gitignore` and must be created on each machine/server:

| File | Create it from | Contains |
|---|---|---|
| `config.php` | `config.sample.php` | Database login, Brevo API key, sender email |
| `includes/brand_data.php` | `includes/brand_data.sample.php` | Team names, phone numbers, emails shown on public pages |

The admin account is created in the installer form — no password is stored in the code.

## Local setup (XAMPP)
1. Folder is already at `C:\xampp\htdocs\dignity_affiliate`.
2. Start Apache + MySQL in XAMPP.
3. Copy `config.sample.php` → `config.php` (and `includes/brand_data.sample.php` → `includes/brand_data.php`).
4. Open `http://localhost/dignity_affiliate/install/`, enter the admin name, email and password → **Install now**.
5. Sign in at `http://localhost/dignity_affiliate/login.php` with the admin email/password.
6. Without a Brevo key, emails are **not sent**; in local (DEBUG) mode a preview of each email is saved to `storage/mail-preview/` so you can open it and click the links (e.g. set-password).

## Deploy to Cloudways (affiliate.dignityjewelry.com)
1. Create the application / subdomain and point `affiliate.dignityjewelry.com` to it; enable the free SSL certificate.
2. Upload all files to the app's `public_html` (keep `.htaccess` files).
3. Edit `config.php` → **production** block: `DB_NAME`, `DB_USER`, `DB_PASS` (from Cloudways *Access Details*), confirm `APP_URL`.
4. Set `BREVO_API_KEY` (Brevo → SMTP & API → API keys) and `BREVO_SENDER_EMAIL` — the sender/domain must be verified in Brevo.
5. Visit `https://affiliate.dignityjewelry.com/install/`, enter the admin details → **Install now**, then **delete the `/install` folder**.
6. Sign in, open **Settings** → *Send test email to me* to confirm Brevo works.
7. Change the admin password under *My account*.

## Automatic deployment (GitHub → Cloudways)
Every push to `main` runs `.github/workflows/deploy.yml`, which checks PHP syntax and then asks the
Cloudways API to `git pull` the new code onto the server. Set up once:
1. Cloudways → Application → **Deployment via Git**: connect this repository (deploy key) and do the first deployment.
2. Cloudways → **API Integration** → **Create Access Token** (Limited Access).
3. GitHub → repository **Settings → Secrets and variables → Actions** → add `CLOUDWAYS_API_TOKEN`,
   `CLOUDWAYS_SERVER_ID`, `CLOUDWAYS_APP_ID`, `CLOUDWAYS_GIT_URL`.
4. Push to `main` (or run the workflow from the **Actions** tab) and check the run is green.

## Notes
- PHP extensions needed: `pdo_mysql`, `curl`, `gd` (for PNG QR codes — falls back to SVG), `mbstring`.
- QR library: chillerlan/php-qrcode (MIT) bundled in `lib/vendor` — no Composer needed.
- Bonus basis (invoice total vs. subtotal after discount), rate, currency, notification emails and WhatsApp message are editable in **Settings**.
- A bonus locks once any payout is recorded: payments that would make the invoice unpaid are blocked until the payouts are removed.
