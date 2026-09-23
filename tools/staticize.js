/* One-time build: bakes header/footer/sidebars + mock content as static HTML
   into every page, so pages are complete without JS. Run: node tools/staticize.js */
"use strict";
const fs = require("fs");
const path = require("path");

/* ---------- mock data ---------- */
const mockCode = fs.readFileSync(path.join(__dirname, "../assets/js/mock.js"), "utf8");
const fakeWindow = {};
new Function("window", mockCode)(fakeWindow);
const MOCK = fakeWindow.MOCK;

/* ---------- icons (same set as skilvi.js) ---------- */
const S = (p) => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + p + "</svg>";
const I = {
  mark: S('<path d="M12 3 5 6v6c0 4.4 3 7.4 7 9 4-1.6 7-4.6 7-9V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>'),
  search: S('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>'),
  bell: S('<path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6"/><path d="M10 19a2 2 0 0 0 4 0"/>'),
  menu: S('<path d="M4 7h16M4 12h16M4 17h16"/>'),
  x: S('<path d="M6 6l12 12M18 6 6 18"/>'),
  check: S('<path d="m5 12 5 5 9-9"/>'),
  checkc: S('<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 5-5"/>'),
  shield: S('<path d="M12 3 5 6v6c0 4.4 3 7.4 7 9 4-1.6 7-4.6 7-9V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>'),
  star: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2.6l2.9 5.9 6.5.95-4.7 4.6 1.1 6.5L12 17.5l-5.8 3.05 1.1-6.5-4.7-4.6 6.5-.95L12 2.6z"/></svg>',
  pin: S('<path d="M12 21s-7-5.3-7-11a7 7 0 0 1 14 0c0 5.7-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>'),
  clock: S('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'),
  chat: S('<path d="M21 12a8 8 0 0 1-8 8H4l2-3a8 8 0 1 1 15-5Z"/>'),
  bank: S('<path d="M3 9.5 12 4l9 5.5"/><path d="M5 10v8m4.5-8v8m5-8v8M19 10v8M3 20h18"/>'),
  arrow: S('<path d="M5 12h14m-6-6 6 6-6 6"/>'),
  plus: S('<path d="M12 5v14M5 12h14"/>'),
  file: S('<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/>'),
  image: S('<rect x="4" y="4" width="16" height="16" rx="2"/><circle cx="9.5" cy="9.5" r="1.5"/><path d="m5 18 5-5 4 4 2-2 3 3"/>'),
  grid: S('<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>'),
  briefcase: S('<rect x="3" y="8" width="18" height="12" rx="2"/><path d="M9 8V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/>'),
  layers: S('<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>'),
  jobs: S('<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6M9 13h6M9 17h3"/>'),
  wallet2: S('<path d="M20 7H5a2 2 0 0 1 0-4h13v4"/><path d="M20 7a1.5 1.5 0 0 1 1.5 1.5v11A1.5 1.5 0 0 1 20 21H6a2 2 0 0 1-2-2V5"/><circle cx="16.5" cy="14" r="1"/>'),
  msg: S('<path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H8l-4 4V6a1 1 0 0 1 1-1Z"/>'),
  scale: S('<path d="M12 4v16m-7 0h14"/><path d="m5 7-3 6a3.5 3.5 0 0 0 6 0L5 7Zm14 0-3 6a3.5 3.5 0 0 0 6 0l-3-6Z"/><path d="M5 7h14"/>'),
  gear: S('<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7 7 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.06-.4.1-.8.1-1.2Z"/>'),
  help: S('<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.4 2.3c-.8.3-.9 1-.9 1.7"/><path d="M12 17h.01"/>'),
  code: S('<path d="m8 8-4 4 4 4m8-8 4 4-4 4m-3-10-2 12"/>'),
  pen: S('<path d="M4 20h4L19.5 8.5a2.1 2.1 0 0 0-3-3L5 17v3Z"/><path d="m13.5 6.5 3 3"/>'),
  wrench: S('<path d="M14.7 6.3a4.5 4.5 0 0 0-6 5.6L3 17.6V21h3.4l5.7-5.7a4.5 4.5 0 0 0 5.6-6L14.5 12l-2.5-2.5 2.7-3.2Z"/>'),
  heart: S('<path d="M12 20.5S4 15 4 9.5A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 8 2.5c0 5.5-8 11-8 11Z"/>'),
  chart: S('<path d="M4 20V10m5.5 10V4M15 20v-7m5.5 7V7"/>'),
  flag: S('<path d="M5 21V4"/><path d="M5 4h12l-2.5 4L17 12H5"/>'),
  card: S('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>'),
  mail: S('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>'),
  users: S('<circle cx="9" cy="8.5" r="3.5"/><path d="M3 19.5a6 6 0 0 1 12 0"/><path d="M16 5.5a3.5 3.5 0 0 1 0 6.6M17.5 14.5a6 6 0 0 1 3.5 5"/>'),
  truck: S('<rect x="2" y="6" width="12" height="10" rx="1"/><path d="M14 10h4l3 3v3h-3"/><circle cx="7" cy="18.5" r="1.8"/><circle cx="17" cy="18.5" r="1.8"/>'),
  zap: S('<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/>'),
  phone: S('<path d="M6 3h3l1.5 5L8 9.5a12 12 0 0 0 6.5 6.5l1.5-2.5 5 1.5v3a2 2 0 0 1-2 2A16 16 0 0 1 4 5a2 2 0 0 1 2-2Z"/>')
};
const ngn = (n) => "₦" + Number(n).toLocaleString("en-NG");
const stars = (r) => {
  let o = '<span class="stars">';
  for (let i = 1; i <= 5; i++) o += '<span class="' + (i <= Math.round(r) ? "on" : "off") + '">' + I.star + "</span>";
  return o + "</span>";
};
const vbadge = '<span class="badge-verified">' + I.shield + "Verified</span>";

/* ---------- shared row renderers (mirror the page scripts) ---------- */
function workerRow(w) {
  return '<div class="worker-row"><span class="avatar ' + w.tone + '">' + w.init + "</span>" +
    '<div class="wr-main"><div class="wr-name"><a href="worker-profile.html">' + w.name + "</a>" +
    (w.verified ? " " + vbadge : "") + '</div><div class="wr-head">' + w.headline + "</div>" +
    '<div class="wr-meta"><span>' + I.pin + w.city + ", " + w.state + '</span><span>' + I.clock + "Replies " + w.resp + "</span>" +
    '<span>' + I.checkc + w.jobs + ' orders completed</span></div></div>' +
    '<div class="wr-side"><div class="wr-price">from ' + ngn(w.from) + ' <small>' + w.mode + "</small></div>" +
    '<span class="rating-line">' + stars(w.rating) + " <b>" + w.rating + "</b> (" + w.reviews + ')</span></div></div>';
}
function resultItem(w) {
  const svc = MOCK.services.find((s) => s.workerId === w.id);
  return '<div class="result-item">' +
    '<span class="avatar ' + w.tone + '">' + w.init + '</span><div class="ri-main">' +
    '<div class="ri-top"><span class="ri-name"><a href="worker-profile.html">' + w.name + "</a></span>" +
    (w.verified ? " " + vbadge : "") + (w.promoted ? ' <span class="tag tag-promoted">Promoted</span>' : "") + "</div>" +
    '<div class="ri-skill">' + w.headline + "</div>" +
    '<div class="ri-meta"><span>' + I.pin + w.city + ", " + w.state + '</span><span>' + stars(w.rating) + ' <b style="color:var(--ink)">' + w.rating + "</b> (" + w.reviews + ')</span>' +
    '<span>' + I.clock + "Replies " + w.resp + '</span><span>' + I.checkc + w.jobs + " orders completed</span></div>" +
    (svc ? '<div class="ri-svc"><div class="svc-line"><b><a href="service-detail.html">' + svc.title + "</a></b>" +
      '<span>from <span class="amount">' + ngn(svc.from) + "</span></span></div></div>" : "") +
    '</div><div class="ri-side"><span class="rating-line">' + stars(w.rating) + " <b>" + w.rating + "</b></span>" +
    (svc ? '<a class="btn btn-secondary btn-sm" href="service-detail.html">View service</a>' : "") +
    '<a class="btn btn-primary btn-sm" href="worker-profile.html">View profile</a></div></div>';
}
function jobCard(j) {
  const st = { remote: "st-royal", "on-site": "st-amber" };
  return '<div class="job-card"><div class="jc-top"><h3><a href="job-detail.html">' + j.title + "</a></h3>" +
    '<span class="jc-budget">' + (j.budgetType === "fixed" ? ngn(j.budget) : "Negotiable") + "</span></div>" +
    '<div class="jc-meta"><span>' + (j.mode === "remote" ? I.code : I.truck) + '<span class="st ' + st[j.mode] + '" style="padding:1px 8px">' + j.mode + "</span></span>" +
    "<span>" + I.pin + j.loc + '</span><span>' + I.clock + "Posted " + j.time + "</span>" +
    (j.deadline !== "—" ? '<span>' + I.clock + "Due " + j.deadline + "</span>" : "") + "</div>" +
    '<div class="jc-foot"><span class="jc-proposals"><b style="color:var(--ink)">' + j.proposals + "</b> proposals · client " + j.clientRating + "★" +
    (j.verified ? ' <span class="badge-verified" style="padding:1px 7px">' + I.shield + "</span>" : "") + "</span>" +
    '<a class="btn btn-primary btn-sm" href="job-detail.html">Apply</a></div></div>';
}
const stClass = { delivered: "st-teal", in_progress: "st-amber", completed: "st-green", paid: "st-royal", settled: "st-green" };
function cdRecentRow(o) {
  const init = o.party[0] + o.party.split(" ")[1][0];
  return '<div class="queue-row" style="padding:10px 0"><span class="avatar sm ' + o.tone + '">' + init + "</span>" +
    '<div class="qr-main"><div class="qr-t">' + o.title + '</div><div class="qr-s">' + o.id + " · " + ngn(o.amount) + "</div></div>" +
    '<span class="st ' + stClass[o.state] + '">' + o.stateLabel + '</span><a class="link" style="font-size:12.5px" href="order-detail.html">Open</a></div>';
}
function cdOrderRow(o) {
  const init = o.party[0] + o.party.split(" ")[1][0];
  return "<tr><td><span class=\"cell-main\">" + o.title + '</span><div class="cell-sub">' + o.id + "</div></td>" +
    '<td><span class="row" style="gap:8px"><span class="avatar sm ' + o.tone + '">' + init + "</span>" + o.party + "</span></td>" +
    '<td class="num amount">' + ngn(o.amount) + "</td><td>" + o.date + "</td>" +
    '<td><span class="st ' + stClass[o.state] + '">' + o.stateLabel + "</span></td>" +
    '<td class="right"><a class="btn btn-secondary btn-sm" href="order-detail.html">Open</a></td></tr>';
}

/* ---------- chrome builders ---------- */
function publicHeader(active) {
  const nav = [
    ["Find Work", "jobs.html", "jobs"],
    ["Find Talent", "search.html", "search"],
    ["Learn", "index.html#learn", "learn"],
    ["Categories", "category.html", "category"]
  ];
  const links = nav.map((n) => '<a href="' + n[1] + '"' + (n[2] === active ? ' class="active"' : "") + ">" + n[0] + "</a>").join("\n      ");
  const mobile = nav.map((n) => '<a href="' + n[1] + '"' + (n[2] === active ? ' class="active"' : "") + ">" + n[0] + "</a>").join("\n    ");
  return '<header class="topbar">\n  <input type="checkbox" id="navCheck" class="nav-check" aria-hidden="true">\n' +
    '  <div class="container topbar-in">\n    <a class="brand" href="index.html"><img class="brand-logo" src="assets/img/skilvi-logo-word.png" alt="Skilvi"></a>\n' +
    '    <nav class="mainnav">\n      ' + links + "\n    </nav>\n" +
    '    <div class="topact">\n      <a class="btn btn-ghost" href="login.html">Sign In</a>\n      <a class="btn btn-primary" href="login.html#regForm">Sign Up</a>\n    </div>\n' +
    '    <label class="icon-btn nav-toggle" for="navCheck" aria-label="Menu">' + I.menu + "</label>\n  </div>\n" +
    '  <div class="mobile-nav" id="mobileNav">\n    ' + mobile + '\n    <a href="login.html">Sign In</a>\n    <a href="login.html#regForm">Sign Up</a>\n  </div>\n</header>';
}
function footer() {
  return '<footer class="footer">\n  <div class="footer-in">\n    <div class="footer-grid">\n' +
    '      <div>\n        <div class="f-brand"><img class="brand-logo" src="assets/img/skilvi-logo-word.png" alt="Skilvi"></div>\n" +
    '        <p class="f-desc">Discover. Learn. Earn. A marketplace built for Nigeria — digital skills and hands-on trades, paid securely in Naira.</p>\n      </div>\n' +
    "      <div><h4>For clients</h4><a href=\"search.html\">Browse workers</a><a href=\"jobs.html\">Post a job</a><a href=\"help.html#faq-escrow\">How escrow works</a><a href=\"help.html#faq-fees\">Fees &amp; pricing</a></div>\n" +
    "      <div><h4>For workers</h4><a href=\"login.html#regForm\">Create a free profile</a><a href=\"verification.html\">Get verified</a><a href=\"help.html#faq-withdrawals\">Withdraw your money</a><a href=\"promotion.html\">Promote your profile</a></div>\n" +
    "      <div><h4>Company</h4><a href=\"about.html\">About Skilvi</a><a href=\"help.html\">Help center</a><a href=\"terms.html\">Terms of service</a><a href=\"privacy.html\">Privacy policy</a><a href=\"help.html\">Contact support</a></div>\n" +
    '    </div>\n    <div class="footer-bottom"><span>© 2026 Skilvi Technologies Ltd. All rights reserved.</span><span>Built in Nigeria · Payments held securely in escrow</span></div>\n  </div>\n</footer>';
}
function sideItem(href, icon, label, active, badge) {
  return '    <a class="side-item' + (active ? " active" : "") + '" href="' + href + '">' + I[icon] + "<span>" + label + "</span>" +
    (badge ? ' <span class="badge-n">' + badge + "</span>" : "") + "</a>\n";
}
function workerSide(a) {
  return '<aside class="side">\n  <div class="side-brand"><a class="brand" href="worker-dashboard.html"><img class="brand-logo" src="assets/img/skilvi-logo-word.png" alt="Skilvi"></a></div>\n' +
    '  <div class="side-label">Work</div>\n' +
    sideItem("worker-dashboard.html", "grid", "Overview", a === "overview") +
    sideItem("worker-orders.html", "briefcase", "My work", a === "orders") +
    sideItem("worker-services.html", "layers", "Services", a === "services") +
    sideItem("worker-jobs.html", "jobs", "Jobs & proposals", a === "jobs", "3") +
    '  <div class="side-label">Money</div>\n' +
    sideItem("worker-wallet.html", "wallet2", "Wallet", a === "wallet") +
    sideItem("worker-wallet.html?tab=withdrawals", "download", "Withdrawals", false) +
    sideItem("verification.html", "shield", "Verification", a === "verification") +
    sideItem("promotion.html", "zap", "Promotion", a === "promotion") +
    '  <div class="side-label">Account</div>\n' +
    sideItem("messages.html", "msg", "Messages", a === "messages", "2") +
    sideItem("disputes.html", "scale", "Disputes", a === "disputes") +
    sideItem("account-settings.html", "gear", "Settings", a === "settings") +
    '  <div class="side-foot"><div class="side-user"><span class="avatar sm a1">CO</span><div><div class="su-name">Chinedu Okafor</div><div class="su-role">Worker · Verified</div></div></div></div>\n</aside>';
}
function clientSide(a) {
  return '<aside class="side">\n  <div class="side-brand"><a class="brand" href="client-dashboard.html"><img class="brand-logo" src="assets/img/skilvi-logo-word.png" alt="Skilvi"></a></div>\n' +
    '  <div class="side-label">Marketplace</div>\n' +
    sideItem("client-dashboard.html", "grid", "Overview", a === "overview") +
    sideItem("client-dashboard.html?tab=orders", "briefcase", "My orders", a === "orders") +
    sideItem("client-dashboard.html?tab=jobs", "jobs", "My jobs", a === "jobs") +
    sideItem("saved.html", "heart", "Saved workers", a === "saved") +
    sideItem("client-dashboard.html?tab=payments", "card", "Payments", a === "payments") +
    '  <div class="side-label">Account</div>\n' +
    sideItem("messages.html", "msg", "Messages", a === "messages", "1") +
    sideItem("disputes.html", "scale", "Disputes", a === "disputes") +
    sideItem("account-settings.html", "gear", "Settings", a === "settings") +
    '  <div class="side-foot"><div class="side-user"><span class="avatar sm a2">AB</span><div><div class="su-name">Aisha Bello</div><div class="su-role">Client · Adaeze Boutique</div></div></div></div>\n</aside>';
}
function adminSide(a) {
  return '<aside class="side">\n  <div class="side-brand"><a class="brand" href="../index.html"><img class="brand-logo" src="../assets/img/skilvi-logo-word.png" alt="Skilvi"></a><span class="admin-tag">Admin</span></div>\n'
    '  <div class="side-label">Operations</div>\n' +
    sideItem("index.html", "grid", "Dashboard", a === "dashboard") +
    sideItem("orders.html", "briefcase", "Orders", a === "orders") +
    sideItem("payments.html", "card", "Payments", a === "payments") +
    sideItem("withdrawals.html", "wallet2", "Withdrawals", a === "withdrawals", "2") +
    '  <div class="side-label">Trust &amp; safety</div>\n' +
    sideItem("disputes.html", "scale", "Disputes", a === "disputes", "3") +
    sideItem("reports.html", "flag", "Reports", a === "reports", "5") +
    sideItem("verification.html", "shield", "Verification", a === "verification") +
    '  <div class="side-label">Platform</div>\n' +
    sideItem("users.html", "users", "Users", a === "users") +
    sideItem("categories.html", "layers", "Categories", a === "categories") +
    sideItem("support.html", "help", "Support inbox", a === "support") +
    '  <div class="side-label">System</div>\n' +
    sideItem("audit-log.html", "file", "Audit log", a === "audit") +
    '  <div class="side-foot"><div class="side-user"><span class="avatar sm a1">AO</span><div><div class="su-name">Admin</div><div class="su-role">Super admin</div></div></div></div>\n</aside>';
}
function subbar(kind) {
  const user = kind === "worker" ? ['a1', 'CO', 'Chinedu'] : kind === "client" ? ['a2', 'AB', 'Aisha'] : ['a1', 'AO', 'Admin'];
  const home = kind === "admin" ? "../index.html" : "index.html";
  return '<header class="subbar">\n    <label class="icon-btn side-toggle" for="sideCheck" aria-label="Menu">' + I.menu + '</label>\n  ' + (kind === "admin" ? '<span class="sb-crumb">Admin console</span>\n  ' : "") +
    '<div class="sb-actions">\n    <a class="link-muted" href="' + home + '">View marketplace</a>\n' +
    '    <a class="icon-btn" href="notifications.html" aria-label="Notifications">' + I.bell + '<span class="dot"></span></a>\n' +
    '    <a class="sb-user" href="' + (kind === "admin" ? "users.html" : "account-settings.html") + '"><span class="avatar sm ' + user[0] + '">' + user[1] + '</span><span class="su-name">' + user[2] + "</span></a>\n" +
    "  </div>\n</header>";
}

/* ---------- per-page content ---------- */
const W = (id) => MOCK.workers.find((w) => w.id === id);
const CONTENT = {
  "index.html": {
    heroEyebrow: I.shield + " Nigeria-first · Escrow-protected",
    searchIco: I.search,
    ctaIco: I.arrow,
    trustStrip: [
      [I.shield, "Escrow-protected payments"], [I.checkc, "Verified workers"],
      [I.bank, "Naira · card, bank transfer, USSD"], [I.chat, "Real support, in English"]
    ].map(([i, t]) => '<span class="trust-item">' + i + t + "</span>").join(""),
    catGrid: [
      '<a class="cat-tile" href="search.html"><span class="cat-ico">' + I.grid + '</span><span><span class="cat-name">All services</span><br><span class="cat-count">1,400+ workers</span></span></a>',
      ...MOCK.categories.map((c) => {
        const ic = { code: I.code, pen: I.pen, wrench: I.wrench, briefcase: I.briefcase, heart: I.heart }[c.icon];
        return '<a class="cat-tile" href="category.html"><span class="cat-ico">' + ic + '</span><span><span class="cat-name">' + c.name + "</span><br><span class=\"cat-count\">" + c.workers + " workers</span></span></a>";
      })
    ].join(""),
    howSteps: [
      ["1", "Find & compare", "Search by skill or category. Compare ratings, verified badges, prices and past reviews before you hire."],
      ["2", "Pay Skilvi, not the worker", "Your payment is held safely in escrow by Skilvi. The worker starts only after you've paid — you're protected from day one."],
      ["3", "Release when it's done", "Approve the delivered work to release the payment. Not satisfied? Request revisions or open a dispute — we mediate."]
    ].map(([n, t, d]) => '<div class="step"><span class="step-num">' + n + "</span><h3>" + t + "</h3><p>" + d + "</p></div>").join(""),
    topWorkers: ["w01", "w03", "w06", "w02"].map((id) => workerRow(W(id))).join("\n    "),
    newJobs: MOCK.jobs.slice(0, 3).map((j) => {
      const st = { remote: "st-royal", "on-site": "st-amber" };
      return '<div class="job-card"><div class="jc-top"><h3><a href="job-detail.html">' + j.title + "</a></h3>" +
        '<span class="jc-budget">' + (j.budgetType === "fixed" ? ngn(j.budget) : "Negotiable") + "</span></div>" +
        '<div class="jc-meta"><span>' + (j.mode === "remote" ? I.code : I.truck) + j.mode + '</span><span>' + I.pin + j.loc + '</span><span>' + I.clock + j.time + "</span></div>" +
        '<div class="jc-foot"><span class="jc-proposals">' + j.proposals + ' proposals</span><a class="btn btn-secondary btn-sm" href="job-detail.html">View job</a></div></div>';
    }).join("\n    ")
  },
  "search.html": {
    results: MOCK.workers.map(resultItem).join("\n    "),
    rcCount: String(MOCK.workers.length)
  },
  "category.html": {
    catHead: '<div class="row" style="gap:16px; align-items:center"><span class="cat-ico" style="width:52px;height:52px">' + I.wrench + "</span>" +
      '<div class="grow"><h1 style="font-size:24px">Trades &amp; Home Services</h1><p class="small muted mt-1">Plumbing, electrical, tiling, painting, AC, generator power and more. All on-site workers show their service area before you hire. Travel costs are stated up front.</p></div>' +
      '<div class="stack" style="gap:8px; min-width:150px"><span class="tag tag-royal">289 workers</span><span class="tag">Mostly on-site</span></div></div>',
    subChips: ["Plumbing", "Electrical", "Tiling & Masonry", "Painting", "AC Installation & Repair", "Generator & Power", "Appliance Repair", "Locksmith"]
      .map((s, i) => '<a class="chip' + (i === 0 ? " active" : "") + '" href="category.html">' + s + "</a>").join("\n    "),
    catResults: ["w03", "w05", "w06", "w08", "w10", "w04"].map((id) => {
      const w = W(id);
      const svc = MOCK.services.find((s) => s.workerId === w.id);
      return '<div class="result-item"><span class="avatar ' + w.tone + '">' + w.init + '</span><div class="ri-main">' +
        '<div class="ri-top"><span class="ri-name"><a href="worker-profile.html">' + w.name + "</a></span>" + (w.verified ? " " + vbadge : "") + "</div>" +
        '<div class="ri-skill">' + w.headline + '</div><div class="ri-meta"><span>' + I.pin + w.city + ", " + w.state + '</span><span>' + I.truck + "On-site</span>" +
        '<span>' + I.clock + "Replies " + w.resp + '</span><span>' + I.checkc + w.jobs + " orders completed</span></div>" +
        (svc ? '<div class="ri-svc"><div class="svc-line"><b><a href="service-detail.html">' + svc.title + "</a></b><span>from <span class=\"amount\">" + ngn(svc.from) + "</span></span></div></div>" : "") +
        '</div><div class="ri-side"><span class="rating-line">' + stars(w.rating) + " <b>" + w.rating + '</b></span><a class="btn btn-primary btn-sm" href="service-detail.html">View service</a></div></div>';
    }).join("\n    ")
  },
  "worker-profile.html": {
    vBadge: I.shield + " Identity verified",
    phMeta: '<span>' + I.pin + "Port Harcourt, Rivers</span><span>" + I.code + "Remote work</span><span>" + I.clock + "Replies within ~1 hour</span><span>" + I.checkc + "97% on-time delivery</span>",
    ssRating: stars(4.9) + " 4.9",
    skillTags: ["React", "WordPress", "WooCommerce", "Tailwind CSS", "Figma to code", "SEO basics", "Speed optimisation"].map((s) => '<span class="tag">' + s + "</span>").join("\n        "),
    profileServices: [
      ["Website development — landing pages to full sites", [["Starter", 45000, 5, 1], ["Standard", 85000, 10, 2], ["Premium", 150000, 20, 4]]],
      ["WordPress care & fixes", [["Fix package", 15000, 2, 1], ["Monthly care", 30000, 30, 3]]]
    ].map(([title, pkgs]) =>
      '<div class="card card-pad"><div class="row spread"><h3 style="font-family:var(--font-body)">' + title + '</h3><a class="btn btn-secondary btn-sm" href="service-detail.html">View details</a></div>' +
      pkgs.map((p) => '<div class="kv"><span class="k">' + p[0] + '</span><span class="v">' + ngn(p[1]) + " · " + p[2] + " days · " + p[3] + " revisions</span></div>").join("") + "</div>"
    ).join("\n      "),
    portfolioGrid: [
      ["Naija Foods — restaurant landing page", "Web · 2026", "image", ""],
      ["Pharma+ — WordPress storefront", "E-commerce · 2025", "code", "t2"],
      ["Harbour Estates — property site", "Web · 2025", "image", "t3"],
      ["QuickLoan — mobile app UI", "UI/UX · 2025", "pen", ""]
    ].map(([t, s, ico, tone]) => '<div class="port-item"><div class="port-thumb ' + tone + '">' + I[ico] + '</div><div class="port-cap"><div class="pc-title">' + t + '</div><div class="pc-sub">' + s + "</div></div></div>").join("\n      "),
    rvStars: stars(4.9),
    dist: [[5, 92], [4, 6], [3, 1], [2, 1], [1, 0]].map(([s, pct]) =>
      '<div class="row" style="gap:8px;font-size:12px;color:var(--ink-3)"><span style="width:14px">' + s + "★</span>" +
      '<span style="flex:1;height:6px;background:var(--bg);border-radius:99px;overflow:hidden;display:block"><span style="display:block;height:100%;width:' + pct + "%;background:var(--royal-600);border-radius:99px\"></span></span>" +
      '<span style="width:26px;text-align:right">' + pct + "%</span></div>").join("\n      "),
    reviewList: MOCK.reviews.map((r) =>
      '<div class="review-card"><div class="rv-head"><span class="avatar sm ' + r.tone + '">' + r.init + '</span><span class="rv-name">' + r.name + "</span>" +
      stars(r.stars) + '<span class="rv-date">' + r.date + "</span></div>" +
      '<p class="rv-text">' + r.text + '</p><div class="rv-tags">' + r.tags.map((t) => '<span class="tag">' + t + "</span>").join("") + "</div>" +
      '<div class="rv-reply"><b>Chinedu Okafor replied:</b> Thank you! It was a pleasure working with you — reach out any time you need the site updated.</div></div>'
    ).join("\n      ")
  },
  "service-detail.html": {
    ck1: I.check, ck2: I.check, ck3: I.check,
    svcBadge: I.shield + " Verified",
    svcRating: stars(4.9) + " <b>4.9</b> · 132 reviews · replies ~1 hr",
    escrowNote: I.shield + "<span><b>Protected by Skilvi escrow.</b> You pay Skilvi, not the worker directly. Your money is held safely and released only when the work is done and you approve it.</span>"
  },
  "jobs.html": {
    plusIco: I.plus,
    jobGrid: MOCK.jobs.map(jobCard).join("\n    ")
  },
  "job-detail.html": {
    pb1: I.shield + " Verified",
    pb2: I.shield + " Verified",
    pr1: stars(4.9) + " <b>4.9</b> · 64 reviews · 91 orders",
    pr2: stars(4.7) + " <b>4.7</b> · 31 reviews · 48 orders",
    pr3: stars(4.5) + " <b>4.5</b> · 22 reviews · 40 orders",
    pr4: stars(4.8) + " <b>4.8</b> · 12 orders completed",
    fc1: I.image + "bathroom-1.jpg (2.1 MB)",
    fc2: I.image + "bathroom-2.jpg (1.8 MB)",
    jdEscrow: I.shield + "<span><b>How payment works:</b> once you accept a proposal, you pay into Skilvi escrow. The worker starts after payment is confirmed, and the money is released only when you approve the finished work.</span>"
  },
  "post-job.html": {
    nextSteps: [
      ["1", "Job goes live", "Matching workers are notified and it appears in their job feed."],
      ["2", "Review proposals", "Compare price, timeline, ratings and past work. Chat with any of them."],
      ["3", "Accept one", "An order is created at the accepted price. You pay into Skilvi escrow."],
      ["4", "Work & approval", "The worker delivers; you approve and the payment is released."],
      ["5", "Review", "Leave a review. The worker's reputation grows on Skilvi."]
    ].map(([n, t, d]) => '<div class="row" style="gap:11px"><span class="step-num" style="margin:0;width:26px;height:26px;font-size:12.5px">' + n + '</span><div><div class="bold small">' + t + '</div><div class="tiny faint">' + d + "</div></div></div>").join("\n        "),
    pjEscrow: I.shield + "<span><b>Your money is protected.</b> You never pay a worker directly. Payment sits with Skilvi until you approve the completed work — and you can open a dispute if anything goes wrong.</span>"
  },
  "login.html": {
    lgTrust: I.shield + "<span><b>Safe by design.</b> Accounts are email-verified, and every order is paid into escrow — neither side can run.</span>"
  },
  "help.html": {
    hIco: I.search, mIco: I.mail, cIco: I.chat,
    topicGrid: [
      [I.shield, "How escrow works", "Money held until the work is done."],
      [I.card, "Fees & pricing", "10% commission, free posting."],
      [I.checkc, "Getting verified", "Identity check, one-time ₦5,000."],
      [I.zap, "Promotion", "Pay for visibility — always labelled."],
      [I.bank, "Withdrawals", "Naira to your bank, next business day."],
      [I.scale, "Disputes", "How we mediate, SLAs, outcomes."],
      [I.flag, "Reporting a user", "Scams, abuse, off-platform payment."],
      [I.briefcase, "Starting as a worker", "Profile → services → first order."]
    ].map(([i, t, d]) => '<a class="topic-card" href="' + ({ "How escrow works": "#faq-escrow", "Fees & pricing": "#faq-fees", "Getting verified": "#faq-verification", "Promotion": "#faq-promotion", "Withdrawals": "#faq-withdrawals", "Disputes": "#faq-disputes", "Reporting a user": "#faq-reporting", "Starting as a worker": "#faq-starting" }[t] || "#") + '"><span class="tc-ico">' + i + "</span><h3>" + t + "</h3><p>" + d + "</p></a>").join("\n      ")
  },
  "client-dashboard.html": {
    sIco: I.search,
    cdStats: [
      [I.briefcase, "Active orders", "2", "1 needs your approval"],
      [I.wallet2, "Spent this month", "₦130,000", "2 orders"],
      [I.jobs, "Open jobs", "1", "5 proposals"],
      [I.shield, "Escrow-protected", "100%", "of your payments"]
    ].map(([i, l, v, d]) => '<div class="stat-card"><div class="label">' + i + l + '</div><div class="value">' + v + '</div><div class="delta">' + d + "</div></div>").join("\n          "),
    cdRecent: MOCK.orders.slice(0, 3).map(cdRecentRow).join("\n            "),
    cdOrders: MOCK.orders.map(cdOrderRow).join("\n              ")
  },
  "order-detail.html": {
    msgIco: I.msg, dIco1: I.code, dIco2: I.file,
    odEscrow: I.shield + "<span><b>Escrow status:</b> ₦85,000 held by Skilvi. Released on approval, auto-released 5 business days after delivery, or resolved via dispute if you disagree.</span>",
    dispNote: I.flag + "<span><b>Dispute DSP-2031 is under review.</b> Escrow of ₦85,000 is frozen. Skilvi support has acknowledged it — resolution target within 5 business days. Message the worker if you can resolve it yourselves.</span>",
    rateRow: [1, 2, 3, 4, 5].map((n) => '<button type="button" class="icon-btn" style="width:44px;height:44px;border-radius:50%;color:#F59E0B" data-toast="Rated ' + n + ' / 5">' + I.star + "</button>").join("")
  },
  "worker-dashboard.html": {
    wbBadge: I.shield + " Verified",
    wdStats: [
      [I.wallet2, "Available balance", "₦412,500", "₦130,000 pending in escrow"],
      [I.briefcase, "Active orders", "2", "1 delivered, 1 in progress"],
      [I.jobs, "Proposals pending", "3", "2 need your attention"],
      [I.star, "Rating", "4.9", "132 reviews · 97% on-time"]
    ].map(([i, l, v, d]) => '<div class="stat-card"><div class="label">' + i + l + '</div><div class="value">' + v + '</div><div class="delta">' + d + "</div></div>").join("\n          "),
    wdBars: (function () { const d = MOCK.admin.gmv, m = Math.max(...d.map((x) => x.v)); return d.map((x, i) => '<div class="bar' + (i === 4 ? " peak" : "") + '" style="height:' + Math.round((x.v / m) * 100) + '%"><span class="bar-tip">₦' + x.v + "k</span></div>").join(""); })(),
    wdBarLabels: MOCK.admin.gmv.map((d) => "<span>" + d.day + "</span>").join(""),
    wdRecent: [
      ["OR-1042", "Landing page — Naija Foods", "Adaeze Boutique", 85000, 76500, "st-teal", "Awaiting approval"],
      ["OR-1031", "WordPress site — Naija Foods", "Naija Foods Ltd", 85000, 76500, "st-green", "Settled Sep 15"],
      ["OR-1024", "Landing page — Zara Wears", "Zara Wears", 75000, 67500, "st-green", "Settled Sep 8"],
      ["OR-1011", "Shopify fixes — Zara Wears", "Zara Wears", 50000, 45000, "st-green", "Settled Sep 1"],
      ["OR-0998", "Brand site — Harbour Estates", "Harbour Estates", 150000, 135000, "st-green", "Settled Aug 24"]
    ].map((r) => "<tr><td><span class=\"cell-main\">" + r[1] + '</span><div class="cell-sub">' + r[0] + "</div></td>" +
      "<td>" + r[2] + '</td><td class="num amount">' + ngn(r[3]) + '</td><td class="num">' + ngn(r[4]) + "</td>" +
      '<td><span class="st ' + r[5] + '">' + r[6] + '</span></td><td class="right"><a class="btn btn-secondary btn-sm" href="order-detail.html">Open</a></td></tr>').join("\n            ")
  },
  "worker-orders.html": (function () {
    const orders = [
      { id: "OR-1042", title: "Landing page — Naija Foods", client: "Adaeze Boutique", amount: 85000, escrow: "Held", state: "delivered", chip: "st-teal", label: "Awaiting client approval", next: "Reply within 24h if asked" },
      { id: "OR-1036", title: "WordPress rebuild — Brew & Co", client: "Brew & Co", amount: 120000, escrow: "Held", state: "active", chip: "st-amber", label: "In progress", next: "Deliver by Sep 24" },
      { id: "OR-1031", title: "WordPress site — Naija Foods", client: "Naija Foods Ltd", amount: 85000, escrow: "Released", state: "completed", chip: "st-green", label: "Settled Sep 15", next: "₦76,500 in wallet" },
      { id: "OR-1024", title: "Landing page — Zara Wears", client: "Zara Wears", amount: 75000, escrow: "Released", state: "completed", chip: "st-green", label: "Settled Sep 8", next: "₦67,500 in wallet" },
      { id: "OR-1011", title: "Shopify fixes — Zara Wears", client: "Zara Wears", amount: 50000, escrow: "Released", state: "completed", chip: "st-green", label: "Settled Sep 1", next: "Review received: 5★" },
      { id: "OR-1003", title: "Wiring estimate follow-up — GRA", client: "M. Okonkwo", amount: 450000, escrow: "Frozen", state: "disputed", chip: "st-red", label: "Dispute DSP-2031", next: "Evidence uploaded" },
      { id: "OR-0998", title: "Brand site — Harbour Estates", client: "Harbour Estates", amount: 150000, escrow: "Released", state: "completed", chip: "st-green", label: "Settled Aug 24", next: "Review received: 5★" }
    ];
    const row = (o) => "<tr><td><span class=\"cell-main\">" + o.title + '</span><div class="cell-sub">' + o.id + " · " + o.client + "</div></td>" +
      '<td class="num amount">' + ngn(o.amount) + '</td><td><span class="tag">' + o.escrow + '</span></td>' +
      '<td><span class="st ' + o.chip + '">' + o.label + "</span></td>" +
      '<td class="cell-sub">' + o.next + '</td><td class="right nowrap"><a class="btn btn-secondary btn-sm" href="order-detail.html">Open</a> <a class="btn btn-ghost btn-sm" href="messages.html">Chat</a></td></tr>';
    const head = '<table class="table"><thead><tr><th>Order</th><th class="num">Amount</th><th>Escrow</th><th>Status</th><th>Next action</th><th></th></tr></thead><tbody>';
    const t = (list) => head + list.map(row).join("\n") + "</tbody></table>";
    return {
      woTable: t(orders),
      woTableActive: t(orders.filter((o) => o.state === "active")),
      woTableDelivered: t(orders.filter((o) => o.state === "delivered")),
      woTableCompleted: t(orders.filter((o) => o.state === "completed")),
      woTableDisputed: t(orders.filter((o) => o.state === "disputed"))
    };
  })(),
  "worker-wallet.html": {
    wIco1: I.wallet2 + " Available",
    wIco2: I.shield + " Pending (escrow)",
    wIco3: I.chart + " Lifetime",
    wdList: MOCK.wallet.withdrawals.map((w) =>
      '<div class="kv"><span class="k">' + w.date + " · " + w.bank + ' <span class="cell-sub">' + w.id + '</span></span><span class="v"><span class="amount">' + ngn(w.amount) + '</span> <span class="st ' + (w.state === "paid" ? "st-green" : "st-red") + '" style="margin-left:8px">' + w.stateLabel + "</span></span></div>").join("\n          "),
    wdLedger: (function () {
      const dot = { settlement: "var(--green)", commission: "var(--red)", withdrawal: "var(--amber)" };
      return MOCK.wallet.ledger.map((l) =>
        "<tr><td class=\"cell-sub\">" + l.date + '</td><td><span class="ledger-type"><span class="lt-dot" style="background:' + dot[l.type] + '"></span>' + l.label + '</span><div class="cell-sub">' + l.ref + "</div></td>" +
        '<td class="num amount" style="color:' + (l.amount < 0 ? "var(--red)" : "var(--ink)") + '">' + (l.amount < 0 ? "−" : "+") + ngn(Math.abs(l.amount)) + '</td><td class="num cell-sub">' + ngn(l.bal) + "</td></tr>").join("\n            ");
    })()
  },
  "worker-services.html": {
    plusIco: I.plus,
    svAlert: I.zap + "<span><b>Tip:</b> services with 3 packages get booked 2× more often than single-price services. A “custom quote” option catches the jobs that don't fit your tiers.</span>"
  },
  "worker-service-form.html": {
    xIco1: I.x,
    areaChips: ["Rivers", "Lagos", "Abia", "FCT"].map((a, i) => '<button type="button" class="chip' + (i === 0 ? " active" : "") + '" style="font-size:12px;padding:4px 10px">' + a + "</button>").join("\n              "),
    sellSteps: [
      "Client picks a package and answers your brief questions.",
      "They pay into Skilvi escrow — you see “worker to start” instantly.",
      "Deliver, then submit the work for approval.",
      "Client approves → 90% lands in your wallet (10% Skilvi)."
    ].map((s, i) => '<div class="row" style="gap:10px"><span class="step-num" style="margin:0;width:24px;height:24px;font-size:11.5px">' + (i + 1) + '</span><span class="small" style="color:var(--ink-2)">' + s + "</span></div>").join("\n          "),
    sfEscrow: I.shield + "<span><b>Revisions are part of the contract.</b> Once the included rounds are used, the client can only approve or open a dispute — scope creep is your biggest risk, and your brief is your shield.</span>"
  },
  "worker-jobs.html": {
    wjTip: I.zap + "<span><b>Winning tips:</b> proposals under 200 words with one portfolio link and a specific start date get accepted ~2× more often. Never underbid your package prices by more than 10%.</span>",
    wjFeed: MOCK.jobs.map((j) =>
      '<div class="job-card"><div class="jc-top"><h3><a href="job-detail.html">' + j.title + "</a></h3>" +
      '<span class="jc-budget">' + (j.budgetType === "fixed" ? ngn(j.budget) : "Negotiable") + "</span></div>" +
      '<div class="jc-meta"><span>' + (j.mode === "remote" ? I.code : I.truck) + j.mode + '</span><span>' + I.pin + j.loc + '</span><span>' + I.clock + j.time + "</span></div>" +
      '<div class="jc-meta small" style="color:var(--ink-2)">' + j.client + " · " + j.clientRating + "★" + (j.verified ? ' <span class="badge-verified" style="padding:1px 7px">' + I.shield + "</span>" : "") + "</div>" +
      '<div class="jc-foot"><span class="jc-proposals">' + j.proposals + ' proposals so far</span><a class="btn btn-primary btn-sm" href="job-detail.html">View &amp; apply</a></div></div>'
    ).join("\n          "),
    wjProps: (function () {
      const stMap = { pending: "st-amber", accepted: "st-green", rejected: "st-gray" };
      return MOCK.proposals.map((p) =>
        "<tr><td><span class=\"cell-main\">" + p.job + '</span><div class="cell-sub">' + p.jobId.toUpperCase() + "</div></td>" +
        '<td class="num amount">' + ngn(p.price) + '</td><td>' + p.days + " days</td><td>" + p.date + "</td>" +
        '<td><span class="st ' + stMap[p.status] + '">' + p.statusLabel + "</span></td>" +
        '<td class="right">' + (p.status === "pending" ? '<a class="btn btn-secondary btn-sm" href="job-detail.html">View</a>' : '<a class="link" style="font-size:13px" href="order-detail.html">View order</a>') + "</td></tr>").join("\n            ");
    })()
  },
  "messages.html": {
    mBadge: I.shield + " Verified",
    mIco: I.plus,
    mFile: I.image + "hero-restaurant.jpg (2.4 MB)",
    chatWarn: I.flag + "<span><b>Keep payment on Skilvi.</b> Money paid off-platform isn't protected by escrow. If you share a contact detail, we'll flag this thread for review.</span>"
  },
  "disputes.html": {
    dAlert: I.scale + "<span><b>Before you open a dispute:</b> a direct message in the order thread resolves ~70% of cases. Keep it on Skilvi — that conversation becomes the evidence if you do escalate.</span>",
    dDrop: I.file + "<br>Drop files here or <b style=\"color:var(--royal-600)\">browse</b> — photos, screenshots, PDFs (max 10 MB each)"
  },
  "dispute-detail.html": {
    caseNote: I.flag + "<span><b>Escrow frozen:</b> ₦450,000 cannot be released, withdrawn or refunded while this case is open. The order stays paused — no new deliverables, no revision requests.</span>",
    ev1: I.image, ev2: I.image, ev3: I.file, ev4: I.msg,
    caseEscrow: I.shield + "<span><b>Policy note:</b> where the client is unresponsive past 5 business days and the worker has timestamped evidence of partial completion, Skilvi applies a partial split in line with the scope agreed in the order.</span>"
  },
  "account-settings.html": {
    stNote: I.shield + "<span><b>Why the trust score?</b> It combines on-time delivery, dispute history and payment reliability. It's visible to Skilvi support (not to other users) and affects how quickly your withdrawals are auto-approved.</span>"
  },
  "admin/index.html": {
    kpiGrid: [
      ["GMV (7d)", "₦3.53M", "+18% vs prior week"], ["Orders created", "24", "12 direct · 12 via proposals"],
      ["Orders completed", "18", "settlement rate 92%"], ["Open disputes", "3", "1 SLA-breach risk"],
      ["Pending withdrawals", "₦470k", "2 in review queue"], ["Open reports", "5", "2 above threshold"]
    ].map(([l, v, s]) => '<div class="kpi"><div class="k-label">' + l + '</div><div class="k-value">' + v + '</div><div class="k-sub">' + s + "</div></div>").join("\n      "),
    gmxBars: (function () { const d = MOCK.admin.gmv, m = Math.max(...d.map((x) => x.v)); return d.map((x, i) => '<div class="bar' + (i === 4 ? " peak" : "") + '" style="height:' + Math.round((x.v / m) * 100) + '%"><span class="bar-tip">₦' + x.v + "k</span></div>").join(""); })(),
    gmxLabels: MOCK.admin.gmv.map((d) => "<span>" + d.day + "</span>").join(""),
    gmxDisputes: MOCK.admin.disputes.map((d) =>
      '<div class="queue-row"><div class="qr-main"><div class="qr-t">' + d.id + " · " + d.title + '</div><div class="qr-s">₦' + ngn(d.amount) + " frozen · opened " + d.opened + " by " + d.by + "</div></div>" +
      '<span class="sla ' + d.slaClass + '">' + d.sla + '</span><a class="btn btn-secondary btn-sm" href="dispute-detail.html">Open</a></div>').join("\n          "),
    gmxWd: MOCK.admin.withdrawals.filter((w) => w.state === "review").map((w) =>
      '<div class="queue-row"><span class="avatar sm ' + w.tone + '">' + w.worker.split(" ").map((x) => x[0]).join("") + '</span>' +
      '<div class="qr-main"><div class="qr-t">' + w.worker + '</div><div class="qr-s">' + w.bank + " · " + w.date + "</div></div>" +
      '<span class="amount" style="font-size:14px">' + ngn(w.amount) + "</span>" +
      '<button class="btn btn-primary btn-sm" data-act="approve" data-name="' + w.id + ' (' + ngn(w.amount) + ')" type="button">Approve</button> ' +
      '<button class="btn btn-danger btn-sm" data-act="reject" data-name="' + w.id + '" type="button">Hold</button></div>').join("\n          ")
  },
  "admin/users.html": {
    uBody: (function () {
      const st = { active: "st-green", suspended: "st-amber", banned: "st-red" };
      return MOCK.admin.users.map((u) =>
        "<tr><td><span class=\"row\" style=\"gap:10px\"><span class=\"avatar sm " + u.tone + "\">" + u.init + "</span>" +
        '<span><span class="cell-main">' + u.name + '</span><div class="cell-sub mono">' + u.phone + "</div></span></span></td>" +
        "<td>" + u.role + "</td><td>" + u.orders + "</td>" +
        '<td>' + (u.flags > 0 ? '<span class="flag">' + u.flags + " risk flags</span>" : '<span class="cell-sub">—</span>') + "</td>" +
        "<td>" + u.joined + '</td><td><span class="st ' + st[u.state] + '">' + u.stateLabel + "</span></td>" +
        '<td class="right nowrap"><button class="btn btn-secondary btn-sm" data-act="view" data-name="' + u.name + '" type="button">View</button> ' +
        (u.state === "active" ? '<button class="btn btn-danger btn-sm" data-act="suspend" data-name="' + u.name + '" type="button">Suspend</button>'
          : u.state === "suspended" ? '<button class="btn btn-primary btn-sm" data-act="unsuspend" data-name="' + u.name + '" type="button">Reinstate</button>' : "—") + "</td></tr>").join("\n          ");
    })()
  },
  "admin/orders.html": {
    oBody: (function () {
      const st = { delivered: "st-teal", in_progress: "st-amber", settled: "st-green", completed: "st-green", disputed: "st-red", cancelled: "st-gray" };
      return MOCK.admin.orders.map((o) =>
        '<tr><td><span class="cell-main">' + o.title + '</span><div class="cell-sub">' + o.id + "</div></td>" +
        '<td class="cell-sub">' + o.client + " → " + o.worker + '</td><td class="num amount">' + ngn(o.amount) + "</td><td>" + o.date + "</td>" +
        '<td><span class="st ' + st[o.state] + '">' + o.stateLabel + "</span></td>" +
        '<td class="right nowrap"><a class="btn btn-secondary btn-sm" href="order-detail.html">Timeline</a> ' +
        (o.state === "delivered" || o.state === "in_progress" ? '<button class="btn btn-danger btn-sm" data-act="release" data-amount="' + o.amount + '" data-name="' + o.id + '" type="button">Force release</button>' : "—") + "</td></tr>").join("\n          ");
    })()
  },
  "admin/payments.html": {
    pBody: (function () {
      const st = { paid: "st-green", refunded: "st-gray", failed: "st-red" };
      return MOCK.admin.payments.map((p) =>
        '<tr><td class="cell-main">' + p.id + '</td><td class="cell-sub">' + p.order + "</td><td>" + p.client + "</td><td>" + p.method + "</td>" +
        '<td class="num amount">' + ngn(p.amount) + "</td><td>" + p.date + "</td>" +
        '<td><span class="st ' + (p.stateLabel.includes("frozen") ? "st-red" : st[p.state]) + '">' + p.stateLabel + "</span></td></tr>").join("\n          ");
    })()
  },
  "admin/withdrawals.html": {
    wqNote: I.flag + "<span><b>Fraud signals to check before approving:</b> account age &lt; 30 days, sudden 10× jump in order volume, withdrawal to a bank name that doesn't match the profile, or the account was involved in a recent dispute. Any one of these → hold and review the order history.</span>",
    wBody: MOCK.admin.withdrawals.filter((w) => w.state === "review").map((w) =>
      "<tr><td class=\"cell-main\">" + w.id + "</td>" +
      '<td><span class="row" style="gap:9px"><span class="avatar sm ' + w.tone + '">' + w.worker.split(" ").map((x) => x[0]).join("") + "</span>" + w.worker + "</span></td>" +
      '<td class="cell-sub">' + w.bank + '</td><td class="num amount">' + ngn(w.amount) + "</td><td>" + w.date + "</td>" +
      '<td class="cell-sub">' + w.stateLabel + "</td>" +
      '<td class="right nowrap"><button class="btn btn-primary btn-sm" data-act="approve" data-name="' + w.id + ' (' + ngn(w.amount) + ')" type="button">Approve &amp; pay</button> ' +
      '<button class="btn btn-danger btn-sm" data-act="reject" data-name="' + w.id + '" type="button">Hold</button></td></tr>').join("\n          "),
    wDone: MOCK.admin.withdrawals.filter((w) => w.state !== "review").map((w) =>
      "<tr><td class=\"cell-main\">" + w.id + "</td><td>" + w.worker + '</td><td class="cell-sub">' + w.bank + "</td>" +
      '<td class="num amount">' + ngn(w.amount) + "</td><td>" + w.date + '</td><td><span class="st ' + (w.state === "paid" ? "st-green" : "st-red") + '">' + w.stateLabel + "</span></td></tr>").join("\n          ")
  },
  "admin/disputes.html": {
    dBody: MOCK.admin.disputes.map((d) =>
      '<tr><td><span class="cell-main">' + d.id + '</span><div class="cell-sub">' + d.state + "</div></td>" +
      '<td class="cell-sub">' + d.order + '</td><td class="num amount">' + ngn(d.amount) + "</td><td>" + d.by + "</td>" +
      '<td class="cell-sub">' + d.reason + '</td><td><span class="sla ' + d.slaClass + '">' + d.sla + "</span></td>" +
      '<td class="right"><a class="btn btn-primary btn-sm" href="dispute-detail.html">Open case</a></td></tr>').join("\n          ")
  },
  "admin/reports.html": {
    rPolicy: I.scale + "<span><b>Escalation policy:</b> 1–2 reports → investigate. 3+ reports or any “scam/fraud” with evidence → flag the account. Confirmed violation #1 → recorded warning. #2 → 30-day suspension. #3 or fraud with funds at risk → ban + withdrawal freeze, then recovery process.</span>",
    rBody: MOCK.admin.reports.map((r) =>
      '<tr><td class="cell-main">' + r.id + "</td><td>" + r.target + '</td><td class="cell-sub">' + r.reason + "</td>" +
      "<td>" + r.reports + (r.reports >= 3 ? ' <span class="flag">threshold</span>' : "") + "</td><td>" + r.last + "</td>" +
      '<td><span class="st ' + (r.state === "Open" ? "st-amber" : "st-gray") + '">' + r.state + "</span></td>" +
      '<td class="right nowrap"><button class="btn btn-ghost btn-sm" data-act="dismiss" data-name="' + r.id + '" type="button">Dismiss</button> ' +
      '<button class="btn btn-secondary btn-sm" data-act="warn" data-name="' + r.target + '" type="button">Warn</button> ' +
      '<button class="btn btn-danger btn-sm" data-act="ban" data-name="' + r.target + '" type="button">Ban</button></td></tr>').join("\n          ")
  },
  "admin/verification.html": {
    vNote: I.shield + "<span><b>Copy rule:</b> when in doubt about a document, reject with reason “Documents unclear — please resubmit” rather than approving. Rejected applicants can reapply once after fixing the issue; repeated failures go to the reports queue.</span>",
    vBody: MOCK.admin.verificationQueue.map((v) =>
      "<tr><td><span class=\"row\" style=\"gap:9px\"><span class=\"avatar sm " + v.tone + "\">" + v.worker.split(" ").map((x) => x[0]).join("") + "</span>" +
      '<span><span class="cell-main">' + v.worker + "</span></span></span></td>" +
      '<td class="cell-sub">' + v.docs + '</td><td><span class="st st-green">' + v.fee + "</span></td><td>" + v.submitted + "</td>" +
      '<td><span class="sla">' + v.sla + "</span></td>" +
      '<td class="right nowrap">' + (v.sla.startsWith("Approved") ? '<span class="st st-green">Badge live</span>'
        : '<button class="btn btn-primary btn-sm" data-act="approve" data-name="' + v.worker + '" type="button">Approve</button> ' +
          '<button class="btn btn-danger btn-sm" data-act="reject" data-name="' + v.worker + '" type="button">Reject</button>') + "</td></tr>").join("\n          ")
  },
  "admin/categories.html": {
    catTree: (function () {
      const icons = { code: I.code, pen: I.pen, wrench: I.wrench, briefcase: I.briefcase, heart: I.heart };
      return MOCK.categories.map((c) =>
        '<div class="queue-card mb-2"><div class="qc-head"><h3><span class="row" style="gap:9px">' + icons[c.icon] + c.name + "</span></h3>" +
        '<span class="row" style="gap:10px"><span class="tag">' + c.workers + ' workers</span><span class="tag">' + c.mode + "</span></span></div>" +
        c.subs.map((s) =>
          '<div class="queue-row"><span class="cell-sub" style="width:220px;flex:none;font-weight:500;color:var(--ink-2)">' + s + "</span>" +
          '<span class="cell-sub" style="flex:1">slug: ' + s.toLowerCase().replace(/[^a-z0-9]+/g, "-") + "</span>" +
          '<span class="st st-green">Active</span>' +
          '<button class="btn btn-secondary btn-sm" data-toast="Sub-category ' + s + ' — edit form (production build)" type="button">Edit</button> ' +
          '<button class="btn btn-ghost btn-sm" data-act="archive" data-name="' + s + '" type="button">Archive</button></div>').join("") +
        "</div>").join("\n      ");
    })()
  },
  "admin/audit-log.html": {
    aBody: MOCK.admin.audit.map((a) =>
      '<tr><td class="cell-sub nowrap">' + a.time + '</td><td class="cell-sub">' + a.admin + "</td>" +
      '<td class="cell-main">' + a.action + '</td><td class="cell-sub">' + a.target + "</td>" +
      '<td class="cell-sub">' + a.reason + '</td><td class="cell-sub mono">' + a.ip + "</td></tr>").join("\n          ")
  },
  "admin/support.html": {
    sbNote: I.chat + "<span><b>Escalation rule:</b> any user mentioning police, courts, or media gets flagged red and routed to the super-admin within 1 hour. Never argue in the inbox — acknowledge, state the next step, and move the case.</span>"
  }
};

/* ---------- apply ---------- */
function fill(html, id, content) {
  const re = new RegExp('(<([a-z][a-z0-9]*)[^>]*?id="' + id + '"[^>]*>)\\s*(</\\2>)');
  return html.replace(re, (m, open, tag, close) => open + content + close);
}
function fillLoose(html, id, content) {
  const re = new RegExp('(<[a-z][a-z0-9]*[^>]*?id="' + id + '"[^>]*>)[\\s\\S]*?(</[a-z][a-z0-9]*>)');
  return html.replace(re, (m, open, close) => open + content + close);
}

const root = path.join(__dirname, "..");
const files = fs.readdirSync(root).filter((f) => f.endsWith(".html")).map((f) => path.join(root, f))
  .concat(fs.readdirSync(path.join(root, "admin")).filter((f) => f.endsWith(".html")).map((f) => path.join(root, "admin", f)));

let changed = 0;
for (const file of files) {
  let html = fs.readFileSync(file, "utf8");
  const name = path.relative(root, file).replace(/\\/g, "/");
  const active = (html.match(/data-active="([^"]+)"/) || [])[1] || "";

  if (html.includes('<header id="site-header"></header>')) {
    html = html.replace('<header id="site-header"></header>', publicHeader(active));
  }
  if (html.includes('<footer id="site-footer"></footer>')) {
    html = html.replace('<footer id="site-footer"></footer>', footer());
  }
  if (html.includes('<aside class="side" id="side"></aside>')) {
    const shell = (html.match(/data-shell="([^"]+)"/) || [])[1];
    html = html.replace('<aside class="side" id="side"></aside>', shell === "admin" ? adminSide(active) : shell === "client" ? clientSide(active) : workerSide(active));
  }
  if (html.includes('<header class="subbar" id="subbar"></header>')) {
    const shell = (html.match(/data-shell="([^"]+)"/) || [])[1];
    html = html.replace('<header class="subbar" id="subbar"></header>', subbar(shell || "worker"));
  }

  const page = CONTENT[name];
  if (page) {
    for (const [id, content] of Object.entries(page)) {
      if (id === "rcCount") html = fillLoose(html, id, content);
      else html = fill(html, id, content);
    }
  }
  fs.writeFileSync(file, html);
  changed++;
}
console.log("Staticized " + changed + " files.");
