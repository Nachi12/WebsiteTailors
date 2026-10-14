import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Trend } from 'k6/metrics';
import { BASE_URL, SCENARIO_STAGES, THRESHOLDS, HEADERS } from '../config.js';

const dynamicTrend = new Trend('dynamic_page_duration', true);
const staticTrend = new Trend('static_asset_duration', true);

const scenarioName = __ENV.SCENARIO || 'baseline';

export const options = {
  stages: SCENARIO_STAGES[scenarioName] || SCENARIO_STAGES.baseline,
  thresholds: THRESHOLDS,
};

const PUBLIC_PAGES = [
  { url: '/', name: 'Homepage' },
  { url: '/services.php', name: 'Services' },
  { url: '/work.php', name: 'Work' },
  { url: '/about.php', name: 'About' },
  { url: '/contact.php', name: 'Contact' },
];

const STATIC_ASSETS = [
  '/assets/css/style.css',
  '/assets/js/main.js',
  '/assets/images/favicon.svg',
  '/assets/images/projects/apex-logistics.jpg',
  '/assets/images/illustrations/card-01-new-online.svg',
];

export default function () {
  // Realistic public visitor browsing through website pages (100% read-only, ZERO synthetic lead insertions)
  const page = PUBLIC_PAGES[Math.floor(Math.random() * PUBLIC_PAGES.length)];
  group(`Public Page: ${page.name}`, () => {
    const res = http.get(`${BASE_URL}${page.url}`, {
      headers: HEADERS.html,
      tags: { type: 'dynamic', page: page.name },
    });

    dynamicTrend.add(res.timings.duration, { page: page.name });

    check(res, {
      [`${page.name} status is 200`]: (r) => r.status === 200,
    });
  });

  // Browser fetches linked static asset
  const asset = STATIC_ASSETS[Math.floor(Math.random() * STATIC_ASSETS.length)];
  group('Static Asset', () => {
    const assetRes = http.get(`${BASE_URL}${asset}`, {
      tags: { type: 'static', asset: asset },
    });

    staticTrend.add(assetRes.timings.duration);

    check(assetRes, {
      'asset status 200': (r) => r.status === 200,
    });
  });

  // Realistic human think time (500ms to 2000ms)
  sleep(0.5 + Math.random() * 1.5);
}
