# Hostinger Deployment Guide — Website Tailors

This production build (`hostinger-production-build.zip`) is fully prepared for Hostinger Shared / Cloud hosting (LiteSpeed/Apache).

---

## Quick Deployment Steps

### Option A: Uploading the ZIP directly to Hostinger File Manager
1. Log in to **Hostinger hPanel** (https://hpanel.hostinger.com).
2. Go to **Websites** -> Manage -> **File Manager** (Files -> File Manager).
3. Navigate to **`public_html/`**.
4. Upload `hostinger-production-build.zip` directly to `public_html/`.
5. Right-click the uploaded zip file and select **Extract** into `public_html/`.
6. Ensure `.htaccess` is present in `public_html/` (enable "Show hidden files" in File Manager settings if needed).

### Option B: Deploying via GitHub Integration
1. Commit and push the project changes to your GitHub repository (`main` branch).
2. In Hostinger hPanel -> **Git**:
   - Repository: `https://github.com/your-username/your-repo`
   - Branch: `main`
   - Install Directory: `public_html`
3. Click **Deploy**.

---

## Database Configuration (MySQL)
1. In Hostinger hPanel -> **Databases**, create a new MySQL database:
   - Database Name: (e.g. `u123456789_websitetailors`)
   - Username: (e.g. `u123456789_admin`)
   - Password: (your secure password)
2. Open **phpMyAdmin** for this database and click **Import**.
3. Select `database/WebsiteTailors.sql` from your project and click **Go**.
4. In `public_html/config/database.php`, enter your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_PORT', 3306);
   define('DB_NAME', 'u123456789_websitetailors');
   define('DB_USER', 'u123456789_admin');
   define('DB_PASS', 'YourSecurePasswordHere');
   ```

---

## Initial Admin Setup
1. If you imported `database/WebsiteTailors.sql`, the default administrative credentials are:
   - Admin Login URL: `https://websitetailors.com/admin/login.php`
   - Username: `admin` (or `superadmin`)
   - Password: `Admin@12345`
2. Change your admin password immediately in **Admin -> Admin Users**.

---

## Production Security & Optimization Checks
- [x] **Canonical URLs**: Automatically redirects `www` to non-www and extensionless URLs.
- [x] **Production Error Handling**: `APP_ENV` automatically switches to `production` on `websitetailors.com` to prevent leaking PHP errors.
- [x] **File Permissions**:
  - `public_html/` -> `0755`
  - `public_html/uploads/` -> `0755`
  - `.htaccess` -> `0644`
- [x] **SSL Certificate**: Activate free Let's Encrypt SSL in Hostinger hPanel -> Security -> SSL.