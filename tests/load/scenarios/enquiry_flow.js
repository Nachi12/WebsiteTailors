import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Counter, Trend } from 'k6/metrics';
import { BASE_URL, SCENARIO_STAGES, THRESHOLDS, HEADERS } from '../config.js';

const enquiryTrend = new Trend('enquiry_submit_duration', true);
const validEnquiryCounter = new Counter('valid_enquiries_submitted');
const rejectedEnquiryCounter = new Counter('invalid_enquiries_rejected');

const scenarioName = __ENV.SCENARIO || 'baseline';

export const options = {
  stages: SCENARIO_STAGES[scenarioName] || SCENARIO_STAGES.baseline,
  thresholds: THRESHOLDS,
};

export default function () {
  let csrfToken = '';

  // Step 1: Visitor lands on contact page & receives session cookie + CSRF token
  group('1. Load Contact Page', () => {
    const pageRes = http.get(`${BASE_URL}/contact.php`, {
      headers: HEADERS.html,
      tags: { type: 'dynamic', page: 'contact_get' },
    });

    check(pageRes, {
      'contact page status 200': (r) => r.status === 200,
      'has session cookie': (r) => r.headers['Set-Cookie'] !== undefined || r.cookies['WebsiteTailors_sess'] !== undefined,
    });

    const doc = pageRes.html();
    csrfToken = doc.find('input[name="csrf_token"]').attr('value') || '';

    check(csrfToken, {
      'csrf token extracted successfully': (t) => t.length === 64,
    });
  });

  if (!csrfToken) {
    sleep(1);
    return;
  }

  // Realistic form completion time (1-2s)
  sleep(1.0 + Math.random() * 1.0);

  // 80% Valid submissions, 15% Invalid validation, 5% Bad CSRF
  const dice = Math.random();

  if (dice < 0.80) {
    // Valid submission with unique synthetic identifier
    group('2. Submit Valid Lead Enquiry', () => {
      const syntheticId = `syn_${__VU}_${__ITER}_${Date.now()}`;
      const payload = {
        csrf_token: csrfToken,
        name: `LoadTest Visitor ${__VU}`,
        email: `${syntheticId}@loadtest.local`,
        phone: '+91 9380552034',
        company: 'LoadTest Corp Ltd',
        service: 'Website Design',
        budget: '₹10,000 - ₹25,000',
        message: `Synthetic inquiry test payload [${syntheticId}] for performance assessment.`,
        website_url: '', // Honeypot must be empty
      };

      const res = http.post(`${BASE_URL}/api/contact.php`, payload, {
        headers: HEADERS.json,
        tags: { type: 'enquiry', action: 'valid_submit' },
      });

      enquiryTrend.add(res.timings.duration);

      const is200 = check(res, {
        'enquiry response status is 200': (r) => r.status === 200,
        'enquiry returns success json': (r) => {
          try {
            const body = JSON.parse(r.body);
            return body.success === true;
          } catch (e) {
            return false;
          }
        },
      });

      if (is200) {
        validEnquiryCounter.add(1);
      }
    });
  } else if (dice < 0.95) {
    // Invalid submission: missing required name
    group('3. Submit Invalid Enquiry (Validation Guard)', () => {
      const payload = {
        csrf_token: csrfToken,
        name: '', // Empty name triggers validation failure
        email: `invalid_${Date.now()}@loadtest.local`,
        phone: '12345',
        message: 'This should be rejected by server validation.',
        website_url: '',
      };

      const res = http.post(`${BASE_URL}/api/contact.php`, payload, {
        headers: HEADERS.json,
        tags: { type: 'enquiry', action: 'invalid_validation' },
      });

      const is422 = check(res, {
        'invalid submission correctly rejected with 422': (r) => r.status === 422,
        'invalid response indicates validation error': (r) => {
          try {
            const body = JSON.parse(r.body);
            return body.success === false && body.error.length > 0;
          } catch (e) {
            return false;
          }
        },
      });

      if (is422) {
        rejectedEnquiryCounter.add(1);
      }
    });
  } else {
    // Bad CSRF Token submission
    group('4. Submit Bad CSRF Enquiry (Security Guard)', () => {
      const payload = {
        csrf_token: 'tampered_or_expired_token_000000000000000000000000000000000000',
        name: 'Attacker Attempt',
        email: `badcsrf_${Date.now()}@loadtest.local`,
        message: 'This should be blocked by CSRF verification.',
        website_url: '',
      };

      const res = http.post(`${BASE_URL}/api/contact.php`, payload, {
        headers: HEADERS.json,
        tags: { type: 'enquiry', action: 'bad_csrf' },
      });

      check(res, {
        'tampered csrf rejected with 422 or 403': (r) => r.status === 422 || r.status === 403,
      });
      rejectedEnquiryCounter.add(1);
    });
  }

  sleep(1.0);
}
