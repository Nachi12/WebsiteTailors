import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Trend, Counter } from 'k6/metrics';
import { BASE_URL, SCENARIO_STAGES, THRESHOLDS, HEADERS } from '../config.js';

const adminTrend = new Trend('admin_dashboard_duration', true);
const authBlockedCounter = new Counter('unauthorized_access_blocked');

const scenarioName = __ENV.SCENARIO || 'baseline';

export const options = {
  stages: SCENARIO_STAGES[scenarioName] || SCENARIO_STAGES.baseline,
  thresholds: THRESHOLDS,
};

export default function () {
  // Step 1: Security check - Verify unauthenticated access to admin dashboard is redirected
  group('1. Verify Unauthorized Access Defense', () => {
    // Send request without session cookie to confirm protection
    const unauthRes = http.get(`${BASE_URL}/admin/index.php`, {
      redirects: 0,
      tags: { type: 'admin', action: 'unauth_guard' },
    });

    const isRedirected = check(unauthRes, {
      'unauthenticated request redirected (302)': (r) => r.status === 302,
      'redirect location points to login': (r) => {
        const loc = r.headers['Location'] || '';
        return loc.includes('/admin/login.php');
      },
    });

    if (isRedirected) {
      authBlockedCounter.add(1);
    }
  });

  // Step 2: Open Login Page & extract CSRF token
  let loginCsrf = '';
  group('2. Load Admin Login Page', () => {
    const loginPageRes = http.get(`${BASE_URL}/admin/login.php`, {
      headers: HEADERS.html,
      tags: { type: 'admin', page: 'login_get' },
    });

    check(loginPageRes, {
      'login page loads with 200': (r) => r.status === 200,
    });

    const doc = loginPageRes.html();
    loginCsrf = doc.find('input[name="csrf_token"]').attr('value') || '';

    check(loginCsrf, {
      'login csrf token present': (t) => t.length === 64,
    });
  });

  if (!loginCsrf) {
    sleep(1);
    return;
  }

  // Step 3: Authenticate with dedicated test admin credentials
  group('3. Admin Authentication POST', () => {
    const payload = {
      csrf_token: loginCsrf,
      email: 'loadtest@websitetailors.local',
      password: 'LoadTest@12345',
    };

    const loginRes = http.post(`${BASE_URL}/admin/login.php`, payload, {
      redirects: 0,
      headers: HEADERS.html,
      tags: { type: 'admin', action: 'login_post' },
    });

    check(loginRes, {
      'login POST returns 302 redirect': (r) => r.status === 302,
      'redirects to admin dashboard': (r) => {
        const loc = r.headers['Location'] || '';
        return loc.includes('/admin/index.php');
      },
    });
  });

  // Step 4: Access Authenticated Dashboard & Read-Only Modules
  group('4. Authenticated Admin Navigation', () => {
    const dashRes = http.get(`${BASE_URL}/admin/index.php`, {
      headers: HEADERS.html,
      tags: { type: 'dynamic', page: 'admin_dashboard' },
    });

    adminTrend.add(dashRes.timings.duration);

    check(dashRes, {
      'dashboard status 200': (r) => r.status === 200,
      'dashboard renders CRM overview': (r) => r.body && r.body.includes('Website Tailors'),
    });

    sleep(0.5);

    // View Leads module
    const leadsRes = http.get(`${BASE_URL}/admin/leads.php`, {
      headers: HEADERS.html,
      tags: { type: 'dynamic', page: 'admin_leads' },
    });

    check(leadsRes, {
      'leads module status 200': (r) => r.status === 200,
    });

    sleep(0.5);

    // View Site Settings module
    const settingsRes = http.get(`${BASE_URL}/admin/settings.php`, {
      headers: HEADERS.html,
      tags: { type: 'dynamic', page: 'admin_settings' },
    });

    check(settingsRes, {
      'settings module status 200': (r) => r.status === 200,
    });
  });

  // Step 5: Clean Logout
  group('5. Admin Logout', () => {
    const logoutRes = http.get(`${BASE_URL}/admin/logout.php`, {
      redirects: 0,
      tags: { type: 'admin', action: 'logout' },
    });

    check(logoutRes, {
      'logout returns 302 redirect': (r) => r.status === 302,
    });
  });

  sleep(1.0);
}
