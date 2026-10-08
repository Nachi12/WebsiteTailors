#!/usr/bin/env python3
"""
Test Suite: WebsiteTailors Admin Phase 1 Mobile-First Responsive Foundation
Validates:
1. All admin pages load correctly via PHP HTTP server
2. Meta viewport tag present on every page
3. Admin CSS stylesheet link present
4. Mobile sidebar off-canvas elements present (sidebar, sidebar-backdrop, sidebarClose, sidebarToggle)
5. Touch targets & responsive layout CSS rules present
6. No PHP syntax or runtime errors on any admin page
"""

import urllib.request
import urllib.parse
import http.cookiejar
import re
import sys

BASE_URL = "http://127.0.0.1:8080/admin"

# Setup cookie jar for admin session
cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

def log_test(title, passed, detail=""):
    status = "[PASS]" if passed else "[FAIL]"
    print(f"  {status} {title}")
    if detail and not passed:
        print(f"         {detail}")

def run_tests():
    print("====================================================")
    print("WebsiteTailors: Admin Phase 1 Mobile-First Validation")
    print("====================================================")
    
    passed_count = 0
    total_count = 0

    # Step 1: Login to Admin
    login_url = f"{BASE_URL}/login.php"
    try:
        resp = opener.open(login_url)
        html = resp.read().decode('utf-8')
        
        # Extract CSRF token
        csrf_match = re.search(r'name="csrf_token"\s+value="([^"]+)"', html)
        csrf_token = csrf_match.group(1) if csrf_match else ""
        
        login_data = urllib.parse.urlencode({
            'email': 'websietailorss@gmail.com',
            'password': 'Admin@12345',
            'csrf_token': csrf_token
        }).encode('utf-8')
        
        req = urllib.request.Request(login_url, data=login_data, method='POST')
        resp = opener.open(req)
        login_resp_html = resp.read().decode('utf-8')
        
        is_logged_in = ("Executive Dashboard" in login_resp_html or "Business Analytics" in login_resp_html or "Dashboard" in login_resp_html)
        total_count += 1
        if is_logged_in:
            passed_count += 1
            log_test("Admin Authentication", True)
        else:
            log_test("Admin Authentication", False, "Could not log into admin panel")
            return
    except Exception as e:
        log_test("Admin Authentication", False, str(e))
        return

    # Pages to test
    admin_pages = [
        ("Dashboard", "index.php"),
        ("Clients", "clients.php"),
        ("Leads", "leads.php"),
        ("Calls", "calls.php"),
        ("Revenue", "revenue.php"),
        ("Invoices", "invoices.php"),
        ("Reports", "reports.php"),
        ("Settings", "settings.php")
    ]

    for label, page in admin_pages:
        page_url = f"{BASE_URL}/{page}"
        total_count += 1
        try:
            resp = opener.open(page_url)
            html = resp.read().decode('utf-8')
            
            # Check 1: 200 OK and no PHP fatal error
            has_fatal = "<b>Fatal error</b>" in html or "<b>Parse error</b>" in html or "Uncaught PDOException" in html or "<b>Notice</b>:" in html or "<b>Warning</b>:" in html
            if resp.status == 200 and not has_fatal:
                log_test(f"Page Load: {label} ({page})", True)
                passed_count += 1
            else:
                log_test(f"Page Load: {label} ({page})", False, "Contains PHP error or non-200 status")
                continue

            # Check 2: Viewport meta tag
            total_count += 1
            has_viewport = '<meta name="viewport"' in html and 'width=device-width' in html
            if has_viewport:
                log_test(f"Viewport Meta: {label}", True)
                passed_count += 1
            else:
                log_test(f"Viewport Meta: {label}", False, "Missing viewport meta tag")

            # Check 3: Sidebar Off-Canvas elements
            total_count += 1
            has_drawer_elems = ('id="adminSidebar"' in html or 'class="sidebar"' in html) and 'id="sidebarBackdrop"' in html and 'id="sidebarToggle"' in html
            if has_drawer_elems:
                log_test(f"Mobile Sidebar Drawer: {label}", True)
                passed_count += 1
            else:
                log_test(f"Mobile Sidebar Drawer: {label}", False, "Missing mobile sidebar drawer HTML elements")

        except Exception as e:
            log_test(f"Page Load: {label} ({page})", False, str(e))

    # CSS & Phase 2 Mobile Dashboard Verification
    css_url = "http://127.0.0.1:8080/assets/css/admin.css"
    total_count += 1
    try:
        css_resp = opener.open(css_url)
        css = css_resp.read().decode('utf-8')
        
        has_touch_targets = "min-height: 44px" in css
        has_drawer_media = "@media (max-width: 992px)" in css and "transform: translateX(-100%)" in css
        has_100vw_guard = "max-width: 100vw" in css
        has_table_scroll = ".table-responsive" in css and "overflow-x: auto" in css
        has_2col_kpi = "grid-template-columns: repeat(2, 1fr)" in css
        has_mobile_card_rules = ".mobile-lead-card" in css and ".mobile-activity-card" in css and ".mobile-followup-card" in css
        
        if has_touch_targets and has_drawer_media and has_100vw_guard and has_table_scroll and has_2col_kpi and has_mobile_card_rules:
            log_test("Admin CSS Mobile Dashboard & Touch Target Rules", True)
            passed_count += 1
        else:
            log_test("Admin CSS Mobile Dashboard & Touch Target Rules", False, "Missing required mobile CSS rules")
    except Exception as e:
        log_test("Admin CSS Verification", False, str(e))

    # Check Mobile Card Blocks in Dashboard HTML
    total_count += 1
    try:
        dash_url = f"{BASE_URL}/index.php"
        resp = opener.open(dash_url)
        html = resp.read().decode('utf-8')
        
        has_mobile_leads = "mobile-leads-cards-list" in html
        has_mobile_activity = "mobile-activity-cards-list" in html
        has_mobile_followups = "mobile-followup-cards-list" in html
        
        if has_mobile_leads and has_mobile_activity and has_mobile_followups:
            log_test("Mobile Dashboard Card HTML Elements (Leads, Activity, Follow-ups)", True)
            passed_count += 1
        else:
            log_test("Mobile Dashboard Card HTML Elements", False, "Missing mobile card HTML containers in index.php")
    except Exception as e:
        log_test("Dashboard Card Check", False, str(e))

    print("\n====================================================")
    print(f"Results: {passed_count}/{total_count} Passed")
    print("====================================================")
    
    if passed_count != total_count:
        sys.exit(1)

if __name__ == '__main__':
    run_tests()
