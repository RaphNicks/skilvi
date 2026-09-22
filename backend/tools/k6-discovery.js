// k6 script for discovery endpoints. Run: k6 run tools/k6-discovery.js
// Target: 10× a small NG launch (≈50 rps) against jobs/search/landing.
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  vus: 20,
  duration: '30s',
  thresholds: {
    http_req_duration: ['p(95)<300'],
    http_req_failed: ['rate<0.01'],
  },
};

const BASE = __ENV.SMOKE_BASE || 'http://127.0.0.1:8080';

export default function () {
  const paths = ['/api/landing', '/api/jobs', '/api/categories', '/api/health'];
  const p = paths[Math.floor(Math.random() * paths.length)];
  const res = http.get(`${BASE}${p}`);
  check(res, { '200': (r) => r.status === 200 });
  sleep(0.2);
}
