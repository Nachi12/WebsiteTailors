<?php
/**
 * Website Tailors - SQLite Fallback Database Initializer
 *
 * Populates database/WebsiteTailors.sqlite with tables and initial seed data matching
 * WebsiteTailors.sql so local development and testing work without a running MySQL daemon.
 */

declare(strict_types=1);

function init_sqlite_database(string $sqliteFile): PDO
{
    $isNew = !file_exists($sqliteFile) || filesize($sqliteFile) === 0;
    $pdo = new PDO('sqlite:' . $sqliteFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA journal_mode = WAL;");
    $pdo->exec("PRAGMA busy_timeout = 15000;");
    $pdo->exec("PRAGMA synchronous = NORMAL;");

    if ($isNew) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'admin',
                is_active INTEGER NOT NULL DEFAULT 1,
                last_login_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                username TEXT NOT NULL,
                attempted_at TEXT NOT NULL,
                is_successful INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS hero_content (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                badge_text TEXT NOT NULL,
                headline TEXT NOT NULL,
                subheadline TEXT NOT NULL,
                description TEXT NOT NULL,
                primary_button_text TEXT NOT NULL,
                primary_button_link TEXT NOT NULL,
                secondary_button_text TEXT NOT NULL,
                secondary_button_link TEXT NOT NULL,
                stats_json TEXT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS services (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                short_description TEXT NOT NULL,
                long_description TEXT NOT NULL,
                icon TEXT NOT NULL DEFAULT 'code',
                features TEXT NULL,
                display_order INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'published',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_id INTEGER NULL,
                title TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                category TEXT NOT NULL,
                client_name TEXT NULL,
                description TEXT NOT NULL,
                image TEXT NULL,
                project_url TEXT NULL,
                tags TEXT NULL,
                display_order INTEGER NOT NULL DEFAULT 0,
                is_featured INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'published',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS process_steps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                step_number TEXT NOT NULL,
                title TEXT NOT NULL,
                description TEXT NOT NULL,
                display_order INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'published',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS testimonials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                client_name TEXT NOT NULL,
                company TEXT NOT NULL,
                position TEXT NULL,
                content TEXT NOT NULL,
                rating INTEGER NOT NULL DEFAULT 5,
                image TEXT NULL,
                display_order INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'published',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS leads (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT NULL,
                company TEXT NULL,
                service_interested TEXT NULL,
                budget TEXT NULL,
                message TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                user_agent TEXT NULL,
                status TEXT NOT NULL DEFAULT 'new',
                notes TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS site_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT NOT NULL UNIQUE,
                setting_value TEXT NULL,
                setting_group TEXT NOT NULL DEFAULT 'general',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_services_status_order ON services (status, display_order);
            CREATE INDEX IF NOT EXISTS idx_projects_status_featured_order ON projects (status, is_featured, display_order);
            CREATE INDEX IF NOT EXISTS idx_projects_client_id ON projects (client_id);
            CREATE INDEX IF NOT EXISTS idx_process_status_order ON process_steps (status, display_order);
            CREATE INDEX IF NOT EXISTS idx_testimonials_status_order ON testimonials (status, display_order);
            CREATE INDEX IF NOT EXISTS idx_leads_status_created ON leads (status, created_at);
        ");

        // Seed default superadmins (password: Admin@12345)
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO admins (username, email, password_hash, full_name, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'websietailorss@gmail.com', '$2y$12$ht.4P0FXjJ7ev7MsltCeSeQpBGGj/is8I.XzZ/WMTbz2NTau8dX4y', 'Website Tailors Administrator', 'superadmin', 1, $now, $now]);
        $stmt->execute(['superadmin', 'superadmin@rithamaya.com', '$2y$12$ht.4P0FXjJ7ev7MsltCeSeQpBGGj/is8I.XzZ/WMTbz2NTau8dX4y', 'Rithamaya Administrator', 'superadmin', 1, $now, $now]);

        // Seed Hero
        $stmt = $pdo->prepare("INSERT INTO hero_content (id, badge_text, headline, subheadline, description, primary_button_text, primary_button_link, secondary_button_text, secondary_button_link, stats_json, updated_at) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'Website Design & Redesign Studio / Bangalore',
            "AFFORDABLE WEBSITES\nFOR BUSINESSES IN\nBANGALORE.",
            'Running a business without a website? Or stuck with one that looks outdated? We build new websites and redesign old ones, fast, clean and at prices small businesses can afford.',
            'Running a business without a website? Or stuck with one that looks outdated? We build new websites and redesign old ones, fast, clean and at prices small businesses can afford.',
            'Get a Free Quote',
            '#contact',
            'See Our Work',
            '#work',
            json_encode([
                ['label' => 'Starting Price', 'value' => '₹[STARTING PRICE]'],
                ['label' => 'Delivery', 'value' => '[X] days'],
                ['label' => 'Consultation', 'value' => 'Free']
            ]),
            $now
        ]);

        // Seed Services
        $services = [
            ['Website Design for New Businesses', 'website-design-new-businesses', 'A complete business website for shops, clinics, studios, restaurants, coaches and service providers. Mobile-friendly, fast, with SEO basics built in.', 'A complete business website for shops, clinics, studios, restaurants, coaches and service providers. Mobile-friendly, fast, with SEO basics built in.', 'code', "Mobile-Friendly Design\nFast Loading Speed\nBasic SEO Setup\nContact & Enquiry Form", 1, 'published'],
            ['Website Redesign', 'website-redesign', 'Better design, faster loading and a layout that makes it easier for visitors to contact your business, without starting from scratch.', 'Better design, faster loading and a layout that makes it easier for visitors to contact your business, without starting from scratch.', 'layout', "Modern Visual Design\nMobile Usability Fixes\nSpeed Optimization\nImproved Contact Flow", 2, 'published'],
            ['WhatsApp Automation', 'whatsapp-automation', 'Auto-replies, enquiry capture, booking reminders and follow-ups on WhatsApp.', 'Auto-replies, enquiry capture, booking reminders and follow-ups on WhatsApp.', 'message-square', "Instant Enquiry Responses\nAutomated Follow-ups\nBooking Reminders\nDirect Lead Delivery", 3, 'published'],
            ['Website + WhatsApp Package', 'website-whatsapp-package', 'A website and WhatsApp automation set up together for businesses that want to turn more website visitors into enquiries.', 'A website and WhatsApp automation set up together for businesses that want to turn more website visitors into enquiries.', 'layers', "Complete Business Site\nWhatsApp Click-to-Chat\nAutomated Lead Alerts\nUnified Setup", 4, 'published']
        ];
        $svcStmt = $pdo->prepare("INSERT INTO services (title, slug, short_description, long_description, icon, features, display_order, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($services as $s) {
            $svcStmt->execute([$s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7], $now, $now]);
        }

        // Seed Projects (Concept & Demo projects clearly labelled)
        $projects = [
            ['Apex Logistics', 'apex-logistics', 'Website Concept', 'Apex Freight Concept', 'A real-time dispatch and logistics management interface concept built for high performance and clean operations.', '/assets/images/projects/apex-logistics.jpg', '#contact', 'PHP 8, SQLite, Custom Dashboard, Vanilla JS', 1, 1, 'published'],
            ['Kroma Studio', 'kroma-studio', 'Redesign Concept', 'Kroma Design Concept', 'An agency portfolio showcase built with dynamic dark-mode interactions, typography emphasis, and responsive layouts.', '/assets/images/projects/kroma-studio.jpg', '#contact', 'Vanilla CSS, Animation, Semantic HTML5', 2, 1, 'published'],
            ['Veloce E-Commerce', 'veloce-ecommerce', 'Demo Project', 'Veloce Storefront Demo', 'A lightweight storefront concept with instant product filtering, clean visual structure, and mobile-first checkout.', '/assets/images/projects/veloce-ecommerce.jpg', '#contact', 'Custom Cart, Responsive UI, SEO Optimized', 3, 1, 'published'],
            ['OmniFlow Automation', 'omniflow-automation', 'Digital System Concept', 'OmniFlow Prototype', 'An operational workflow interface prototype for client CRM management, automated invoicing, and task approvals.', '/assets/images/projects/omniflow-automation.jpg', '#contact', 'REST APIs, Background Workers, Role Security', 4, 0, 'published']
        ];
        $prjStmt = $pdo->prepare("INSERT INTO projects (title, slug, category, client_name, description, image, project_url, tags, display_order, is_featured, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($projects as $p) {
            $prjStmt->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9], $p[10], $now, $now]);
        }

        // Seed Process Steps
        $steps = [
            ['01', 'Tell us', 'Tell us about your business and what you need.', 1, 'published'],
            ['02', 'We plan', 'We plan the pages, design and price, and you approve it.', 2, 'published'],
            ['03', 'We build', 'We build it and you review it.', 3, 'published'],
            ['04', 'You launch', 'Your website goes live, and we stay available for support.', 4, 'published']
        ];
        $stepStmt = $pdo->prepare("INSERT INTO process_steps (step_number, title, description, display_order, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($steps as $st) {
            $stepStmt->execute([$st[0], $st[1], $st[2], $st[3], $st[4], $now, $now]);
        }

        // Testimonials: No fake testimonials; empty table by default
        // Real client testimonials will be added when genuine permission is received.

        // Seed Settings
        $settings = [
            ['company_name', 'Website Tailors', 'general'],
            ['tagline', 'Affordable Business Websites & WhatsApp Automation in Bangalore', 'general'],
            ['email', 'websietailorss@gmail.com', 'contact'],
            ['phone', '+91 9380552034', 'contact'],
            ['address', 'Bangalore, Karnataka, India', 'contact'],
            ['linkedin', 'https://linkedin.com/company/websitetailors', 'social'],
            ['instagram', 'https://instagram.com/websitetailors', 'social'],
            ['facebook', 'https://facebook.com/websitetailors', 'social'],
            ['github', 'https://github.com/websitetailors', 'social'],
            ['twitter', 'https://x.com/websitetailors', 'social'],
            ['logo', '/assets/images/logo.svg', 'branding'],
            ['favicon', '/assets/images/favicon.svg', 'branding'],
            ['meta_title', 'Affordable Website Design in Bangalore | Website Tailors', 'seo'],
            ['meta_description', 'Don\'t have a website yet? Or is your current one outdated? Website Tailors builds and redesigns fast, affordable business websites in Bangalore. Get a free quote.', 'seo'],
            ['primary_color', '#b8ff3d', 'branding'],
            ['announcement_text', 'Now booking website projects in Bangalore', 'general']
        ];
        $setStmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value, setting_group, created_at, updated_at) VALUES (?, ?, ?, ?, ?)");
        foreach ($settings as $set) {
            $setStmt->execute([$set[0], $set[1], $set[2], $now, $now]);
        }

        // Seed Sample Leads
        $sampleLeads = [
            ['Sarah Jenkins', 'sarah@aetherdynamics.com', '+1 (555) 234-5678', 'Aether Dynamics', 'Software', '₹2,50,000 - ₹5,00,000', 'We need a custom inventory and dispatch dashboard to connect with our ERP.', '127.0.0.1', 'new', null, date('Y-m-d H:i:s', time() - 3600 * 2)],
            ['David Kim', 'david@nexusretail.co', '+1 (555) 345-6789', 'Nexus Retail', 'Websites', '₹1,00,000 - ₹2,50,000', 'Looking to overhaul our high-traffic e-commerce storefront for better mobile performance.', '127.0.0.1', 'new', null, date('Y-m-d H:i:s', time() - 3600 * 5)],
            ['Amara Okafor', 'amara@solacefin.io', '+1 (555) 456-7890', 'Solace Financial', 'AI + Automation', '₹5,00,000+', 'Automating client onboarding workflows and compliance checks via webhooks.', '127.0.0.1', 'contacted', 'Had initial discovery call on Tuesday.', date('Y-m-d H:i:s', time() - 86400)],
            ['Rajesh Menon', 'rajesh@apexlogistics.in', '+91 98201 12345', 'Apex Logistics India', 'Websites', '₹1,50,000 - ₹3,00,000', 'Interested in expanding our current portal features.', '127.0.0.1', 'qualified', 'Ready for proposal review.', date('Y-m-d H:i:s', time() - 86400 * 2)],
            ['Ananya Sen', 'ananya@kromadesign.in', '+91 98302 23456', 'Kroma Design Studio', 'Software', '₹2,50,000 - ₹5,00,000', 'Annual maintenance and server scaling setup.', '127.0.0.1', 'closed', 'Contract signed.', date('Y-m-d H:i:s', time() - 86400 * 4)]
        ];
        $leadStmt = $pdo->prepare("INSERT INTO leads (name, email, phone, company, service_interested, budget, message, ip_address, status, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($sampleLeads as $l) {
            $leadStmt->execute([$l[0], $l[1], $l[2], $l[3], $l[4], $l[5], $l[6], $l[7], $l[8], $l[9], $l[10], $l[10]]);
        }
    }

    // Ensure metadata tracking table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS _db_meta (key TEXT PRIMARY KEY, val TEXT)");

    // CRM Schema Assurance (creates tables if not yet existing)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_name TEXT NOT NULL,
            company_name TEXT NULL,
            email TEXT NOT NULL,
            phone TEXT NOT NULL,
            alternate_phone TEXT NULL,
            service TEXT NULL,
            source TEXT NULL,
            status TEXT NOT NULL DEFAULT 'New',
            assigned_to TEXT NULL,
            notes TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS calls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            contact_name TEXT NOT NULL,
            company TEXT NULL,
            phone TEXT NOT NULL,
            type TEXT NOT NULL DEFAULT 'discovery',
            status TEXT NOT NULL DEFAULT 'scheduled',
            scheduled_at TEXT NOT NULL,
            duration_minutes INTEGER DEFAULT 0,
            priority TEXT NOT NULL DEFAULT 'normal',
            notes TEXT NULL,
            outcome TEXT NULL,
            created_at TEXT NOT NULL,
            call_datetime TEXT NULL
        );

        CREATE TABLE IF NOT EXISTS invoices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_number TEXT NOT NULL UNIQUE,
            invoice_type TEXT NOT NULL DEFAULT 'advance',
            client_id INTEGER NULL,
            client_name TEXT NOT NULL,
            project_id INTEGER NULL,
            project_name TEXT NULL,
            project_phase TEXT NULL,
            service TEXT NULL,
            amount REAL NOT NULL DEFAULT 0.00,
            project_total REAL NOT NULL DEFAULT 0.00,
            advance_amount REAL NOT NULL DEFAULT 0.00,
            amount_received REAL NOT NULL DEFAULT 0.00,
            balance_amount REAL NOT NULL DEFAULT 0.00,
            status TEXT NOT NULL DEFAULT 'paid',
            due_date TEXT NOT NULL,
            paid_at TEXT NULL,
            payment_mode TEXT NULL,
            transaction_reference TEXT NULL,
            bank_name TEXT NULL,
            payment_method_desc TEXT NULL,
            payment_date TEXT NULL,
            line_items TEXT NULL,
            notes TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NULL
        );

        CREATE TABLE IF NOT EXISTS revenue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_id INTEGER NULL,
            client_name TEXT NULL,
            lead_id INTEGER NULL,
            invoice_id INTEGER NULL,
            project_id INTEGER NULL,
            amount REAL NOT NULL DEFAULT 0.00,
            payment_type TEXT NOT NULL DEFAULT 'Bank Transfer',
            payment_status TEXT NOT NULL DEFAULT 'Paid',
            payment_date TEXT NOT NULL,
            transaction_reference TEXT NULL,
            bank_name TEXT NULL,
            service TEXT NOT NULL,
            notes TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );
    ");

    // Phase Migrations: Ensure all required columns exist on invoices
    try {
        $invCols = $pdo->query("PRAGMA table_info(invoices)")->fetchAll();
        $invColNames = array_column($invCols, 'name');
        if (!in_array('invoice_type', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN invoice_type TEXT NOT NULL DEFAULT 'advance'");
        }
        if (!in_array('client_id', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN client_id INTEGER NULL");
        }
        if (!in_array('project_id', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN project_id INTEGER NULL");
        }
        if (!in_array('project_name', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN project_name TEXT NULL");
        }
        if (!in_array('project_phase', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN project_phase TEXT NULL");
        }
        if (!in_array('service', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN service TEXT NULL");
        }
        if (!in_array('project_total', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN project_total REAL NOT NULL DEFAULT 0.00");
        }
        if (!in_array('advance_amount', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN advance_amount REAL NOT NULL DEFAULT 0.00");
        }
        if (!in_array('amount_received', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN amount_received REAL NOT NULL DEFAULT 0.00");
        }
        if (!in_array('balance_amount', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN balance_amount REAL NOT NULL DEFAULT 0.00");
        }
        if (!in_array('payment_mode', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN payment_mode TEXT NULL");
        }
        if (!in_array('transaction_reference', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN transaction_reference TEXT NULL");
        }
        if (!in_array('bank_name', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN bank_name TEXT NULL");
        }
        if (!in_array('payment_method_desc', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN payment_method_desc TEXT NULL");
        }
        if (!in_array('payment_date', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN payment_date TEXT NULL");
        }
        if (!in_array('line_items', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN line_items TEXT NULL");
        }
        if (!in_array('notes', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN notes TEXT NULL");
        }
        if (!in_array('updated_at', $invColNames, true)) {
            $pdo->exec("ALTER TABLE invoices ADD COLUMN updated_at TEXT NULL");
        }
    } catch (\Throwable $e) {
        error_log("SQLite invoices migration notice: " . $e->getMessage());
    }

    // Phase Migrations: Ensure revenue table has required fields
    try {
        $revCols = $pdo->query("PRAGMA table_info(revenue)")->fetchAll();
        $revColNames = array_column($revCols, 'name');
        if (!in_array('client_name', $revColNames, true)) {
            $pdo->exec("ALTER TABLE revenue ADD COLUMN client_name TEXT NULL");
        }
        if (!in_array('project_id', $revColNames, true)) {
            $pdo->exec("ALTER TABLE revenue ADD COLUMN project_id INTEGER NULL");
        }
        if (!in_array('transaction_reference', $revColNames, true)) {
            $pdo->exec("ALTER TABLE revenue ADD COLUMN transaction_reference TEXT NULL");
        }
        if (!in_array('bank_name', $revColNames, true)) {
            $pdo->exec("ALTER TABLE revenue ADD COLUMN bank_name TEXT NULL");
        }
    } catch (\Throwable $e) {
        error_log("SQLite revenue migration notice: " . $e->getMessage());
    }

    // Phase Migrations: Ensure projects table has client_id field
    try {
        $projCols = $pdo->query("PRAGMA table_info(projects)")->fetchAll();
        $projColNames = array_column($projCols, 'name');
        if (!in_array('client_id', $projColNames, true)) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN client_id INTEGER NULL");
        }
    } catch (\Throwable $e) {
        error_log("SQLite projects migration notice: " . $e->getMessage());
    }

    // Phase Migrations: Ensure leads table has required fields
    try {
        $leadCols = $pdo->query("PRAGMA table_info(leads)")->fetchAll();
        $existingLeadCols = array_column($leadCols, 'name');
        if (!in_array('client_id', $existingLeadCols, true)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN client_id INTEGER NULL");
        }
        if (!in_array('service', $existingLeadCols, true)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN service TEXT NULL");
        }
        if (!in_array('source', $existingLeadCols, true)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN source TEXT NULL DEFAULT 'Website Form'");
        }
        if (!in_array('call_status', $existingLeadCols, true)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN call_status TEXT NOT NULL DEFAULT 'Not Called'");
        }
        if (!in_array('last_called_at', $existingLeadCols, true)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN last_called_at TEXT NULL");
        }
        if (!in_array('next_followup_at', $existingLeadCols, true)) {
            $pdo->exec("ALTER TABLE leads ADD COLUMN next_followup_at TEXT NULL");
        }
    } catch (\Throwable) {}

    // Phase Migrations: Ensure calls table has required fields
    try {
        $callCols = $pdo->query("PRAGMA table_info(calls)")->fetchAll();
        $existingCallCols = array_column($callCols, 'name');
        if (!in_array('lead_id', $existingCallCols, true)) {
            $pdo->exec("ALTER TABLE calls ADD COLUMN lead_id INTEGER NULL");
        }
        if (!in_array('client_id', $existingCallCols, true)) {
            $pdo->exec("ALTER TABLE calls ADD COLUMN client_id INTEGER NULL");
        }
        if (!in_array('call_datetime', $existingCallCols, true)) {
            $pdo->exec("ALTER TABLE calls ADD COLUMN call_datetime TEXT NULL");
        }
        if (!in_array('next_followup_at', $existingCallCols, true)) {
            $pdo->exec("ALTER TABLE calls ADD COLUMN next_followup_at TEXT NULL");
        }
        $pdo->exec("UPDATE calls SET call_datetime = COALESCE(scheduled_at, created_at) WHERE call_datetime IS NULL");
    } catch (\Throwable) {}

    // Check if CRM sample data was already initialized once
    $isCrmSeeded = false;
    try {
        $isCrmSeeded = (bool)$pdo->query("SELECT 1 FROM _db_meta WHERE key = 'crm_seeded'")->fetchColumn();
    } catch (\Throwable) {}

    // If database already contains user data, mark as seeded to never overwrite user actions/deletions
    if (!$isCrmSeeded) {
        $existingCallsCount = (int)$pdo->query("SELECT COUNT(*) FROM calls")->fetchColumn();
        $existingRevCount = (int)$pdo->query("SELECT COUNT(*) FROM revenue")->fetchColumn();
        $existingInvCount = (int)$pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();

        // If records already exist from previous work, simply seal the database so nothing is reseeded
        if ($existingCallsCount > 0 || $existingRevCount > 0 || $existingInvCount > 0) {
            $pdo->exec("INSERT OR REPLACE INTO _db_meta (key, val) VALUES ('crm_seeded', '1')");
            return $pdo;
        }

        // Fresh database initialization: seed sample records ONCE
        $now = date('Y-m-d H:i:s');

        // 1. Sample Clients
        $sampleClients = [
            ['Rajesh Menon', 'Apex Logistics India', 'rajesh@apexlogistics.in', '+91 98201 12345', '+91 98201 12399', 'Websites', 'Website Inquiry', 'Active', 'Website Tailors Administrator', 'Enterprise dispatch and fleet management portal client.', date('Y-m-d H:i:s', time() - 86400 * 120), $now],
            ['Ananya Sen', 'Kroma Design Studio', 'ananya@kromadesign.in', '+91 98302 23456', null, 'Software', 'Referral', 'Active', 'Website Tailors Administrator', 'Studio portfolio maintenance and server scaling setup.', date('Y-m-d H:i:s', time() - 86400 * 90), $now],
            ['Rohan Singhania', 'Veloce E-Commerce', 'rohan@velocefashion.in', '+91 98403 34567', '+91 98403 34599', 'AI + Automation', 'LinkedIn', 'Active', 'Website Tailors Administrator', 'E-commerce headless checkout engine and automated inventory sync.', date('Y-m-d H:i:s', time() - 86400 * 60), $now],
            ['David Kim', 'Nexus Retail Co', 'david@nexusretail.co', '+91 98504 45678', null, 'Websites', 'Cold Outreach', 'Active', 'Website Tailors Administrator', 'Multi-brand e-commerce frontend redesign with Shopify Plus.', date('Y-m-d H:i:s', time() - 86400 * 45), $now]
        ];
        $clientStmt = $pdo->prepare("INSERT INTO clients (client_name, company_name, email, phone, alternate_phone, service, source, status, assigned_to, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($sampleClients as $c) {
            $clientStmt->execute($c);
        }

        // 2. Sample Leads
        $sampleLeads = [
            ['Vikram Malhotra', 'vikram@malhotratech.in', '+91 98201 12345', 'Malhotra Tech', 'Website', '₹1,50,000', 'Need a bespoke headless corporate portal.', '127.0.0.1', 'New', null, date('Y-m-d H:i:s', time() - 3600 * 2)],
            ['Ananya Sen', 'ananya@cloudnative.co', '+91 98302 23456', 'CloudNative Labs', 'Software', '₹2,50,000', 'Looking for a custom multi-tenant SaaS dashboard.', '127.0.0.1', 'New', null, date('Y-m-d H:i:s', time() - 3600 * 6)],
            ['Rohan Mehta', 'rohan@mehtalogistics.com', '+91 98403 34567', 'Mehta Logistics', 'AI + Automation', '₹1,80,000', 'Automate delivery dispatch route workflows.', '127.0.0.1', 'Contacted', 'Initial discovery call conducted.', date('Y-m-d H:i:s', time() - 86400 * 2)],
            ['Pooja Iyer', 'pooja@iyerfashion.com', '+91 98504 45678', 'Iyer Luxury', 'UI/UX', '₹95,000', 'Storefront mobile UI/UX overhaul.', '127.0.0.1', 'Contacted', 'Scheduled UI wireframe review.', date('Y-m-d H:i:s', time() - 86400 * 3)],
            ['Karan Kapoor', 'karan@kapoordigital.io', '+91 98605 56789', 'Kapoor Digital', 'Software', '₹3,00,000', 'Enterprise CRM connector with ERP.', '127.0.0.1', 'Qualified', 'Scope finalized, proposal preparing.', date('Y-m-d H:i:s', time() - 86400 * 5)],
            ['Sneha Roy', 'sneha@finflow.in', '+91 98706 67890', 'FinFlow Payments', 'AI + Automation', '₹2,20,000', 'Automated reconciliation and webhook gateway.', '127.0.0.1', 'Qualified', 'Technical assessment complete.', date('Y-m-d H:i:s', time() - 86400 * 6)],
            ['Arjun Verma', 'arjun@vermasteel.com', '+91 98807 78901', 'Verma Steel', 'Website', '₹1,20,000', 'Corporate manufacturing brand redesign.', '127.0.0.1', 'Proposal Sent', 'Proposal sent via email.', date('Y-m-d H:i:s', time() - 86400 * 8)],
            ['Divya Nair', 'divya@nairretail.in', '+91 98908 89012', 'Nair Retail Group', 'Software', '₹1,75,000', 'Inventory sync & billing POS portal.', '127.0.0.1', 'Proposal Sent', 'Awaiting board sign-off.', date('Y-m-d H:i:s', time() - 86400 * 10)],
            ['Rajesh Menon', 'rajesh@apexlogistics.in', '+91 98201 12345', 'Apex Logistics India', 'Website', '₹4,50,000', 'Enterprise logistics portal build.', '127.0.0.1', 'Converted', 'Client converted and signed SLA contract.', date('Y-m-d H:i:s', time() - 86400 * 35)],
            ['Ananya Sen', 'ananya@kromadesign.in', '+91 98302 23456', 'Kroma Design Studio', 'Software', '₹3,20,000', 'Design studio engine and retainers.', '127.0.0.1', 'Converted', 'Signed and active client.', date('Y-m-d H:i:s', time() - 86400 * 50)],
            ['Tarun Khanna', 'tarun@khannatech.com', '+91 99009 90123', 'Khanna Tech', 'Other', '₹60,000', 'Legacy WordPress bug fixes.', '127.0.0.1', 'Lost', 'Budget out of alignment; archived.', date('Y-m-d H:i:s', time() - 86400 * 40)]
        ];
        $leadStmt = $pdo->prepare("INSERT INTO leads (name, email, phone, company, service_interested, budget, message, ip_address, status, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($sampleLeads as $l) {
            $leadStmt->execute([$l[0], $l[1], $l[2], $l[3], $l[4], $l[5], $l[6], $l[7], $l[8], $l[9], $l[10], $l[10]]);
        }

        // 3. Sample Calls
        $sampleCalls = [
            ['Vikram Malhotra', 'Malhotra Tech', '+91 98201 12345', 'discovery', 'scheduled', date('Y-m-d H:i:s', time() - 3600 * 3), 30, 'high', 'Discovery call on website portal', null, date('Y-m-d H:i:s', time() - 86400 * 1)],
            ['Ananya Sen', 'CloudNative Labs', '+91 98302 23456', 'discovery', 'scheduled', date('Y-m-d H:i:s', time() - 3600 * 1), 30, 'high', 'Review multi-tenant dashboard requirements', null, date('Y-m-d H:i:s', time() - 86400 * 1)],
            ['Rohan Mehta', 'Mehta Logistics', '+91 98403 34567', 'follow_up', 'scheduled', date('Y-m-d H:i:s', time() + 3600 * 4), 25, 'normal', 'Follow-up on route dispatch workflow', null, date('Y-m-d H:i:s', time())],
            ['Pooja Iyer', 'Iyer Luxury', '+91 98504 45678', 'follow_up', 'scheduled', date('Y-m-d H:i:s', time() + 86400), 30, 'normal', 'Review UI/UX prototype wireframes', null, date('Y-m-d H:i:s', time())],
            ['Karan Kapoor', 'Kapoor Digital', '+91 98605 56789', 'check_in', 'completed', date('Y-m-d H:i:s', time() - 86400 * 1), 35, 'normal', 'Technical scoping discussion', 'Connected', date('Y-m-d H:i:s', time() - 86400 * 1)],
            ['Sneha Roy', 'FinFlow Payments', '+91 98706 67890', 'discovery', 'completed', date('Y-m-d H:i:s', time() - 86400 * 2), 20, 'normal', 'Initial webhook architecture discussion', 'Connected', date('Y-m-d H:i:s', time() - 86400 * 2)],
            ['Arjun Verma', 'Verma Steel', '+91 98807 78901', 'proposal_review', 'completed', date('Y-m-d H:i:s', time() - 86400 * 3), 40, 'high', 'Walkthrough of website milestone pricing', 'Call Back', date('Y-m-d H:i:s', time() - 86400 * 3)],
            ['Divya Nair', 'Nair Retail Group', '+91 98908 89012', 'check_in', 'completed', date('Y-m-d H:i:s', time() - 86400 * 4), 15, 'low', 'Left voicemail regarding contract amendment', 'No Answer', date('Y-m-d H:i:s', time() - 86400 * 4)],
            ['Rajesh Menon', 'Apex Logistics India', '+91 98201 12345', 'check_in', 'completed', date('Y-m-d H:i:s', time() - 86400 * 5), 25, 'normal', 'SLA server health review call', 'Connected', date('Y-m-d H:i:s', time() - 86400 * 5)],
            ['Ananya Sen', 'Kroma Design Studio', '+91 98302 23456', 'follow_up', 'completed', date('Y-m-d H:i:s', time() - 86400 * 6), 20, 'normal', 'Client requested afternoon call back', 'Call Back', date('Y-m-d H:i:s', time() - 86400 * 6)],
            ['Rohan Singhania', 'Veloce E-Commerce', '+91 98403 34567', 'check_in', 'completed', date('Y-m-d H:i:s', time() - 86400 * 7), 30, 'normal', 'E-commerce campaign sync', 'Connected', date('Y-m-d H:i:s', time() - 86400 * 7)],
            ['David Kim', 'Nexus Retail', '+1 (555) 345-6789', 'check_in', 'completed', date('Y-m-d H:i:s', time() - 86400 * 12), 10, 'low', 'Followed up on quote; phone rang out', 'No Answer', date('Y-m-d H:i:s', time() - 86400 * 12)]
        ];
        $callStmt = $pdo->prepare("INSERT INTO calls (contact_name, company, phone, type, status, scheduled_at, duration_minutes, priority, notes, outcome, created_at, call_datetime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($sampleCalls as $call) {
            $callStmt->execute([$call[0], $call[1], $call[2], $call[3], $call[4], $call[5], $call[6], $call[7], $call[8], $call[9], $call[10], $call[5]]);
        }

        // 4. Sample Invoices
        $sampleInvoices = [
            ['INV-2026-001', 'Apex Global Logistics', 'Website', 85000.00, 'paid', date('Y-m-d', time() - 86400 * 150), date('Y-m-d H:i:s', time() - 86400 * 148), date('Y-m-d H:i:s', time() - 86400 * 150)],
            ['INV-2026-002', 'Kroma Creative Agency', 'Software', 125000.00, 'paid', date('Y-m-d', time() - 86400 * 115), date('Y-m-d H:i:s', time() - 86400 * 114), date('Y-m-d H:i:s', time() - 86400 * 115)],
            ['INV-2026-003', 'Solace Financial', 'AI + Automation', 180000.00, 'paid', date('Y-m-d', time() - 86400 * 85), date('Y-m-d H:i:s', time() - 86400 * 84), date('Y-m-d H:i:s', time() - 86400 * 85)],
            ['INV-2026-004', 'Veloce Luxury Group', 'UI/UX', 95000.00, 'paid', date('Y-m-d', time() - 86400 * 60), date('Y-m-d H:i:s', time() - 86400 * 59), date('Y-m-d H:i:s', time() - 86400 * 60)],
            ['INV-2026-005', 'Nexus Retail Co', 'Website', 110000.00, 'paid', date('Y-m-d', time() - 86400 * 38), date('Y-m-d H:i:s', time() - 86400 * 37), date('Y-m-d H:i:s', time() - 86400 * 38)],
            ['INV-2026-006', 'TechNova Studios', 'Other', 45000.00, 'paid', date('Y-m-d', time() - 86400 * 25), date('Y-m-d H:i:s', time() - 86400 * 24), date('Y-m-d H:i:s', time() - 86400 * 25)],
            ['INV-2026-007', 'Apex Global Logistics', 'Software', 145000.00, 'paid', date('Y-m-d', time() - 86400 * 18), date('Y-m-d H:i:s', time() - 86400 * 17), date('Y-m-d H:i:s', time() - 86400 * 18)],
            ['INV-2026-008', 'Solace Financial', 'AI + Automation', 75000.00, 'paid', date('Y-m-d', time() - 86400 * 6), date('Y-m-d H:i:s', time() - 86400 * 5), date('Y-m-d H:i:s', time() - 86400 * 6)],
            ['INV-2026-009', 'Aether Dynamics', 'Software', 160000.00, 'paid', date('Y-m-d', time() - 86400 * 4), date('Y-m-d H:i:s', time() - 86400 * 3), date('Y-m-d H:i:s', time() - 86400 * 4)],
            ['INV-2026-010', 'Kroma Creative Agency', 'UI/UX', 65000.00, 'paid', date('Y-m-d', time() - 86400 * 1), date('Y-m-d H:i:s', time() - 3600 * 12), date('Y-m-d H:i:s', time() - 86400 * 1)],
            ['INV-2026-011', 'Veloce Luxury Group', 'AI + Automation', 120000.00, 'pending', date('Y-m-d', time() + 86400 * 8), null, date('Y-m-d H:i:s', time() - 86400 * 2)],
            ['INV-2026-012', 'Nexus Retail Co', 'Website', 90000.00, 'pending', date('Y-m-d', time() + 86400 * 14), null, date('Y-m-d H:i:s', time() - 86400 * 1)]
        ];
        $invStmt = $pdo->prepare("INSERT INTO invoices (invoice_number, client_name, service, amount, status, due_date, paid_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($sampleInvoices as $inv) {
            $invStmt->execute($inv);
        }

        // 5. Sample Revenue
        $sampleRevenues = [
            ['Apex Global Logistics', 'Website', 85000.00, 'Bank Transfer', 'Paid', date('Y-m-d H:i:s', time() - 86400 * 148), 'Corporate portal milestone 1'],
            ['Kroma Creative Agency', 'Software', 125000.00, 'Card', 'Paid', date('Y-m-d H:i:s', time() - 86400 * 114), 'Design studio engine deployment'],
            ['Solace Financial', 'AI + Automation', 180000.00, 'UPI', 'Paid', date('Y-m-d H:i:s', time() - 86400 * 84), 'Webhook reconciliation pipeline'],
            ['Veloce Luxury Group', 'UI/UX', 95000.00, 'Bank Transfer', 'Paid', date('Y-m-d H:i:s', time() - 86400 * 59), 'E-commerce UI/UX sprint'],
            ['Nexus Retail Co', 'Website', 110000.00, 'Bank Transfer', 'Paid', date('Y-m-d H:i:s', time() - 86400 * 37), 'Retail portal launch'],
            ['TechNova Studios', 'Other', 45000.00, 'UPI', 'Paid', date('Y-m-d H:i:s', time() - 86400 * 24), 'WordPress migration'],
            ['Apex Global Logistics', 'Software', 145000.00, 'Bank Transfer', 'Paid', date('Y-m-d H:i:s', time() - 86400 * 17), 'Fleet sync connector'],
            ['Solace Financial', 'AI + Automation', 75000.00, 'UPI', 'Paid', date('Y-m-d H:i:s', time() - 86400 * 5), 'Custom AI agent retainers'],
            ['Aether Dynamics', 'Software', 160000.00, 'Bank Transfer', 'Paid', date('Y-m-d H:i:s', time() - 86400 * 3), 'ERP integration contract'],
            ['Kroma Creative Agency', 'UI/UX', 65000.00, 'Card', 'Paid', date('Y-m-d H:i:s', time() - 3600 * 12), 'Brand guidelines sprint'],
            ['Veloce Luxury Group', 'AI + Automation', 120000.00, 'Bank Transfer', 'Pending', date('Y-m-d H:i:s', time() - 86400 * 2), 'Awaiting settlement invoice INV-2026-011'],
            ['Nexus Retail Co', 'Website', 90000.00, 'Bank Transfer', 'Pending', date('Y-m-d H:i:s', time() - 86400 * 1), 'Milestone 2 deliverable invoice INV-2026-012']
        ];
        $revStmt = $pdo->prepare("
            INSERT INTO revenue (client_id, lead_id, invoice_id, amount, payment_type, payment_status, payment_date, service, notes, created_at, updated_at)
            VALUES (
                (SELECT id FROM clients WHERE company_name = ? OR client_name = ? LIMIT 1),
                NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");
        foreach ($sampleRevenues as $r) {
            $revStmt->execute([$r[0], $r[0], $r[2], $r[3], $r[4], $r[5], $r[1], $r[6], $r[5], $now]);
        }

        // Link sample calls to leads
        try {
            $pdo->exec("
                UPDATE calls 
                SET lead_id = (SELECT id FROM leads WHERE leads.name = calls.contact_name LIMIT 1)
                WHERE lead_id IS NULL;
            ");
        } catch (\Throwable) {}

        // Mark CRM as seeded permanently
        $pdo->exec("INSERT OR REPLACE INTO _db_meta (key, val) VALUES ('crm_seeded', '1')");
    }

    return $pdo;
}
