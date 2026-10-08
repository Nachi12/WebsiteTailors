#!/usr/bin/env python3
"""
WebsiteTailors — Static HTTP Server End-to-End Test Suite
Tests that http://127.0.0.1:3000 serves all pages and assets cleanly with 200 OK.
"""

import urllib.request
import urllib.parse
import json
import re
import sys

BASE_STATIC_URL = "http://127.0.0.1:3000"
API_URL = "http://127.0.0.1:8088/api/leads/create.php"

passed = 0
failed = 0

def check(condition, test_name, details=""):
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
print("WebsiteTailors: Static HTTP Server & Asset Verification")
print("====================================================\n")

# 1. Test Root Homepage
try:
    with urllib.request.urlopen(f"{BASE_STATIC_URL}/", timeout=5) as resp:
        code = resp.getcode()
        html = resp.read().decode("utf-8")
        check(code == 200, "HTTP 200 OK for /")
        check("<!DOCTYPE html>" in html, "Valid HTML5 doctype served")
        check("Website Tailors" in html, "Title rendered correctly")
        check('id="main-content"' in html, "Main content container present")
except Exception as e:
    check(False, "Failed to connect to static HTTP server", str(e))
    sys.exit(1)

# 2. Extract and verify all linked assets (CSS, JS, images, fonts)
asset_matches = re.findall(r'(?:href|src)=["\'](assets/[^"\']+)["\']', html)
unique_assets = sorted(list(set(asset_matches)))

print(f"\nVerifying {len(unique_assets)} public assets over HTTP:")
for asset in unique_assets:
    asset_url = f"{BASE_STATIC_URL}/{asset}"
    try:
        req = urllib.request.Request(asset_url)
        with urllib.request.urlopen(req, timeout=5) as resp:
            status = resp.getcode()
            content_length = resp.headers.get("Content-Length")
            check(status == 200 and int(content_length or 0) > 0, f"Asset: {asset} (HTTP {status}, {content_length} bytes)")
    except Exception as e:
        check(False, f"Asset: {asset}", str(e))

# 3. Test CSS media queries for responsive layouts (desktop, tablet, mobile)
css_url = f"{BASE_STATIC_URL}/assets/css/style.css"
with urllib.request.urlopen(css_url, timeout=5) as resp:
    css_content = resp.read().decode("utf-8")

check("@media" in css_content, "Responsive media queries defined in stylesheet")
check("900px" in css_content or "768px" in css_content, "Tablet breakpoints defined")
check("600px" in css_content or "480px" in css_content, "Mobile breakpoints defined")
check("overflow-x: hidden" in css_content or "box-sizing: border-box" in css_content, "Anti-overflow rules defined")

# 4. Test API endpoint connectivity from static frontend perspective
print("\nVerifying Lead Submission API Integration:")
test_payload = {
    "name": "Siddharth Rao",
    "email": "siddharth.rao@example.com",
    "phone": "9876501234",
    "company": "WebsiteTailors Static Browser Verification",
    "service": "Websites",
    "budget": "₹1,00,000 – ₹3,00,000",
    "message": "Testing lead form submission directly from automated static suite."
}

try:
    data = json.dumps(test_payload).encode("utf-8")
    req = urllib.request.Request(API_URL, data=data, headers={
        "Content-Type": "application/json",
        "Origin": BASE_STATIC_URL,
        "X-Requested-With": "XMLHttpRequest"
    }, method="POST")
    with urllib.request.urlopen(req, timeout=5) as resp:
        api_status = resp.getcode()
        api_body = json.loads(resp.read().decode("utf-8"))
        check(api_status == 200, f"API responds with HTTP 200 (Got {api_status})")
        check(api_body.get("success") is True, "API JSON response has success=true")
        check("lead_id" in api_body, f"Lead successfully created with ID #{api_body.get('lead_id')}")
except Exception as e:
    check(False, "API lead submission test", str(e))

print("\n====================================================")
print(f"Static Server Verification Results: {passed} Passed, {failed} Failed")
print("====================================================")

if failed > 0:
    sys.exit(1)
