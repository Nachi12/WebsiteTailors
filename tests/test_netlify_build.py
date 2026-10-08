#!/usr/bin/env python3
"""
Website Tailors — Netlify Build Validation & Security Audit Suite
Validates the static output in /netlify/ against all production and security requirements.
"""

import os
import re
import sys

NETLIFY_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', 'netlify'))

passed = 0
failed = 0

def assert_check(condition, test_name, details=""):
    global passed, failed
    if condition:
        passed += 1
        print(f"  [PASS] {test_name}")
    else:
        failed += 1
        print(f"  [FAIL] {test_name}")
        if details:
            print(f"         Details: {details}")

print("====================================================")
print("Website Tailors: Netlify Build Validation & Security Suite")
print("====================================================\n")

# 1. Check folder structure and critical files
assert_check(os.path.isdir(NETLIFY_DIR), "Netlify deployment directory exists")
index_html = os.path.join(NETLIFY_DIR, "index.html")
netlify_toml = os.path.join(NETLIFY_DIR, "netlify.toml")
css_file = os.path.join(NETLIFY_DIR, "assets", "css", "style.css")
js_main = os.path.join(NETLIFY_DIR, "assets", "js", "main.js")
js_config = os.path.join(NETLIFY_DIR, "assets", "js", "config.js")
favicon_svg = os.path.join(NETLIFY_DIR, "assets", "images", "favicon.svg")

assert_check(os.path.isfile(index_html), "index.html exists in netlify/")
assert_check(os.path.isfile(netlify_toml), "netlify.toml exists in netlify/")
assert_check(os.path.isfile(css_file), "assets/css/style.css exists in netlify/")
assert_check(os.path.isfile(js_main), "assets/js/main.js exists in netlify/")
assert_check(os.path.isfile(js_config), "assets/js/config.js exists in netlify/")
assert_check(os.path.isfile(favicon_svg), "assets/images/favicon.svg exists in netlify/")

with open(index_html, "r", encoding="utf-8") as f:
    html_content = f.read()

# 2. Content & Section presence
assert_check("<title>" in html_content and "Website Tailors" in html_content, "Page Title is present and accurate")
assert_check('class="hero"' in html_content, "Hero section present")
assert_check('id="services"' in html_content, "Services section present")
assert_check('id="about"' in html_content, "About/Approach section present")
assert_check('id="work"' in html_content, "Work/Projects section present")
assert_check('class="principles"' in html_content, "Principles section present")
assert_check('id="process"' in html_content, "Process section present")
assert_check('id="testimonials"' in html_content, "Testimonials section present")
assert_check('id="contact"' in html_content, "Contact section present")
assert_check('id="contactForm"' in html_content, "Contact form present")
assert_check('id="floatingSystem"' in html_content or 'id="heroOrbitalWrapper"' in html_content or 'id="heroVisualStage"' in html_content, "Hero visual composition present")
assert_check('id="loader"' in html_content, "Preloader present")
assert_check('class="cursor"' in html_content, "Custom cursor present")

# 3. Security checks: No database credentials or server secrets
sensitive_patterns = [
    r'DB_PASSWORD', r'DB_USER', r'DB_HOST', r'DB_NAME',
    r'SECRET_KEY', r'WebsiteTailors_user', r'WebsiteTailors_db', r'\.env\b'
]
for root, dirs, files in os.walk(NETLIFY_DIR):
    for fname in files:
        fpath = os.path.join(root, fname)
        rel_path = os.path.relpath(fpath, NETLIFY_DIR)
        with open(fpath, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()
        for pat in sensitive_patterns:
            m = re.search(pat, content, re.IGNORECASE)
            assert_check(not m, f"No sensitive pattern '{pat}' in {rel_path}", f"Found match: {m.group(0) if m else ''}")

# 4. Check for forbidden local paths (/Users/..., file://...)
for root, dirs, files in os.walk(NETLIFY_DIR):
    for fname in files:
        fpath = os.path.join(root, fname)
        rel_path = os.path.relpath(fpath, NETLIFY_DIR)
        with open(fpath, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()
        assert_check("/Users/" not in content, f"No local /Users/ path in {rel_path}")
        assert_check("file://" not in content, f"No file:// protocol in {rel_path}")

# 5. Check for .php dependencies in index.html (except in comments or API doc)
php_in_html = re.findall(r'(href|src|action)=["\'][^"\']*\.php[^"\']*["\']', html_content)
assert_check(len(php_in_html) == 0, "No HTML attributes point directly to .php files", f"Matches: {php_in_html}")

# 6. Check navigation anchors
for anchor in ["#home", "#services", "#work", "#process", "#about", "#contact"]:
    assert_check(f'href="{anchor}"' in html_content, f"Navigation anchor '{anchor}' exists in index.html")

print("\n====================================================")
print(f"Audit Results: {passed} Passed, {failed} Failed")
print("====================================================")

if failed > 0:
    sys.exit(1)
