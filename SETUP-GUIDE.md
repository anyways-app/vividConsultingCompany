# vividConsulting.info — Authentication & Payments Setup Guide

---

## 1. Google Cloud Console Setup

### 1.1 Create a Google Cloud Project

1. Go to https://console.cloud.google.com/projectcreate
2. **Project name**: `vividConsulting-website`
3. **Organization**: Leave as default (or select your org if you have one)
4. Click **Create**
5. Wait for the project to be created, then ensure it's selected in the top nav bar

### 1.2 Configure OAuth Consent Screen

1. Go to https://console.cloud.google.com/apis/credentials/consent
2. Select **External** (allows any Google account to sign in)
3. Click **Create**
4. Fill in the fields:
   - **App name**: `vividConsulting.INFO`
   - **User support email**: `santosh@vividconsulting.info`
   - **App logo**: Upload the vividconsulting.png logo (optional)
   - **App domain — Application home page**: `http://www.vividconsulting.info`
   - **App domain — Privacy policy**: `http://www.vividconsulting.info/qa/pages/privacy.html`
   - **App domain — Terms of service**: `http://www.vividconsulting.info/qa/pages/terms.html`
   - **Authorized domains**: Add `vividconsulting.info`
   - **Developer contact email**: `santosh@vividconsulting.info`
5. Click **Save and Continue**
6. **Scopes** screen: Click **Add or Remove Scopes**
   - Select: `openid`, `email`, `profile` (under Google Account)
   - Click **Update**, then **Save and Continue**
7. **Test users** screen: Add `santosh@vividconsulting.info` (and any other testers)
8. Click **Save and Continue**, then **Back to Dashboard**

### 1.3 Create OAuth 2.0 Client ID

1. Go to https://console.cloud.google.com/apis/credentials
2. Click **+ Create Credentials** → **OAuth client ID**
3. **Application type**: Web application
4. **Name**: `vividConsulting Website`
5. **Authorized JavaScript origins**: Add both:
   - `http://www.vividconsulting.info`
   - `https://www.vividconsulting.info`
6. **Authorized redirect URIs**: Add both:
   - `http://www.vividconsulting.info/qa/callback.php` (QA)
   - `https://www.vividconsulting.info/callback.php` (Production)
7. Click **Create**
8. Copy the **Client ID** and **Client Secret** — you'll paste these into `config.php`

### 1.4 Values to Copy into config.php

| config.php key          | Where to find it                              |
|-------------------------|-----------------------------------------------|
| `GOOGLE_CLIENT_ID`      | OAuth credentials page → Client ID            |
| `GOOGLE_CLIENT_SECRET`  | OAuth credentials page → Client Secret        |
| `GOOGLE_REDIRECT_URI`   | Must match one of the authorized redirect URIs |

---

## 2. Stripe Setup

### 2.1 Create a Stripe Account

1. Go to https://dashboard.stripe.com/register
2. Sign up with `santosh@vividconsulting.info`
3. Complete business verification when prompted
4. For QA testing, you'll use **Test mode** (toggle in the top-right of the Stripe dashboard)

### 2.2 Get API Keys

1. Go to https://dashboard.stripe.com/test/apikeys (Test mode)
2. Copy:
   - **Publishable key**: starts with `pk_test_...`
   - **Secret key**: Click **Reveal test key**, starts with `sk_test_...`

### 2.3 Create Products and Prices

1. Go to https://dashboard.stripe.com/test/products
2. Click **+ Add product**

**Product 1: Consultant Monthly**
- **Name**: `Consultant Monthly`
- **Description**: `Full access to the vividConsulting template library — monthly billing`
- **Pricing model**: Standard pricing
- **Price**: `$199.00`
- **Billing period**: Monthly / Recurring
- Click **Save product**
- Copy the **Price ID** (starts with `price_...`) from the product detail page

**Product 2: Consultant Annual**
- **Name**: `Consultant Annual`
- **Description**: `Full access to the vividConsulting template library — annual billing (save 40%)`
- **Pricing model**: Standard pricing
- **Price**: `$1,433.00`
- **Billing period**: Yearly / Recurring
- Click **Save product**
- Copy the **Price ID** (starts with `price_...`)

### 2.4 Set Up Webhook

1. Go to https://dashboard.stripe.com/test/webhooks
2. Click **+ Add endpoint**
3. **Endpoint URL**: `http://www.vividconsulting.info/qa/stripe-webhook.php`
   (For production: `https://www.vividconsulting.info/stripe-webhook.php`)
4. **Events to send**: Select these 4 events:
   - `checkout.session.completed`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_failed`
5. Click **Add endpoint**
6. On the endpoint detail page, click **Reveal** under **Signing secret**
7. Copy the signing secret (starts with `whsec_...`)

### 2.5 Values to Copy into config.php

| config.php key               | Where to find it                          |
|------------------------------|-------------------------------------------|
| `STRIPE_PUBLISHABLE_KEY`     | API keys page → Publishable key           |
| `STRIPE_SECRET_KEY`          | API keys page → Secret key                |
| `STRIPE_WEBHOOK_SECRET`      | Webhook endpoint detail → Signing secret  |
| `STRIPE_PRICE_MONTHLY`       | Consultant Monthly product → Price ID     |
| `STRIPE_PRICE_ANNUAL`        | Consultant Annual product → Price ID      |

---

## 3. File Structure

```
/qa/
├── config.php                  ← Configuration (credentials, DB, URLs)
├── db.php                      ← Database connection (PDO singleton)
├── auth.php                    ← Auth logic (OAuth, sessions, find-or-create)
├── callback.php                ← Google OAuth redirect endpoint
├── stripe-checkout.php         ← Creates Stripe Checkout sessions
├── stripe-webhook.php          ← Receives Stripe webhook events
├── composer.json               ← PHP dependencies
├── vendor/                     ← Composer autoload (generated)
├── schema.sql                  ← PostgreSQL schema (run once)
├── login.html                  ← Sign-in page
├── dashboard.html              ← Authenticated user dashboard
├── index.html                  ← Existing home page
├── SETUP-GUIDE.md              ← This file
├── api/
│   ├── user.php                ← GET: current user profile (JSON)
│   ├── templates.php           ← GET: template library (JSON)
│   ├── login.php               ← GET: initiates Google OAuth redirect
│   └── logout.php              ← POST/GET: destroys session, redirects home
├── js/
│   └── auth-nav.js             ← Auth-aware nav (include on every page)
├── pages/                      ← Existing site pages
├── blog/                       ← Existing blog pages
├── templates/                  ← Existing template library page
└── images/                     ← Existing images
```

---

## 4. Nav Integration for Existing Pages

To add auth-aware navigation to every existing page, add this line before `</body>`:

```html
<script src="js/auth-nav.js"></script>
```

This script automatically:
- Checks if the user is logged in (via `api/user.php`)
- If logged in: replaces the "Subscribe" button with user avatar + dropdown (Dashboard, Sign Out)
- If not logged in: replaces the "Subscribe" button with a "Sign In" link

No other changes to existing pages are needed.

---

## 5. Deployment Checklist

### Database Setup

- [ ] 1. In cPanel, create a new PostgreSQL database (e.g., `vividconsulting_auth`)
- [ ] 2. Create a database user with a strong password
- [ ] 3. Grant all privileges on the database to the user
- [ ] 4. Note the host (usually `localhost`), port (`5432`), database name, username, and password
- [ ] 5. SSH into the server and run: `psql -U <user> -d <dbname> -f /path/to/schema.sql`
- [ ] 6. Verify tables were created: `psql -U <user> -d <dbname> -c "\dt"`

### Google OAuth Setup

- [ ] 7. Create Google Cloud project (see Section 1.1)
- [ ] 8. Configure OAuth consent screen (see Section 1.2)
- [ ] 9. Create OAuth client ID with correct redirect URIs (see Section 1.3)
- [ ] 10. Copy Client ID and Client Secret

### Stripe Setup

- [ ] 11. Create Stripe account (see Section 2.1)
- [ ] 12. Copy API keys from Test mode (see Section 2.2)
- [ ] 13. Create both subscription products and prices (see Section 2.3)
- [ ] 14. Set up webhook endpoint with the 4 events (see Section 2.4)
- [ ] 15. Copy webhook signing secret

### Configuration

- [ ] 16. Open `config.php` and fill in ALL placeholder values:
  - Google Client ID and Secret
  - Stripe keys, Price IDs, and Webhook Secret
  - PostgreSQL host, port, database name, username, password
  - BASE_URL (ensure correct for QA vs production)
  - FORCE_HTTPS (false for QA, true for production)
  - SESSION_COOKIE_SECURE (false for QA, true for production)
  - SESSION_COOKIE_PATH (/qa/ for QA, / for production)

### Install Dependencies

- [ ] 17. SSH into server, navigate to the site root (`/qa/`)
- [ ] 18. Run `composer install` to install Stripe PHP SDK
- [ ] 19. Verify `vendor/` directory was created with `vendor/autoload.php`

### Upload Files

- [ ] 20. Upload all PHP files, the `api/` directory, `js/auth-nav.js`, `login.html`, and `dashboard.html`
- [ ] 21. Set file permissions to 705 (as per your server requirements)
- [ ] 22. Ensure `config.php` is NOT publicly accessible (add to `.htaccess` if needed):
```apache
<Files "config.php">
    Order Allow,Deny
    Deny from all
</Files>
<Files "db.php">
    Order Allow,Deny
    Deny from all
</Files>
<Files "auth.php">
    Order Allow,Deny
    Deny from all
</Files>
```

### Add Auth Nav to Existing Pages

- [ ] 23. Add `<script src="js/auth-nav.js"></script>` before `</body>` on every existing HTML page

### Test Auth Flow

- [ ] 24. Navigate to `http://www.vividconsulting.info/qa/login.html`
- [ ] 25. Click "Sign in with Google"
- [ ] 26. Authorize with your Google account
- [ ] 27. Verify you're redirected to `dashboard.html`
- [ ] 28. Verify your name, avatar, and "free" tier badge appear
- [ ] 29. Verify the template list loads with 8 stub templates
- [ ] 30. Verify free templates are accessible, consultant templates show "Upgrade to access"

### Test Stripe

- [ ] 31. On the dashboard, click "Upgrade to Consultant"
- [ ] 32. You should be redirected to Stripe Checkout (Test mode)
- [ ] 33. Use Stripe test card: `4242 4242 4242 4242`, any future date, any CVC
- [ ] 34. Complete checkout — you should be redirected to dashboard with success message
- [ ] 35. Verify your tier badge changed to "consultant"
- [ ] 36. Verify all templates are now accessible (no "Upgrade to access" links)
- [ ] 37. Check the Stripe dashboard → Webhooks → verify events were received and returned 200

### Test Session Persistence

- [ ] 38. Close the browser entirely
- [ ] 39. Reopen and navigate to `dashboard.html`
- [ ] 40. Verify you're still logged in (not redirected to login)

### Test Logout

- [ ] 41. Click avatar → Sign Out
- [ ] 42. Verify you're redirected to the home page
- [ ] 43. Navigate to `dashboard.html` — verify redirect to `login.html`

### Test Nav on Existing Pages

- [ ] 44. Navigate to `index.html` while logged in
- [ ] 45. Verify avatar + name appears in top-right nav (instead of Subscribe button)
- [ ] 46. Click avatar → verify dropdown shows Dashboard and Sign Out
- [ ] 47. Log out, reload — verify "Sign In" link appears

### Security Verification

- [ ] 48. Try accessing `config.php` directly in browser — should return 403 Forbidden
- [ ] 49. Try accessing `db.php` directly — should return 403
- [ ] 50. Try accessing `api/user.php` while logged out — should return 401 JSON
- [ ] 51. Verify session cookie has `httponly` flag (check in browser dev tools → Application → Cookies)

### Production Cutover (when ready)

- [ ] 52. Update `config.php`: set `FORCE_HTTPS = true`, `SESSION_COOKIE_SECURE = true`, `SESSION_COOKIE_PATH = '/'`
- [ ] 53. Update `BASE_URL` to `https://www.vividconsulting.info`
- [ ] 54. Update `GOOGLE_REDIRECT_URI` to `https://www.vividconsulting.info/callback.php`
- [ ] 55. Switch Stripe to Live mode: update all Stripe keys, Price IDs, and webhook to production values
- [ ] 56. Add production webhook endpoint in Stripe: `https://www.vividconsulting.info/stripe-webhook.php`
- [ ] 57. Publish Google OAuth consent screen (removes "Testing" limitation)
