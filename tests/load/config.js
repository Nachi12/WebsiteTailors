// WebsiteTailors Load Test Suite - Configuration & Shared Helpers
export const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8088';
export const ALLOW_PRODUCTION = __ENV.ALLOW_PRODUCTION === 'true';

// Safety Guard: Abort if targeting production without explicit confirmation
if (BASE_URL.includes('websitetailors.com') && !ALLOW_PRODUCTION) {
  throw new Error(
    'CRITICAL SAFETY GUARD: Targeting production domain without explicit ALLOW_PRODUCTION=true is strictly forbidden.'
  );
}

export const SCENARIO_STAGES = {
  smoke: [
    { duration: '10s', target: 1 },
  ],
  baseline: [
    { duration: '30s', target: 5 },
    { duration: '1m', target: 10 },
    { duration: '30s', target: 0 },
  ],
  normal: [
    { duration: '1m', target: 25 },
    { duration: '2m', target: 50 },
    { duration: '30s', target: 0 },
  ],
  growth: [
    { duration: '1m', target: 50 },
    { duration: '2m', target: 100 },
    { duration: '30s', target: 0 },
  ],
  stress: [
    { duration: '1m', target: 100 },
    { duration: '2m', target: 200 },
    { duration: '1m', target: 300 },
    { duration: '30s', target: 0 },
  ],
  spike: [
    { duration: '15s', target: 10 },
    { duration: '30s', target: 150 },
    { duration: '15s', target: 10 },
    { duration: '15s', target: 0 },
  ],
  soak: [
    { duration: '1m', target: 50 },
    { duration: '5m', target: 50 },
    { duration: '30s', target: 0 },
  ],
};

export const THRESHOLDS = {
  // Public dynamic pages: p95 under 500ms
  'http_req_duration{type:dynamic}': ['p(95)<500'],
  // Enquiry submission: p95 under 1000ms
  'http_req_duration{type:enquiry}': ['p(95)<1000'],
  // HTTP failures below 0.1% under sustainable load
  http_req_failed: ['rate<0.01'],
};

export const HEADERS = {
  json: {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  html: {
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 WebsiteTailors-LoadTest/1.0',
  },
};
