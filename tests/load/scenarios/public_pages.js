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

const PAGES = [
  { url: '/', name: 'Homepage' },
  { url: '/services.php', name: 'Services' },
  { url: '/work.php', name: 'Work' },
  { url: '/about.php', name: 'About' },
  { url: '/contact.php', name: 'Contact' },
];

const ASSETS = [
  '/assets/css/style.css',
  '/assets/js/main.js',
  '/assets/images/favicon.svg',
  '/assets/images/projects/apex-logistics.jpg',
];

export default function () {
  // 1. Browse public pages with realistic user behavior
  const page = PAGES[Math.floor(Math.random() * PAGES.length)];
  group(`Public Page: ${page.name}`, () => {
    const res = http.get(`${BASE_URL}${page.url}`, {
      headers: HEADERS.html,
      tags: { type: 'dynamic', page: page.name },
    });

    dynamicTrend.add(res.timings.duration, { page: page.name });

    check(res, {
      [`${page.name} status is 200`]: (r) => r.status === 200,
      [`${page.name} contains html body`]: (r) => r.body && r.body.length > 500,
    });
  });

  // 2. Browser fetches linked static assets
  const asset = ASSETS[Math.floor(Math.random() * ASSETS.length)];
  group('Static Asset Request', () => {
    const res = http.get(`${BASE_URL}${asset}`, {
      tags: { type: 'static', asset: asset },
    });

    staticTrend.add(res.timings.duration, { asset: asset });

    check(res, {
      'asset status is 200': (r) => r.status === 200,
    });
  });

  // Realistic human think time (500ms to 2000ms)
  sleep(0.5 + Math.random() * 1.5);
}
