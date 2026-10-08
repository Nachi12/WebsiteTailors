<?php
/**
 * WebsiteTailors - General Utility & Helper Functions
 * 
 * Provides flash messaging, redirection, JSON responses,
 * site settings helpers, slug generation, and formatting.
 */

declare(strict_types=1);

if (!defined('WebsiteTailors_INIT')) {
    die('Direct access not permitted.');
}

/**
 * Redirect safely to another URL
 *
 * @param string $url
 * @param int $statusCode
 */
function redirect(string $url, int $statusCode = 302): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Location: ' . $url, true, $statusCode);
    exit;
}

/**
 * Set a session flash message
 *
 * @param string $type 'success' | 'error' | 'warning' | 'info'
 * @param string $message
 */
function set_flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION['flash_messages'][$type][] = $message;
}

/**
 * Get and flush flash messages
 *
 * @param string|null $type
 * @return array<int, string>
 */
function get_flash(?string $type = null): array
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['flash_messages'])) {
        return [];
    }

    if ($type !== null) {
        $messages = $_SESSION['flash_messages'][$type] ?? [];
        unset($_SESSION['flash_messages'][$type]);
        return $messages;
    }

    $all = $_SESSION['flash_messages'];
    $_SESSION['flash_messages'] = [];
    return $all;
}

/**
 * Check if flash messages exist
 *
 * @param string|null $type
 * @return bool
 */
function has_flash(?string $type = null): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['flash_messages'])) {
        return false;
    }
    return $type !== null ? !empty($_SESSION['flash_messages'][$type]) : true;
}

/**
 * Send a standardized JSON response
 *
 * @param array<string, mixed> $data
 * @param int $statusCode
 */
function json_response(array $data, int $statusCode = 200): void
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Fetch a single site setting from cache or DB
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function get_setting(string $key, mixed $default = null): mixed
{
    $settings = get_all_settings();
    return $settings[$key] ?? $default;
}

/**
 * Fetch all site settings as a key => value array
 *
 * @return array<string, string|null>
 */
/**
 * Fetch all site settings as a key => value array with fallback defaults
 *
 * @return array<string, string|null>
 */
/**
 * Fetch all site settings as a key => value array with request-level memoization
 *
 * @return array<string, string|null>
 */
function get_all_settings(bool $refresh = false): array
{
    static $cachedSettings = null;
    if ($refresh) {
        $cachedSettings = null;
    }
    if ($cachedSettings !== null) {
        return $cachedSettings;
    }

    $defaults = [
        'company_name'       => 'Website Tailors',
        'tagline'            => 'Affordable Business Websites & WhatsApp Automation in Bangalore',
        'email'              => 'websietailorss@gmail.com',
        'phone'              => '+91 9380552034',
        'address'            => 'Bangalore, Karnataka, India',
        'linkedin'           => 'https://linkedin.com/company/websitetailors',
        'instagram'          => 'https://instagram.com/websitetailors',
        'facebook'           => 'https://facebook.com/websitetailors',
        'github'             => 'https://github.com/websitetailors',
        'twitter'            => 'https://x.com/websitetailors',
        'logo'               => '/assets/images/logo.svg',
        'favicon'            => '/assets/images/favicon.svg',
        'meta_title'         => 'Affordable Website Design in Bangalore | Website Tailors',
        'meta_description'   => 'Don\'t have a website yet? Or is your current one outdated? Website Tailors builds and redesigns fast, affordable business websites in Bangalore. Get a free quote.',
        'primary_color'      => '#b8ff3d',
        'announcement_text'  => 'Now booking website projects in Bangalore'
    ];

    try {
        $db = Database::getInstance();
        if ($db->isConnected()) {
            $rows = $db->fetchAll("SELECT setting_key, setting_value FROM site_settings");
            if (!empty($rows)) {
                $settings = [];
                foreach ($rows as $row) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                $cachedSettings = array_merge($defaults, $settings);
                return $cachedSettings;
            }
        }
    } catch (\Throwable $e) {
        error_log("get_all_settings notice: " . $e->getMessage());
    }

    $cachedSettings = $defaults;
    return $cachedSettings;
}

/**
 * Update or insert a site setting
 *
 * @param string $key
 * @param string $value
 * @param string $group
 * @return bool
 */
function update_setting(string $key, string $value, string $group = 'general'): bool
{
    try {
        $db = Database::getInstance();
        if (!$db->isConnected()) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $existing = $db->fetch("SELECT id FROM site_settings WHERE setting_key = :k", [':k' => $key]);
        if ($existing) {
            $db->update('site_settings', [
                'setting_value' => $value,
                'updated_at'    => $now
            ], 'setting_key = :k', [':k' => $key]);
        } else {
            $db->insert('site_settings', [
                'setting_key'   => $key,
                'setting_value' => $value,
                'setting_group' => $group,
                'created_at'    => $now,
                'updated_at'    => $now
            ]);
        }
        get_all_settings(true);
        return true;
    } catch (\Throwable $e) {
        error_log("update_setting error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch Hero content row with request-level memoization
 *
 * @return array<string, mixed>
 */
function get_hero_content(): array
{
    static $cachedHero = null;
    if ($cachedHero !== null) {
        return $cachedHero;
    }

    $defaults = [
        'badge_text'            => 'Website Design & Redesign Studio / Bangalore',
        'headline'              => "AFFORDABLE WEBSITES\nFOR BUSINESSES IN\nBANGALORE.",
        'subheadline'           => 'Running a business without a website? Or stuck with one that looks outdated? We build new websites and redesign old ones, fast, clean and at prices small businesses can afford.',
        'description'           => 'Running a business without a website? Or stuck with one that looks outdated? We build new websites and redesign old ones, fast, clean and at prices small businesses can afford.',
        'primary_button_text'   => 'Get a Free Quote',
        'primary_button_link'   => '#contact',
        'secondary_button_text' => 'See Our Work',
        'secondary_button_link' => '#work',
        'stats_json'            => '[{"label":"Starting Price","value":"₹[STARTING PRICE]"},{"label":"Delivery","value":"[X] days"},{"label":"Consultation","value":"Free"}]'
    ];

    try {
        $db = Database::getInstance();
        if ($db->isConnected()) {
            $hero = $db->fetch("SELECT * FROM hero_content ORDER BY id ASC LIMIT 1");
            if (!empty($hero)) {
                $cachedHero = array_merge($defaults, $hero);
                return $cachedHero;
            }
        }
    } catch (\Throwable $e) {
        error_log("get_hero_content notice: " . $e->getMessage());
    }

    $cachedHero = $defaults;
    return $cachedHero;
}

/**
 * Fetch published services with request-level memoization
 *
 * Returns live DB array if DB is connected (even if empty, for graceful empty state),
 * or defaults if DB is completely offline.
 *
 * @return array<int, array<string, mixed>>
 */
function get_services(): array
{
    static $cachedServices = null;
    if ($cachedServices !== null) {
        return $cachedServices;
    }

    $defaults = [
        [
            'id' => 1,
            'title' => 'Website Design for New Businesses',
            'slug' => 'website-design-new-businesses',
            'short_description' => 'A complete business website for shops, clinics, studios, restaurants, coaches and service providers. Mobile-friendly, fast, with SEO basics built in.',
            'long_description' => 'A complete business website for shops, clinics, studios, restaurants, coaches and service providers. Mobile-friendly, fast, with SEO basics built in.',
            'icon' => 'code',
            'display_order' => 1,
            'status' => 'published'
        ],
        [
            'id' => 2,
            'title' => 'Website Redesign',
            'slug' => 'website-redesign',
            'short_description' => 'Better design, faster loading and a layout that makes it easier for visitors to contact your business, without starting from scratch.',
            'long_description' => 'Better design, faster loading and a layout that makes it easier for visitors to contact your business, without starting from scratch.',
            'icon' => 'layout',
            'display_order' => 2,
            'status' => 'published'
        ],
        [
            'id' => 3,
            'title' => 'WhatsApp Automation',
            'slug' => 'whatsapp-automation',
            'short_description' => 'Auto-replies, enquiry capture, booking reminders and follow-ups on WhatsApp.',
            'long_description' => 'Auto-replies, enquiry capture, booking reminders and follow-ups on WhatsApp.',
            'icon' => 'message-square',
            'display_order' => 3,
            'status' => 'published'
        ],
        [
            'id' => 4,
            'title' => 'Website + WhatsApp Package',
            'slug' => 'website-whatsapp-package',
            'short_description' => 'A website and WhatsApp automation set up together for businesses that want to turn more website visitors into enquiries.',
            'long_description' => 'A website and WhatsApp automation set up together for businesses that want to turn more website visitors into enquiries.',
            'icon' => 'layers',
            'display_order' => 4,
            'status' => 'published'
        ]
    ];

    try {
        $db = Database::getInstance();
        if ($db->isConnected()) {
            $rows = $db->fetchAll("SELECT * FROM services WHERE status = 'published' ORDER BY display_order ASC, id ASC");
            if (!empty($rows)) {
                $cachedServices = $rows;
                return $cachedServices;
            }
        }
    } catch (\Throwable $e) {
        error_log("get_services notice: " . $e->getMessage());
    }

    $cachedServices = $defaults;
    return $cachedServices;
}

/**
 * Fetch published projects with optional category filter and request-level memoization
 *
 * @param string|null $category
 * @param int|null $limit
 * @return array<int, array<string, mixed>>
 */
function get_projects(?string $category = null, ?int $limit = null): array
{
    static $cachedProjects = [];
    $cacheKey = ($category ?? 'all') . '_' . ($limit ?? 'all');
    if (isset($cachedProjects[$cacheKey])) {
        return $cachedProjects[$cacheKey];
    }

    $defaults = [
        [
            'id' => 1,
            'title' => 'Apex Logistics',
            'slug' => 'apex-logistics',
            'category' => 'Website Concept',
            'client_name' => 'Apex Freight Concept',
            'description' => 'A real-time dispatch and logistics management interface concept built for high performance and clean operations.',
            'image' => '/assets/images/projects/apex-logistics.jpg',
            'project_url' => '#contact',
            'tags' => 'PHP 8, SQLite, Custom Dashboard, Vanilla JS',
            'display_order' => 1,
            'is_featured' => 1,
            'status' => 'published'
        ],
        [
            'id' => 2,
            'title' => 'Kroma Studio',
            'slug' => 'kroma-studio',
            'category' => 'Redesign Concept',
            'client_name' => 'Kroma Design Concept',
            'description' => 'An agency portfolio showcase built with dynamic dark-mode interactions, typography emphasis, and responsive layouts.',
            'image' => '/assets/images/projects/kroma-studio.jpg',
            'project_url' => '#contact',
            'tags' => 'Vanilla CSS, Animation, Semantic HTML5',
            'display_order' => 2,
            'is_featured' => 1,
            'status' => 'published'
        ],
        [
            'id' => 3,
            'title' => 'Veloce E-Commerce',
            'slug' => 'veloce-ecommerce',
            'category' => 'Demo Project',
            'client_name' => 'Veloce Storefront Demo',
            'description' => 'A lightweight storefront concept with instant product filtering, clean visual structure, and mobile-first checkout.',
            'image' => '/assets/images/projects/veloce-ecommerce.jpg',
            'project_url' => '#contact',
            'tags' => 'Custom Cart, Responsive UI, SEO Optimized',
            'display_order' => 3,
            'is_featured' => 1,
            'status' => 'published'
        ],
        [
            'id' => 4,
            'title' => 'OmniFlow Automation',
            'slug' => 'omniflow-automation',
            'category' => 'Digital System Concept',
            'client_name' => 'OmniFlow Prototype',
            'description' => 'An operational workflow interface prototype for client CRM management, automated invoicing, and task approvals.',
            'image' => '/assets/images/projects/omniflow-automation.jpg',
            'project_url' => '#contact',
            'tags' => 'REST APIs, Background Workers, Role Security',
            'display_order' => 4,
            'is_featured' => 0,
            'status' => 'published'
        ]
    ];

    try {
        $db = Database::getInstance();
        if ($db->isConnected()) {
            $sql = "SELECT * FROM projects WHERE status = 'published'";
            $params = [];
            if (!empty($category)) {
                $sql .= " AND category = :cat";
                $params[':cat'] = $category;
            }
            $sql .= " ORDER BY display_order ASC, id DESC";
            if ($limit !== null && $limit > 0) {
                $sql .= " LIMIT " . (int)$limit;
            }

            $rows = $db->fetchAll($sql, $params);
            $cachedProjects[$cacheKey] = $rows;
            return $cachedProjects[$cacheKey];
        }
    } catch (\Throwable $e) {
        error_log("get_projects notice: " . $e->getMessage());
    }

    $cachedProjects[$cacheKey] = $defaults;
    return $cachedProjects[$cacheKey];
}

/**
 * Fetch published process steps with request-level memoization
 *
 * @return array<int, array<string, mixed>>
 */
function get_process_steps(): array
{
    static $cachedSteps = null;
    if ($cachedSteps !== null) {
        return $cachedSteps;
    }

    $defaults = [
        [
            'id' => 1,
            'step_number' => '01',
            'title' => 'Tell us.',
            'description' => "Tell us what you're trying to build, fix or improve.",
            'display_order' => 1,
            'status' => 'published'
        ],
        [
            'id' => 2,
            'step_number' => '02',
            'title' => 'We plan.',
            'description' => 'We define the experience, technology and scope.',
            'display_order' => 2,
            'status' => 'published'
        ],
        [
            'id' => 3,
            'step_number' => '03',
            'title' => 'We build.',
            'description' => 'Design, development and testing happen together.',
            'display_order' => 3,
            'status' => 'published'
        ],
        [
            'id' => 4,
            'step_number' => '04',
            'title' => 'You launch.',
            'description' => 'Your product goes live and starts doing its job.',
            'display_order' => 4,
            'status' => 'published'
        ]
    ];

    try {
        $db = Database::getInstance();
        if ($db->isConnected()) {
            $rows = $db->fetchAll("SELECT * FROM process_steps WHERE status = 'published' ORDER BY display_order ASC, id ASC");
            $cachedSteps = $rows;
            return $cachedSteps;
        }
    } catch (\Throwable $e) {
        error_log("get_process_steps notice: " . $e->getMessage());
    }

    $cachedSteps = $defaults;
    return $cachedSteps;
}

/**
 * Fetch published testimonials with request-level memoization
 *
 * @param int|null $limit
 * @return array<int, array<string, mixed>>
 */
function get_testimonials(?int $limit = null): array
{
    static $cachedTestimonials = [];
    $cacheKey = $limit ?? 'all';
    if (isset($cachedTestimonials[$cacheKey])) {
        return $cachedTestimonials[$cacheKey];
    }

    $defaults = [];

    try {
        $db = Database::getInstance();
        if ($db->isConnected()) {
            $sql = "SELECT * FROM testimonials WHERE status = 'published' ORDER BY display_order ASC, id DESC";
            if ($limit !== null && $limit > 0) {
                $sql .= " LIMIT " . (int)$limit;
            }
            $rows = $db->fetchAll($sql);
            $cachedTestimonials[$cacheKey] = $rows;
            return $cachedTestimonials[$cacheKey];
        }
    } catch (\Throwable $e) {
        error_log("get_testimonials notice: " . $e->getMessage());
    }

    $cachedTestimonials[$cacheKey] = $defaults;
    return $cachedTestimonials[$cacheKey];
}

/**
 * Reusable CamelCase Aliases (for clean API conformity)
 */
function getHero(): array { return get_hero_content(); }
function getServices(): array { return get_services(); }
function getProjects(?string $category = null, ?int $limit = null): array { return get_projects($category, $limit); }
function getProcessSteps(): array { return get_process_steps(); }
function getTestimonials(?int $limit = null): array { return get_testimonials($limit); }
function getSiteSettings(): array { return get_all_settings(); }

/**
 * Image Fallback Helper
 *
 * Verifies if an image exists on the filesystem. If missing or null, returns a safe,
 * visually designed SVG placeholder or fallback URL so broken images never render.
 *
 * @param string|null $path Relative path e.g. '/uploads/projects/xyz.webp'
 * @param string $type 'project' | 'avatar' | 'logo' | 'favicon'
 * @return string
 */
function get_image_url(?string $path, string $type = 'project'): string
{
    if (!empty($path)) {
        $clean = ltrim($path, '/\\');
        $fullPath = ROOT_PATH . '/' . $clean;
        if (file_exists($fullPath) && is_file($fullPath)) {
            return BASE_URL . '/' . $clean;
        }
        // If it starts with http, return as-is
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
    }

    // High quality visual SVG placeholders (inline Data URIs) to ensure zero broken image icons
    if ($type === 'avatar') {
        return 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="#181922"/><circle cx="50" cy="40" r="20" fill="#9699A8"/><path d="M20,85 C20,68 35,65 50,65 C65,65 80,68 80,85 Z" fill="#9699A8"/></svg>');
    }

    if ($type === 'logo') {
        return ASSETS_URL . '/images/logo.svg';
    }

    if ($type === 'favicon') {
        return ASSETS_URL . '/images/favicon.svg';
    }

    // Default Project visual placeholder
    return 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500" viewBox="0 0 800 500"><rect width="800" height="500" fill="#12131A"/><rect x="40" y="40" width="720" height="420" rx="12" fill="#1A1B24" stroke="#2B2D3C" stroke-width="2"/><circle cx="80" cy="75" r="7" fill="#FF5F56"/><circle cx="105" cy="75" r="7" fill="#FFBD2E"/><circle cx="130" cy="75" r="7" fill="#27C93F"/><rect x="80" y="130" width="140" height="24" rx="4" fill="#B8FF3D" opacity="0.9"/><rect x="80" y="175" width="480" height="40" rx="6" fill="#FFFFFF" opacity="0.9"/><rect x="80" y="240" width="640" height="12" rx="4" fill="#525668"/><rect x="80" y="265" width="540" height="12" rx="4" fill="#525668"/><rect x="80" y="320" width="180" height="44" rx="8" fill="#B8FF3D"/></svg>');
}

/**
 * Get core problem & positioning principles (Phase 5)
 *
 * @return array<int, array{number: string, title: string, description: string}>
 */
function get_principles(): array
{
    return [
        [
            'number'      => '01 / NEW ONLINE',
            'title'       => 'No website yet?',
            'description' => 'Your customers are already searching on Google. We put your business online with a professional site, WhatsApp button and Google-friendly setup.'
        ],
        [
            'number'      => '02 / REDESIGN',
            'title'       => 'Website looks outdated?',
            'description' => 'We redesign it so it looks modern, loads fast, works on phones and is structured for better search visibility.'
        ],
        [
            'number'      => '03 / AUTOMATION',
            'title'       => 'Want to save time on enquiries?',
            'description' => 'We connect your site to WhatsApp so enquiries and follow-ups can be handled more efficiently.'
        ]
    ];
}

/**
 * Fetch Pricing Packages (Phase 7)
 *
 * @return array<int, array{name: string, best_for: string, price: string, features: array<int, string>}>
 */
function get_pricing_packages(): array
{
    return [
        [
            'name'     => 'Starter Website',
            'best_for' => 'Businesses going online for the first time',
            'price'    => 'Starting at ₹[STARTING PRICE]',
            'features' => [
                'Complete business website',
                'Mobile-friendly & fast loading',
                'Google-friendly SEO basics',
                'WhatsApp click-to-chat button',
                'Enquiry form setup'
            ]
        ],
        [
            'name'     => 'Redesign',
            'best_for' => 'Businesses with an old website',
            'price'    => 'Starting at ₹[STARTING PRICE]',
            'features' => [
                'Modern visual redesign',
                'Faster page loading speed',
                'Mobile usability optimization',
                'Re-structured contact flow',
                'SEO metadata preservation'
            ]
        ],
        [
            'name'     => 'Website + WhatsApp',
            'best_for' => 'Businesses wanting more enquiries',
            'price'    => 'Starting at ₹[STARTING PRICE]',
            'features' => [
                'Complete business website',
                'WhatsApp auto-replies & workflows',
                'Automated enquiry capture',
                'Instant lead alerts',
                'Full setup & launch support'
            ]
        ]
    ];
}

/**
 * Fetch Frequently Asked Questions (Phase 9)
 *
 * @return array<int, array{question: string, answer: string}>
 */
function get_faqs(): array
{
    return [
        [
            'question' => 'How much does a website cost in Bangalore?',
            'answer'   => 'Pricing depends on your specific requirements such as page count and features. We offer clear upfront quotes with no hidden charges starting at ₹[STARTING PRICE].'
        ],
        [
            'question' => 'I already run a business. Do I really need a website?',
            'answer'   => 'Yes. Most customers look for local businesses on Google before calling or visiting. A website gives your business credibility and makes it easy for customers to contact you.'
        ],
        [
            'question' => 'Can you redesign my existing website?',
            'answer'   => 'Yes, we specialize in redesigning outdated websites to improve loading speed, mobile usability, visual design and search visibility.'
        ],
        [
            'question' => 'How long does it take?',
            'answer'   => 'Most business websites are planned, designed and launched within [X] days depending on scope and feedback cycles.'
        ],
        [
            'question' => 'Will my website show up on Google?',
            'answer'   => 'Yes, every website we build includes clean HTML structure, essential meta tags, sitemap submission and basic local SEO setup so search engines can index your pages.'
        ]
    ];
}

/**
 * Process and save incoming lead inquiry with validation, CSRF, and honeypot checks
 *
 * @param array<string, mixed> $input
 * @return array{success: bool, error: ?string, message: string}
 */
function process_lead_inquiry(array $input): array
{
    // 1. Prevent unexpected file uploads on contact endpoint
    if (!empty($_FILES)) {
        // Discard any unexpected file uploads
        foreach ($_FILES as $fileKey => $fileArr) {
            if (isset($fileArr['tmp_name']) && is_uploaded_file($fileArr['tmp_name'])) {
                @unlink($fileArr['tmp_name']);
            }
        }
    }

    // 2. Honeypot anti-spam verification: website_url field must be blank
    if (!empty($input['website_url'])) {
        // Silently accept bots without saving to database or notifying
        return [
            'success' => true,
            'error'   => null,
            'message' => "Thanks! Your enquiry has been received. We'll get back to you shortly."
        ];
    }

    // 3. CSRF Token verification
    $csrfToken = $input['csrf_token'] ?? null;
    if (!verify_csrf($csrfToken)) {
        return [
            'success' => false,
            'error'   => 'Security session token expired. Please refresh the page and try again.',
            'message' => ''
        ];
    }

    // 4. Sanitize and trim inputs
    $name     = sanitize_text($input['name'] ?? '');
    $email    = sanitize_email($input['email'] ?? '');
    $phone    = sanitize_text($input['phone'] ?? '');
    $company  = sanitize_text($input['company'] ?? $input['business'] ?? '');
    $service  = sanitize_text($input['service'] ?? '');
    $budget   = sanitize_text($input['budget'] ?? '');
    $goal     = sanitize_text($input['goal'] ?? '');
    $details  = sanitize_text($input['message'] ?? $input['project_details'] ?? '');
    if (empty($goal) && !empty($details)) {
        $goal = 'Inquiry / Project Discussion';
    }
    $source   = sanitize_text($input['source'] ?? $input['lead_source'] ?? 'Website Questionnaire');

    // Validation for Website Questionnaire and Contact submissions
    if (empty($name)) {
        return ['success' => false, 'error' => 'Please enter your name.', 'message' => ''];
    }
    if (empty($phone) && empty($email)) {
        return ['success' => false, 'error' => 'Please provide a valid phone number or email address.', 'message' => ''];
    }

    $message  = '';
    if (!empty($goal)) {
        $message .= "Goal: " . $goal;
    }
    if (!empty($details)) {
        $message .= (!empty($message) ? " | " : "") . "Details: " . $details;
    }
    if (empty($message)) {
        $message = "Submitted via Website Tailors Interactive Project Questionnaire.";
    }

    // 5. Duplicate Protection & Rapid Flood Prevention:
    // Do not blindly reject repeat enquiries from the same email (returning customer may submit multiple enquiries).
    // Only intercept clear duplicate submissions (same email & message) within a rapid 60-second window.
    $nowTime = time();
    $ipAddress = get_client_ip();
    $db = Database::getInstance();

    if (!empty($email) && $db->isConnected()) {
        try {
            $cutoffRapid = date('Y-m-d H:i:s', $nowTime - 60);
            $duplicateCount = (int)$db->fetchColumn(
                "SELECT COUNT(*) FROM leads WHERE LOWER(email) = :email AND message = :msg AND created_at >= :cutoff",
                [':email' => strtolower((string)$email), ':msg' => $message, ':cutoff' => $cutoffRapid]
            );
            if ($duplicateCount > 0) {
                // Return success to visitor without creating duplicate DB entry
                return [
                    'success' => true,
                    'error'   => null,
                    'message' => "Thanks! Your enquiry has been received. We'll get back to you shortly."
                ];
            }
        } catch (\Throwable $e) {
            error_log("Duplicate protection check notice: " . $e->getMessage());
        }
    }

    // 6. Strict Server-Side Validation & Input Length Limits
    // Name: required, 2 - 100 characters
    if (empty($name) || mb_strlen($name) < 2) {
        return ['success' => false, 'error' => 'Please provide your full name (minimum 2 characters).', 'message' => ''];
    }
    if (mb_strlen($name) > 100) {
        return ['success' => false, 'error' => 'Name cannot exceed 100 characters.', 'message' => ''];
    }

    // Email: required, RFC compliant, prevent header injection (no newlines)
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Please provide a valid email address.', 'message' => ''];
    }
    if (str_contains($email, "\r") || str_contains($email, "\n")) {
        return ['success' => false, 'error' => 'Invalid email format.', 'message' => ''];
    }
    if (mb_strlen($email) > 150) {
        return ['success' => false, 'error' => 'Email address cannot exceed 150 characters.', 'message' => ''];
    }

    // Phone: optional, max 50 characters, numeric/formatting symbols only
    if (!empty($phone)) {
        if (mb_strlen($phone) > 50) {
            return ['success' => false, 'error' => 'Phone number cannot exceed 50 characters.', 'message' => ''];
        }
        if (!preg_match('/^[0-9+\-\s().]{0,50}$/', $phone)) {
            return ['success' => false, 'error' => 'Please provide a valid phone number format.', 'message' => ''];
        }
    }

    // Company: optional, max 100 characters
    if (!empty($company) && mb_strlen($company) > 100) {
        return ['success' => false, 'error' => 'Company name cannot exceed 100 characters.', 'message' => ''];
    }

    // Service: max 100 characters
    if (mb_strlen($service) > 100) {
        $service = mb_substr($service, 0, 100);
    }

    // Budget: max 50 characters
    if (!empty($budget) && mb_strlen($budget) > 50) {
        $budget = mb_substr($budget, 0, 50);
    }

    // Message: required, min 5 characters, max 5000 characters
    if (empty($message) || mb_strlen($message) < 5) {
        $message = "Submitted via Website Tailors Interactive Project Questionnaire.";
    }
    if (mb_strlen($message) > 5000) {
        return ['success' => false, 'error' => 'Project details message cannot exceed 5,000 characters.', 'message' => ''];
    }

    // 7. Store inside `leads` table using PDO prepared statements
    $currentDateTime = date('Y-m-d H:i:s');
    $leadRecord = [
        'name'               => $name,
        'email'              => $email,
        'phone'              => !empty($phone) ? $phone : null,
        'company'            => !empty($company) ? $company : null,
        'service'            => !empty($service) ? $service : 'Website Development',
        'service_interested' => !empty($service) ? $service : 'Website Development',
        'budget'             => !empty($budget) ? $budget : null,
        'message'            => $message,
        'source'             => $source,
        'status'             => 'New',
        'call_status'        => 'Not Called',
        'last_called_at'     => null,
        'next_followup_at'   => null,
        'ip_address'         => $ipAddress,
        'user_agent'         => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        'created_at'         => $currentDateTime,
        'updated_at'         => $currentDateTime
    ];

    try {
        if ($db->isConnected()) {
            $newLeadId = $db->insert('leads', $leadRecord);
            $leadRecord['id'] = $newLeadId;
        } else {
            // If DB is temporarily offline, safely record to error log so inquiry is not lost
            error_log(sprintf(
                "[Website Tailors Lead (Offline DB)] Name: %s | Email: %s | Company: %s | Service: %s | Msg: %s",
                $name, $email, $company, $service, $message
            ));
        }

        // 8. Dispatch Email Notification safely
        if (function_exists('send_lead_notification')) {
            try {
                send_lead_notification($leadRecord);
            } catch (\Throwable $mailEx) {
                error_log("Lead email notification notice: " . $mailEx->getMessage());
            }
        }

        // Set last submission timestamp to prevent rapid double-clicks
        $_SESSION['last_lead_submit_time'] = $nowTime;

        return [
            'success' => true,
            'error'   => null,
            'message' => "Thanks! Your enquiry has been received. We'll get back to you shortly."
        ];
    } catch (\Throwable $e) {
        error_log("process_lead_inquiry error: " . $e->getMessage());
        return [
            'success' => false,
            'error'   => 'A temporary server error occurred. Please try again or reach out to us directly via email.',
            'message' => ''
        ];
    }
}

/**
 * Generate a clean URL-friendly slug
 *
 * @param string $text
 * @return string
 */
function slugify(string $text): string
{
    // Replace non letter or digits by hyphen
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // Transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // Trim
    $text = trim($text, '-');
    // Remove duplicate hyphens
    $text = preg_replace('~-+~', '-', $text);
    // Lowercase
    $text = strtolower($text);

    return empty($text) ? 'item-' . time() : $text;
}

/**
 * Safely format timestamps
 *
 * @param string|null $date
 * @param string $format
 * @return string
 */
function format_date(?string $date, string $format = 'M j, Y'): string
{
    if (empty($date)) {
        return '—';
    }
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (\Exception) {
        return $date;
    }
}

/**
 * Normalize phone number for Exotel Click-to-Call API
 * Handles 10-digit Indian numbers, +91 prefixes, and international numbers
 *
 * @param string $phone
 * @return string
 */
function normalize_phone_number(string $phone): string
{
    $clean = preg_replace('/[^\d+]/', '', $phone);
    if (empty($clean)) {
        return '';
    }

    // If starts with + (international format), return clean version
    if (str_starts_with($clean, '+')) {
        return $clean;
    }

    // 10-digit Indian number: prepend 0 for Exotel standard (e.g. 9380552034 -> 09380552034)
    if (strlen($clean) === 10 && preg_match('/^[6-9]\d{9}$/', $clean)) {
        return '0' . $clean;
    }

    // 11-digit starting with 0: valid Indian number
    if (strlen($clean) === 11 && str_starts_with($clean, '0')) {
        return $clean;
    }

    // 12-digit starting with 91: convert to 0 format
    if (strlen($clean) === 12 && str_starts_with($clean, '91')) {
        return '0' . substr($clean, 2);
    }

    return $clean;
}

/**
 * Initiate Exotel Click-to-Call (Two-leg call: Agent -> Client)
 *
 * @param string $agentPhone
 * @param string $clientPhone
 * @param string|null $callbackUrl
 * @return array{success: bool, call_sid: ?string, status: string, error: ?string, message: string}
 */
function exotel_click_to_call(string $agentPhone, string $clientPhone, ?string $callbackUrl = null): array
{
    $accountSid = defined('EXOTEL_ACCOUNT_SID') ? EXOTEL_ACCOUNT_SID : (getenv('EXOTEL_ACCOUNT_SID') ?: '');
    $apiKey     = defined('EXOTEL_API_KEY') ? EXOTEL_API_KEY : (getenv('EXOTEL_API_KEY') ?: '');
    $apiToken   = defined('EXOTEL_API_TOKEN') ? EXOTEL_API_TOKEN : (getenv('EXOTEL_API_TOKEN') ?: '');
    $subdomain  = defined('EXOTEL_SUBDOMAIN') ? EXOTEL_SUBDOMAIN : (getenv('EXOTEL_SUBDOMAIN') ?: 'api.exotel.com');
    $callerId   = defined('EXOTEL_VIRTUAL_NUMBER') ? EXOTEL_VIRTUAL_NUMBER : (getenv('EXOTEL_VIRTUAL_NUMBER') ?: '08045678900');

    $agentNorm = normalize_phone_number($agentPhone);
    $clientNorm = normalize_phone_number($clientPhone);

    if (empty($agentNorm)) {
        return ['success' => false, 'call_sid' => null, 'status' => 'failed', 'error' => 'Invalid agent phone configuration.', 'message' => ''];
    }
    if (empty($clientNorm)) {
        return ['success' => false, 'call_sid' => null, 'status' => 'failed', 'error' => 'Invalid client phone number.', 'message' => ''];
    }

    // Mock/Development mode if API key is not configured or in local testing
    if (empty($apiKey) || empty($apiToken) || $apiKey === 'your_exotel_api_key') {
        $mockSid = 'EXO-MOCK-' . time() . '-' . rand(1000, 9999);
        error_log("[Exotel Telephony Notice] Mock Click-to-Call initiated. Agent: {$agentNorm} -> Client: {$clientNorm} | SID: {$mockSid}");
        return [
            'success'  => true,
            'call_sid' => $mockSid,
            'status'   => 'initiated',
            'error'    => null,
            'message'  => 'Calling your phone...'
        ];
    }

    // Official Exotel Connect API endpoint:
    // POST https://<api_key>:<api_token>@<subdomain>/v1/Accounts/<account_sid>/Calls/connect.json
    $endpoint = sprintf('https://%s/v1/Accounts/%s/Calls/connect.json', $subdomain, $accountSid);

    $postData = [
        'From'     => $agentNorm,
        'To'       => $clientNorm,
        'CallerId' => $callerId,
        'CallType' => 'trans',
    ];

    if (!empty($callbackUrl)) {
        $postData['StatusCallback'] = $callbackUrl;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ':' . $apiToken);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log("[Exotel Error] cURL error: " . $curlError);
        return [
            'success'  => false,
            'call_sid' => null,
            'status'   => 'failed',
            'error'    => 'Unable to reach telephony service. Please try again.',
            'message'  => ''
        ];
    }

    $json = json_decode($response, true);
    $callObj = $json['Call'] ?? $json['RestException'] ?? [];
    $callSid = $callObj['Sid'] ?? null;

    if ($httpCode >= 200 && $httpCode < 300 && !empty($callSid)) {
        return [
            'success'  => true,
            'call_sid' => $callSid,
            'status'   => strtolower((string)($callObj['Status'] ?? 'initiated')),
            'error'    => null,
            'message'  => 'Calling your phone...'
        ];
    }

    $errMsg = $callObj['Message'] ?? ($json['RestException']['Message'] ?? 'Call request failed.');
    error_log("[Exotel Error] HTTP {$httpCode}: {$errMsg}");

    return [
        'success'  => false,
        'call_sid' => null,
        'status'   => 'failed',
        'error'    => 'Unable to start the call. Please try again.',
        'message'  => ''
    ];
}

/**
 * Render hyper-realistic HTML/CSS website or product UI preview inside browser frame
 *
 * @param array<string, mixed> $project
 * @return string
 */
function render_project_preview_html(array $project): string
{
    $slug   = strtolower((string)($project['slug'] ?? ''));
    $title  = (string)($project['title'] ?? '');
    $imgUrl = get_image_url($project['image'] ?? $project['image_path'] ?? null, 'project');

    $urlText = 'https://' . slugify($title) . '.com';
    if (strpos($slug, 'apex') !== false || strpos(strtolower($title), 'apex') !== false) {
        $urlText = 'https://apex-logistics.com/portal/dispatch';
    } elseif (strpos($slug, 'kroma') !== false || strpos(strtolower($title), 'kroma') !== false) {
        $urlText = 'https://kroma.design';
    } elseif (strpos($slug, 'veloce') !== false || strpos(strtolower($title), 'veloce') !== false) {
        $urlText = 'https://veloce-store.com/shop';
    } elseif (strpos($slug, 'omniflow') !== false || strpos(strtolower($title), 'omniflow') !== false) {
        $urlText = 'https://app.omniflow.io/workflows/crm-sync';
    }

    $altText = e($title) . ' website preview';

    return '
    <div class="browser-address">
      <span class="browser-lock">🔒</span>
      <span class="browser-url-text">' . e($urlText) . '</span>
    </div>
    <div class="browser-viewport">
      <img src="' . e($imgUrl) . '" alt="' . $altText . '" class="browser-preview-img" loading="lazy" />
    </div>';
}

