# WebsiteTailors — Technical Architecture & Deployment Manual

> **Tagline**: *"We Make Digital Things Work."*

A production-ready, high-performance web platform and CMS architecture built strictly with **Vanilla PHP 8+**, **MySQL / MariaDB (via PDO)**, **HTML5**, **CSS3**, and **Vanilla JavaScript**.

Zero external frameworks, zero Node/npm runtime dependencies, zero composer vendor bloat. Perfectly suited for deployment on standard Apache/LiteSpeed shared hosting (such as **Hostinger**, cPanel, SiteGround, etc.).

php -S 127.0.0.1:8000
to run this project


---

## 1. Directory Structure

```text
/
├── .htaccess                      # Apache security rules, header defenses, sensitive file protection
├── README.md                      # Complete architectural manual (this document)
├── setup.php                      # One-time self-locking web setup wizard for initial admin
├── index.php                      # Public homepage front controller
├── services.php                   # Public services listing
├── work.php                       # Public portfolio / projects showcase
├── about.php                      # Public about & process flow
├── contact.php                    # Public contact form & lead capture with CSRF
│
├── admin/                         # CMS Admin Panel
│   ├── .htaccess                  # Direct access restrictions for admin includes
│   ├── index.php                  # Admin dashboard & analytics overview
│   ├── login.php                  # Hardened login (rate-limited, brute-force guarded)
│   ├── logout.php                 # Secure session destruction
│   ├── hero.php                   # Hero content manager
│   ├── services.php               # Services CMS manager
│   ├── projects.php               # Projects CMS manager
│   ├── process.php                # Process steps CMS manager
│   ├── testimonials.php           # Testimonials CMS manager
│   ├── leads.php                  # Contact inquiries & leads viewer
│   ├── settings.php               # Site settings & branding manager
│   ├── users.php                  # Admin user management
│   └── includes/
│       ├── auth_guard.php         # Strict session & privilege verification guard
│       ├── admin_header.php       # Admin layout header shell
│       └── admin_footer.php       # Admin layout footer shell
│
├── api/                           # Secure asynchronous endpoints
│   ├── contact.php                # AJAX lead submission endpoint (CSRF & Rate-limited)
│   └── admin/                     # Admin AJAX actions
│
├── assets/                        # Static presentation assets
│   ├── css/                       # Vanilla CSS stylesheets
│   ├── js/                        # Vanilla JavaScript modules
│   ├── images/                    # Brand graphics, logos, static illustrations
│   └── icons/                     # UI SVG vector icons
│
├── bin/                           # Command-line utilities
│   └── create_admin.php           # CLI admin creation tool (secure password hashing)
│
├── config/                        # Core configuration
│   ├── config.php                 # Application constants, environment, base URL, session
│   ├── database.php               # Singleton PDO connection wrapper & query helpers
│   ├── config.example.php         # Template config for remote servers
│   └── installed.lock             # Self-lock marker created after initial admin setup
│
├── database/                      # Schema & migrations
│   └── WebsiteTailors.sql                 # Complete MySQL schema, indexes, and WebsiteTailors seed data
│
├── includes/                      # Core backend services & security helpers
│   ├── init.php                   # Master bootstrapper (loads config, session, helpers)
│   ├── security.php               # CSRF tokens, XSS escaping, rate-limiting, IP discovery
│   ├── auth.php                   # Session authentication, login/logout, RBAC
│   ├── upload.php                 # Secure file upload handler (MIME checking, random names)
│   └── functions.php              # Utility routines, flash messages, formatters, slugs
│
└── uploads/                       # User-uploaded dynamic media
    ├── .htaccess                  # Critical: Script execution disabled (php engine off)
    ├── projects/                  # Project showcase covers
    ├── testimonials/              # Client avatar imagery
    └── settings/                  # Custom logos and favicons
```

---

## 2. Admin Panel Access & Default Credentials

The CMS administration interface is accessible at:
- **Local URL**: `http://localhost:8000/admin/login.php`
- **Production URL**: `https://your-domain.com/admin/login.php`

### Pre-Configured Administrative Credentials

| Account | Email Address | Username | Password | Role |
| :--- | :--- | :--- | :--- | :--- |
| **Primary Administrator** | `superadmin@rithamaya.com` | `superadmin` | `Admin@12345` | `superadmin` |
| **Default Seed Admin** | `websietailorss@gmail.com` | `admin` | `Admin@12345` | `superadmin` |

> [!NOTE]
> - Passwords are case-sensitive (capital **`A`** and special character **`@`**).
> - Rate limiting is enforced (5 failed attempts within 15 minutes triggers a lockout).
> - For production deployment, remember to change passwords immediately in **Admin -> Admin Users**.

---

## 3. Database Architecture (`database/WebsiteTailors.sql`)

The database uses **InnoDB** engine with `utf8mb4` character set and `utf8mb4_unicode_ci` collation.

### Core Tables & Models

1. **`admins`**: Administrative user accounts.
   - `id`, `username` (UNIQUE), `email` (UNIQUE), `password_hash`, `full_name`, `role` (`superadmin`, `admin`), `is_active`, `last_login_at`, `created_at`, `updated_at`.
2. **`login_attempts`**: Tracks authentication attempts for IP and username rate limiting.
   - `id`, `ip_address`, `username`, `attempted_at`, `is_successful`.
   - Indexed on `(ip_address, attempted_at)` and `(username, attempted_at)`.
3. **`hero_content`**: Headline, subheadline, description, buttons, and metrics for the homepage hero.
   - `id`, `badge_text`, `headline`, `subheadline`, `description`, `primary_button_text`, `primary_button_link`, `secondary_button_text`, `secondary_button_link`, `stats_json`, `updated_at`.
4. **`services`**: Agency service offerings.
   - `id`, `title`, `slug` (UNIQUE), `short_description`, `long_description`, `icon`, `features`, `display_order`, `status` (`published`, `draft`), `created_at`, `updated_at`.
5. **`projects`**: Portfolio and case studies.
   - `id`, `title`, `slug` (UNIQUE), `category`, `client_name`, `description`, `image`, `project_url`, `tags`, `display_order`, `is_featured`, `status`, `created_at`, `updated_at`.
6. **`process_steps`**: 5-step client delivery roadmap.
   - `id`, `step_number`, `title`, `description`, `display_order`, `status`, `created_at`, `updated_at`.
7. **`testimonials`**: Verified client feedback.
   - `id`, `client_name`, `company`, `position`, `content`, `rating`, `image`, `display_order`, `status`, `created_at`, `updated_at`.
8. **`leads`**: Incoming client inquiries submitted through the contact forms.
   - `id`, `name`, `email`, `phone`, `service_interested`, `budget`, `message`, `ip_address`, `user_agent`, `status` (`new`, `contacted`, `qualified`, `closed`, `archived`), `notes`, `created_at`, `updated_at`.
9. **`site_settings`**: Global key-value store for company branding, contact details, and social links.
   - `id`, `setting_key` (UNIQUE), `setting_value`, `setting_group` (`general`, `contact`, `social`, `branding`, `seo`), `created_at`, `updated_at`.

---

## 4. Security Architecture

### 1. Password Hashing & Verification
- Uses PHP's native `password_hash($password, PASSWORD_DEFAULT)`.
- Automatically tests for algorithmic cost upgrades on login with `password_needs_rehash()`.
- Zero plaintext or hardcoded credentials anywhere in the repository.

### 2. Session Hardening
- `session.use_strict_mode = 1`: Prevents uninitialized session ID acceptance.
- `session.cookie_httponly = 1`: Prevents client-side scripts from reading session cookies.
- `SameSite = Lax`: Defends against Cross-Site Request Forgery via third-party requests.
- Dynamic HTTPS detection sets `cookie_secure = true` automatically on production SSL hosts.
- Mandatory `session_regenerate_id(true)` executed upon successful authentication to destroy old session tokens and defeat session fixation attacks.

### 3. CSRF Protection
- Cryptographically strong tokens created using `bin2hex(random_bytes(32))`.
- Form injection helper: `<?= csrf_field() ?>`.
- Token retrieval helper: `csrf_token()`.
- Constant-time verification with `hash_equals()` in `verify_csrf()`.
- Middleware enforcer: `require_csrf()`.

### 4. SQL Injection Prevention
- The database wrapper (`Database` singleton in `config/database.php`) uses **PDO prepared statements** for all parameterized queries:
  - `Database::getInstance()->query($sql, $params)`
  - `Database::getInstance()->fetch($sql, $params)`
  - `Database::getInstance()->insert($table, $data)`
  - `Database::getInstance()->update($table, $data, $where, $whereParams)`
- `PDO::ATTR_EMULATE_PREPARES` is set to `false`, enforcing real prepared statements at the MySQL driver level.

### 5. Brute Force & Rate Limiting Defense
- Login attempts are logged in `login_attempts` with IP, username, and success state.
- If more than `MAX_LOGIN_ATTEMPTS` (5) failed attempts occur within `LOGIN_LOCKOUT_MINUTES` (15), further attempts from that IP or target account are rejected immediately.
- Timing attack mitigation: when an unknown username is supplied, a dummy hash verification is performed so execution timing remains identical.

### 6. XSS Prevention
- All user-controlled and database-retrieved strings are escaped before output using the `e()` helper:
  ```php
  <?= e($variable) ?>
  ```
  Which executes `htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8')`.

### 7. Secure File Uploads
- Files uploaded via `handle_file_upload()` in `includes/upload.php`:
  1. Inspects native PHP error codes.
  2. Asserts `is_uploaded_file()`.
  3. Enforces maximum file size (default 8MB).
  4. Whitelists file extensions (`jpg`, `jpeg`, `png`, `webp`, `svg`).
  5. Performs real MIME inspection via PHP's `finfo(FILEINFO_MIME_TYPE)` rather than trusting client-supplied headers.
  6. Scans SVGs for embedded `<script>` or event handlers.
  7. Renames all uploads to randomized 32-character hexadecimal strings (`bin2hex(random_bytes(16)) . '.' . $ext`).
  8. `uploads/.htaccess` turns off the PHP engine and explicitly denies execution of any executable script extension.

### 8. Apache Protection Rules (`.htaccess`)
- Disables directory browsing (`Options -Indexes`).
- Blocks direct HTTP access to hidden files (`.git`, `.env`).
- Restricts direct downloads of `.sql`, `.log`, `.conf`, and `.example` files.
- Sets defensive HTTP headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `X-XSS-Protection: 1; mode=block`.

---

## 5. Setup & Provisioning

### Method A: Web Setup Wizard (Shared Hosting / Hostinger)
1. Import `database/WebsiteTailors.sql` using phpMyAdmin or the Hostinger database manager.
2. Enter your MySQL database credentials in `config/database.php`.
3. Open `http://your-domain.com/setup.php` in your browser.
4. Fill out the Superadmin registration form and submit.
5. The setup wizard creates your Superadmin account, creates `config/installed.lock`, and permanently locks itself.
6. (Optional) Delete `setup.php` from your server.

### Method B: Command Line (CLI)
On local environments or servers with SSH terminal access:
```bash
# Interactive mode:
php bin/create_admin.php

# Direct arguments:
php bin/create_admin.php admin websietailorss@gmail.com "SuperSecurePassword123" "System Admin" superadmin
```

---

## 6. Hostinger Shared Hosting Deployment Checklist

1. **Upload Files**: Upload the project directory to `public_html/`.
2. **Create MySQL Database**: In Hostinger hPanel -> **Databases**, create a database and user.
3. **Import SQL**: Open phpMyAdmin in hPanel and import `database/WebsiteTailors.sql`.
4. **Configure Credentials**: Edit `config/database.php` and fill in `DB_NAME`, `DB_USER`, and `DB_PASS`.
5. **Set Environment**: In `config/config.php`, change `APP_ENV` to `'production'`.
6. **Create Admin**: Run `setup.php` in your browser once to initialize your admin account.
7. **Verify Permissions**: Ensure `uploads/` has write permissions (`chmod 0755`).
8. **Verify `.htaccess`**: Ensure Apache mod_rewrite and mod_headers are active on the host (standard on Hostinger).
