// Flow wiring pass: adds new sidebar entries, footer About link, bell→notifications,
// and converts specific stub buttons into real page links. Run once.
const fs = require("fs");
const path = require("path");
const root = path.resolve(__dirname, "..");

const SHIELD = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v6c0 4.4 3 7.4 7 9 4-1.6 7-4.6 7-9V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg>';
const ZAP = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/></svg>';
const HEART = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20.3S3.8 15 3.8 9.6a4.6 4.6 0 0 1 8.2-2.9 4.6 4.6 0 0 1 8.2 2.9c0 5.4-8.2 10.7-8.2 10.7Z"/></svg>';
const GEAR = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3.2"/><path d="M19 12a7 7 0 0 0-.14-1.4l2-1.55-2-3.46-2.35.95A7 7 0 0 0 14.06 5L13.7 2.5h-3.4L9.94 5a7 7 0 0 0-1.45.84l-2.35-.95-2 3.46 2 1.55A7 7 0 0 0 6 12c0 .48.05.94.14 1.4l-2 1.55 2 3.46 2.35-.95c.44.36.92.66 1.45.84l.36 2.5h3.4l.36-2.5c.53-.18 1.01-.48 1.45-.84l2.35.95 2-3.46-2-1.55c.09-.46.14-.92.14-1.4Z"/></svg>';

const workerSide = [
  `    <a class="side-item" href="verification.html">${SHIELD}<span>Verification</span></a>`,
  `    <a class="side-item" href="promotion.html">${ZAP}<span>Promotion</span></a>`
];
const clientSide = `    <a class="side-item" href="saved.html">${HEART}<span>Saved workers</span></a>`;
const adminPromo = `    <a class="side-item" href="promotions.html">${ZAP}<span>Promotions</span></a>`;
const adminSettings = `    <a class="side-item" href="settings.html">${GEAR}<span>Settings</span></a>`;

const BELL_TOAST = '<button class="icon-btn" data-toast="3 new notifications" aria-label="Notifications">';
const BELL_LINK = '<a class="icon-btn" href="notifications.html" aria-label="Notifications">';

function apply(f, fn) {
  const p = path.join(root, f);
  if (!fs.existsSync(p)) return 0;
  const before = fs.readFileSync(p, "utf8");
  const after = fn(before);
  if (after !== before) { fs.writeFileSync(p, after); return 1; }
  return 0;
}
const has = (s, t) => s.includes(t);

const workerFiles = ["worker-dashboard.html","worker-orders.html","worker-services.html","worker-service-form.html","worker-jobs.html","worker-wallet.html","messages.html","disputes.html","account-settings.html"];
const clientFiles = ["client-dashboard.html"];
const adminFiles = fs.readdirSync(path.join(root, "admin")).filter(f => f.endsWith(".html"));
let changed = 0;

// 1) Worker sidebar: Verification + Promotion after Withdrawals
for (const f of workerFiles) {
  changed += apply(f, s => {
    if (has(s, 'href="verification.html"')) return s;
    const anchor = /(<a class="side-item" href="worker-wallet\.html\?tab=withdrawals"[^>]*>.*?<\/a>)/s;
    if (!anchor.test(s)) return s;
    return s.replace(anchor, `$1\n${workerSide.join("\n")}`);
  });
}
// 2) Client sidebar: Saved workers after My jobs
for (const f of clientFiles) {
  changed += apply(f, s => {
    if (has(s, 'href="saved.html"')) return s;
    const anchor = /(<a class="side-item" href="client-dashboard\.html\?tab=jobs"[^>]*>.*?<\/a>)/s;
    if (!anchor.test(s)) return s;
    return s.replace(anchor, `$1\n${clientSide}`);
  });
}
// 3) Admin sidebar: Promotions after Withdrawals; Settings after Audit log
for (const f of adminFiles) {
  changed += apply("admin/" + f, s => {
    let out = s;
    if (!has(out, 'href="promotions.html"')) {
      const a = /(<a class="side-item" href="withdrawals\.html"[^>]*>.*?<\/a>)/s;
      if (a.test(out)) out = out.replace(a, `$1\n${adminPromo}`);
    }
    if (!has(out, 'href="settings.html"')) {
      const b = /(<a class="side-item" href="audit-log\.html"[^>]*>.*?<\/a>)/s;
      if (b.test(out)) out = out.replace(b, `$1\n${adminSettings}`);
    }
    return out;
  });
}
// 4) Bell → notifications page (worker + client subbars)
for (const f of [...workerFiles, ...clientFiles]) {
  changed += apply(f, s => has(s, BELL_TOAST) ? s.replaceAll(BELL_TOAST, BELL_LINK) : s);
}
// 5) Footer: About Skilvi after "Become a worker"
const footerAnchor = /(<a href="worker-dashboard\.html">Become a worker<\/a>)/g;
for (const f of fs.readdirSync(root).filter(f => f.endsWith(".html"))) {
  changed += apply(f, s => has(s, "About Skilvi") ? s : s.replace(footerAnchor, "$1\n        <a href=\"about.html\">About Skilvi</a>"));
}
for (const f of adminFiles) {
  changed += apply("admin/" + f, s => has(s, "About Skilvi") ? s : s.replace(footerAnchor, "$1\n        <a href=\"../about.html\">About Skilvi</a>"));
}
// 6) Specific button → page conversions
changed += apply("worker-dashboard.html", s =>
  s.replace('<button class="btn btn-outline" data-toast="Profile opens on worker-profile.html (next batch)">View profile</button>',
            '<a class="btn btn-outline" href="worker-profile.html">View profile</a>'));
changed += apply("worker-profile.html", s =>
  s.replace('<button class="btn btn-outline" data-toast="Saved to your shortlist">☆ Save for later</button>',
            '<a class="btn btn-outline" href="saved.html">☆ Save for later</a>'));
changed += apply("client-dashboard.html", s =>
  s.replace('<a class="btn btn-outline btn-sm" data-toast="Review screen opens (next batch)">Rate</a>',
            '<a class="btn btn-outline btn-sm" href="review.html">Rate</a>'));
changed += apply("order-detail.html", s =>
  s.replace('<button class="btn btn-primary" data-toast="Review composer opens (next batch)">Submit review</button>',
            '<a class="btn btn-primary" href="review.html">Submit review</a>'));

console.log("Wiring pass done. Files changed:", changed);
