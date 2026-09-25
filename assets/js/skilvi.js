/* ============================================================
   SKILVI — shared frontend behaviour (vanilla JS, no deps)
   Injects chrome (header/footer/sidebar), powers toasts,
   modals, tabs, and small page interactions.
   Future: these partials map 1:1 to PHP includes.
   ============================================================ */
(function () {
  "use strict";

  const $  = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const ngn = (n) => "₦" + Number(n).toLocaleString("en-NG");
  window.Sk = { $, $$, ngn, toast: toast };

  /* ---------------- Icons (24px stroke, 1.5-2 weight) ---------------- */
  const S = (p) => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + p + "</svg>";
  const I = {
    mark:    S('<path d="M12 3 5 6v6c0 4.4 3 7.4 7 9 4-1.6 7-4.6 7-9V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>'),
    search:  S('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>'),
    bell:    S('<path d="M6 9a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6"/><path d="M10 19a2 2 0 0 0 4 0"/>'),
    menu:    S('<path d="M4 7h16M4 12h16M4 17h16"/>'),
    x:       S('<path d="M6 6l12 12M18 6 6 18"/>'),
    check:   S('<path d="m5 12 5 5 9-9"/>'),
    checkc:  S('<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 5-5"/>'),
    shield:  S('<path d="M12 3 5 6v6c0 4.4 3 7.4 7 9 4-1.6 7-4.6 7-9V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>'),
    star:    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M12 2.6l2.9 5.9 6.5.95-4.7 4.6 1.1 6.5L12 17.5l-5.8 3.05 1.1-6.5-4.7-4.6 6.5-.95L12 2.6z"/></svg>',
    pin:     S('<path d="M12 21s-7-5.3-7-11a7 7 0 0 1 14 0c0 5.7-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>'),
    clock:   S('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'),
    chat:    S('<path d="M21 12a8 8 0 0 1-8 8H4l2-3a8 8 0 1 1 15-5Z"/>'),
    wallet:  S('<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/>'),
    bank:    S('<path d="M3 9.5 12 4l9 5.5"/><path d="M5 10v8m4.5-8v8m5-8v8M19 10v8M3 20h18"/>'),
    arrow:   S('<path d="M5 12h14m-6-6 6 6-6 6"/>'),
    plus:    S('<path d="M12 5v14M5 12h14"/>'),
    file:    S('<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/>'),
    image:   S('<rect x="4" y="4" width="16" height="16" rx="2"/><circle cx="9.5" paper="1" cy="9.5" r="1.5"/><path d="m5 18 5-5 4 4 2-2 3 3"/>'),
    user:    S('<circle cx="12" cy="8" r="4"/><path d="M5 20a7 7 0 0 1 14 0"/>'),
    users:   S('<circle cx="9" cy="8.5" r="3.5"/><path d="M3 19.5a6 6 0 0 1 12 0"/><path d="M16 5.5a3.5 3.5 0 0 1 0 6.6M17.5 14.5a6 6 0 0 1 3.5 5"/>'),
    grid:    S('<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>'),
    briefcase: S('<rect x="3" y="8" width="18" height="12" rx="2"/><path d="M9 8V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/>'),
    layers:  S('<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>'),
    jobs:    S('<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6M9 13h6M9 17h3"/>'),
    wallet2: S('<path d="M20 7H5a2 2 0 0 1 0-4h13v4"/><path d="M20 7a1.5 1.5 0 0 1 1.5 1.5v11A1.5 1.5 0 0 1 20 21H6a2 2 0 0 1-2-2V5"/><circle cx="16.5" cy="14" r="1"/>'),
    msg:     S('<path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H8l-4 4V6a1 1 0 0 1 1-1Z"/>'),
    scale:   S('<path d="M12 4v16m-7 0h14"/><path d="m5 7-3 6a3.5 3.5 0 0 0 6 0L5 7Zm14 0-3 6a3.5 3.5 0 0 0 6 0l-3-6Z"/><path d="M5 7h14"/>'),
    gear:    S('<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7 7 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.06-.4.1-.8.1-1.2Z"/>'),
    help:    S('<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.4 2.3c-.8.3-.9 1-.9 1.7"/><path d="M12 17h.01"/>'),
    code:    S('<path d="m8 8-4 4 4 4m8-8 4 4-4 4m-3-10-2 12"/>'),
    pen:     S('<path d="M4 20h4L19.5 8.5a2.1 2.1 0 0 0-3-3L5 17v3Z"/><path d="m13.5 6.5 3 3"/>'),
    wrench:  S('<path d="M14.7 6.3a4.5 4.5 0 0 0-6 5.6L3 17.6V21h3.4l5.7-5.7a4.5 4.5 0 0 0 5.6-6L14.5 12l-2.5-2.5 2.7-3.2Z"/>'),
    camera:  S('<rect x="3" y="7" width="18" height="13" rx="2"/><circle cx="12" cy="13" r="3.5"/><path d="M8.5 7 10 4h4l1.5 3"/>'),
    chart:   S('<path d="M4 20V10m5.5 10V4M15 20v-7m5.5 7V7"/>'),
    flag:    S('<path d="M5 21V4"/><path d="M5 4h12l-2.5 4L17 12H5"/>'),
    logout:  S('<path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4"/><path d="m15 8 4 4-4 4m4-4H9"/>'),
    card:    S('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>'),
    phone:   S('<path d="M6 3h3l1.5 5L8 9.5a12 12 0 0 0 6.5 6.5l1.5-2.5 5 1.5v3a2 2 0 0 1-2 2A16 16 0 0 1 4 5a2 2 0 0 1 2-2Z"/>'),
    mail:    S('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>'),
    eye:     S('<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>'),
    download: S('<path d="M12 4v11m0 0-4-4m4 4 4-4"/><path d="M4 19h16"/>'),
    home:    S('<path d="m4 11 8-7 8 7v8a2 2 0 0 1-2 2h-4v-6h-4v6H6a2 2 0 0 1-2-2v-8Z"/>'),
    zap:     S('<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/>'),
    truck:   S('<rect x="2" y="6" width="12" height="10" rx="1"/><path d="M14 10h4l3 3v3h-3"/><circle cx="7" cy="18.5" r="1.8"/><circle cx="17" cy="18.5" r="1.8"/>'),
    paint:   S('<path d="M12 3a9 9 0 1 0 0 18c1.4 0 2-1 2-2s-.7-1.6 0-2.4c.6-.7 1.5-.6 2.5-.6H19a3 3 0 0 0 3-3c0-5-4-10-10-10Z"/><circle cx="7.5" cy="10.5" r="1"/><circle cx="12" cy="7.5" r="1"/><circle cx="16.5" cy="10.5" r="1"/>'),
    bolt:    S('<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/>'),
    book:    S('<path d="M4 5a2 2 0 0 1 2-2h14v18H6a2 2 0 0 1-2-2V5Z"/><path d="M20 17H6a2 2 0 0 0-2 2"/><path d="M9 7h7"/>'),
    heart:   S('<path d="M12 20.5S4 15 4 9.5A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 8 2.5c0 5.5-8 11-8 11Z"/>'),
    doc:     S('<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/>')
  };
  window.SkIcon = (name, cls) => (I[name] || I.file).replace("<svg ", '<svg class="' + (cls || "ico") + '" ');

  /* ---------------- Brand & chrome config ---------------- */
  const brand = (href) =>
    '<a class="brand" href="' + href + '"><img class="brand-logo" src="assets/img/skilvi-logo-word.png" alt="Skilvi"></a>';

  const PUBLIC_NAV = [
    { label: "Find Talent", href: "search.html", key: "search" },
    { label: "Categories", href: "category.html", key: "category" },
    { label: "Find Work", href: "jobs.html", key: "jobs" }
  ];

  const WORKER_NAV = [
    { group: "Work", items: [
      { label: "Overview", href: "worker-dashboard.html", icon: "grid", key: "overview" },
      { label: "My work", href: "worker-orders.html", icon: "briefcase", key: "orders" },
      { label: "Services", href: "worker-services.html", icon: "layers", key: "services" },
      { label: "Jobs & proposals", href: "worker-jobs.html", icon: "jobs", key: "jobs" }
    ]},
    { group: "Money", items: [
      { label: "Wallet", href: "worker-wallet.html", icon: "wallet2", key: "wallet" },
      { label: "Withdrawals", href: "worker-wallet.html?tab=withdrawals", icon: "download", key: "wallet" },
      { label: "Verification", href: "verification.html", icon: "shield", key: "verification" },
      { label: "Promotion", href: "promotion.html", icon: "zap", key: "promotion" }
    ]},
    { group: "Account", items: [
      { label: "Messages", href: "messages.html", icon: "msg", key: "messages" },
      { label: "Disputes", href: "disputes.html", icon: "scale", key: "disputes" },
      { label: "Settings", href: "account-settings.html", icon: "gear", key: "settings" },
      { label: "Sign out", href: "/logout.html", icon: "logout", key: "logout" }
    ]}
  ];

  const CLIENT_NAV = [
    { group: "Marketplace", items: [
      { label: "Overview", href: "client-dashboard.html", icon: "grid", key: "overview" },
      { label: "My orders", href: "client-dashboard.html?tab=orders", icon: "briefcase", key: "orders" },
      { label: "My jobs", href: "client-dashboard.html?tab=jobs", icon: "jobs", key: "jobs" },
      { label: "Saved workers", href: "saved.html", icon: "heart", key: "saved" },
      { label: "Payments", href: "client-dashboard.html?tab=payments", icon: "card", key: "payments" }
    ]},
    { group: "Account", items: [
      { label: "Messages", href: "messages.html", icon: "msg", key: "messages" },
      { label: "Disputes", href: "disputes.html", icon: "scale", key: "disputes" },
      { label: "Settings", href: "account-settings.html", icon: "gear", key: "settings" },
      { label: "Sign out", href: "/logout.html", icon: "logout", key: "logout" }
    ]}
  ];

  const ADMIN_NAV = [
    { group: "Operations", items: [
      { label: "Dashboard", href: "index.html", icon: "grid", key: "dashboard" },
      { label: "Orders", href: "orders.html", icon: "briefcase", key: "orders" },
      { label: "Payments", href: "payments.html", icon: "card", key: "payments" },
      { label: "Withdrawals", href: "withdrawals.html", icon: "wallet2", key: "withdrawals" },
      { label: "Promotions", href: "promotions.html", icon: "zap", key: "promotions" }
    ]},
    { group: "Trust & safety", items: [
      { label: "Disputes", href: "disputes.html", icon: "scale", key: "disputes" },
      { label: "Reports", href: "reports.html", icon: "flag", key: "reports" },
      { label: "Verification", href: "verification.html", icon: "shield", key: "verification" }
    ]},
    { group: "Platform", items: [
      { label: "Users", href: "users.html", icon: "users", key: "users" },
      { label: "Categories", href: "categories.html", icon: "layers", key: "categories" },
      { label: "Support inbox", href: "support.html", icon: "help", key: "support" }
    ]},
    { group: "System", items: [
      { label: "Audit log", href: "audit-log.html", icon: "doc", key: "audit" },
      { label: "Settings", href: "settings.html", icon: "gear", key: "settings" },
      { label: "Sign out", href: "/logout.html", icon: "logout", key: "logout" }
    ]}
  ];

  /* ---------------- Renderers ---------------- */
  function renderPublicHeader(active) {
    const nav = PUBLIC_NAV.map((n) =>
      '<a href="' + n.href + '"' + (n.key === active ? ' class="active"' : "") + ">" + n.label + "</a>").join("");
    const el = $("#site-header");
    if (!el) return;
    el.className = "lp-nav";
    el.innerHTML =
      '<div class="lp-wrap lp-nav-in">' +
      '<a class="lp-logo" href="index.html" aria-label="Skilvi home"><img src="assets/img/skilvi-logo-word.png" alt="Skilvi"></a>' +
      '<nav class="lp-nav-mid" aria-label="Primary">' + nav + "</nav>" +
      '<div class="lp-nav-right">' +
      '<a class="lp-signin" href="login.html">Sign In</a>' +
      '<a class="lp-btn lp-btn-solid lp-btn-sm" href="login.html#regForm">Sign Up</a>' +
      "</div>" +
      '<label class="lp-burger" for="lpMenu" aria-label="Menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></label>' +
      "</div>" +
      '<input type="checkbox" id="lpMenu" class="lp-menu-check">' +
      '<div class="lp-menu"><div class="lp-menu-in">' + nav +
      '<a href="login.html">Sign In</a><a class="lp-btn lp-btn-solid lp-btn-sm" href="login.html#regForm">Sign Up — it\'s free</a></div></div>';
  }

  function renderFooter() {
    const el = $("#site-footer");
    if (!el) return;
    el.className = "footer";
    el.innerHTML =
      '<div class="footer-in"><div class="footer-grid">' +
      '<div><div class="f-brand"><img class="brand-logo" src="assets/img/skilvi-logo-word.png" alt="Skilvi"></div>' +
      '<p class="f-desc">Discover. Learn. Earn. A marketplace built for Nigeria — digital skills and hands-on trades, paid securely in Naira.</p></div>' +
      "<div><h4>For clients</h4><a href=\"search.html\">Browse workers</a><a href=\"jobs.html\">Post a job</a><a href=\"help.html#faq-escrow\">How escrow works</a><a href=\"help.html#faq-fees\">Fees & pricing</a></div>" +
      "<div><h4>For workers</h4><a href=\"login.html#regForm\">Create a free profile</a><a href=\"verification.html\">Get verified</a><a href=\"help.html#faq-withdrawals\">Withdraw your money</a><a href=\"promotion.html\">Promote your profile</a></div>" +
      "<div><h4>Company</h4><a href=\"about.html\">About Skilvi</a><a href=\"help.html\">Help center</a><a href=\"terms.html\">Terms of service</a><a href=\"privacy.html\">Privacy policy</a><a href=\"help.html\">Contact support</a><a href=\"about.html\">About Skilvi</a></div>" +
      "</div>" +
      '<div class="footer-bottom"><span>© 2026 Skilvi Technologies Ltd. All rights reserved.</span>' +
      "<span>Built in Nigeria · Payments held securely in escrow</span></div></div>";
  }

  function navHtml(items) {
    return items.map((g) =>
      '<div class="side-label">' + g.group + "</div>" +
      g.items.map((it) =>
        '<a class="side-item' + (it.key === ACTIVE ? " active" : "") + '" href="' + it.href + '">' +
        I[it.icon] + "<span>" + it.label + "</span>" +
        (it.badge ? '<span class="badge-n">' + it.badge + "</span>" : "") +
        "</a>").join("")
    ).join("");
  }

  function renderShell(role) {
    const cfg = role === "admin"
      ? { nav: ADMIN_NAV, user: { init: "AO", tone: "a1", name: "Admin", sub: "Super admin" }, foot: "back" }
      : role === "worker"
        ? { nav: WORKER_NAV, user: { init: "CO", tone: "a1", name: "Chinedu Okafor", sub: "Worker · Verified" } }
        : { nav: CLIENT_NAV, user: { init: "AB", tone: "a2", name: "Aisha Bello", sub: "Client · Adaeze Boutique" } };

    const side = $("#side");
    if (side) {
      side.innerHTML =
        '<div class="side-brand">' + brand(cfg.foot === "back" ? "../index.html" : (role === "worker" ? "worker-dashboard.html" : "client-dashboard.html")) +
        (role === "admin" ? '<span class="admin-tag">Admin</span>' : "") + "</div>" +
        navHtml(cfg.nav) +
        '<div class="side-foot"><div class="side-user"><span class="avatar sm ' + cfg.user.tone + '">' + cfg.user.init + "</span>" +
        "<div><div class=\"su-name\">" + cfg.user.name + '</div><div class="su-role">' + cfg.user.sub + "</div></div></div></div>";
    }
    const sub = $("#subbar");
    if (sub) {
      sub.innerHTML =
        '<label class="icon-btn side-toggle" for="sideCheck" aria-label="Menu">' + I.menu + "</label>" +
        (role === "admin" ? '<span class="sb-crumb">Admin console</span>' : "") +
        '<div class="sb-actions">' +
        '<a class="link-muted" href="' + (role === "admin" ? "../index.html" : "index.html") + '">View marketplace</a>' +
        '<a class="icon-btn" href="notifications.html" aria-label="Notifications">' + I.bell + '<span class="dot"></span></a>' +
        '<a class="sb-user" href="account-settings.html"><span class="avatar sm ' + cfg.user.tone + '">' + cfg.user.init + "</span>" +
        '<span class="su-name">' + cfg.user.name.split(" ")[0] + "</span></a>" +
        '<a class="btn btn-ghost btn-sm" href="/logout.html">Sign out</a>' +
        "</div>";
      $$("[data-toast]", sub).forEach((b) => b.addEventListener("click", () => toast(b.getAttribute("data-toast"))));
    }
  }

  /* ---------------- Components ---------------- */
  function toast(msg, type) {
    let wrap = $(".toast-wrap");
    if (!wrap) {
      wrap = document.createElement("div");
      wrap.className = "toast-wrap";
      document.body.appendChild(wrap);
    }
    const t = document.createElement("div");
    t.className = "toast" + (type ? " " + type : "");
    t.innerHTML = (type === "error" ? I.flag : type === "success" ? I.checkc : I.bell) + "<span></span>";
    t.querySelector("span").textContent = msg;
    wrap.appendChild(t);
    setTimeout(() => { t.style.opacity = "0"; t.style.transition = "opacity .3s"; setTimeout(() => t.remove(), 320); }, 3400);
  }

  function initTabs(root) {
    const tabs = $$("[data-tab]", root);
    if (!tabs.length) return;
    tabs.forEach((tab) => tab.addEventListener("click", () => {
      const group = tab.closest("[data-tabs]") || document;
      $$("[data-tab]", group).forEach((t) => t.classList.remove("active"));
      tab.classList.add("active");
      const target = tab.getAttribute("data-tab");
      $$("[data-panel]", group).forEach((p) =>
        p.classList.toggle("active", p.getAttribute("data-panel") === target));
    }));
  }

  function initModals() {
    $$("[data-modal-open]").forEach((btn) =>
      btn.addEventListener("click", () => {
        const m = $("#" + btn.getAttribute("data-modal-open"));
        if (m) m.classList.add("open");
      }));
    $$(".modal-backdrop").forEach((m) =>
      m.addEventListener("click", (e) => {
        if (e.target === m || e.target.closest("[data-modal-close]")) m.classList.remove("open");
      }));
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") $$(".modal-backdrop.open").forEach((m) => m.classList.remove("open"));
    });
  }

  function starsHtml(rating) {
    let out = '<span class="stars">';
    for (let i = 1; i <= 5; i++)
      out += '<span class="' + (i <= Math.round(rating) ? "on" : "off") + '">' + I.star + "</span>";
    return out + "</span>";
  }
  window.SkStars = starsHtml;
  window.SkIconSvg = I;

  function moneyInputs() {
    $$(".money-input").forEach((inp) => {
      inp.addEventListener("input", () => {
        const v = inp.value.replace(/[^0-9]/g, "");
        inp.value = v ? Number(v).toLocaleString("en-NG") : "";
      });
    });
  }

  /* ---------------- Page inits ---------------- */
  function initByPage() {
    const page = document.body.getAttribute("data-page");
    if (window.SkApi) return;

    /* Search: render + filter mock workers */
    if (page === "search" && window.MOCK) {
      const list = $("#results");
      if (list) {
        const params = new URLSearchParams(location.search);
        const q = (params.get("q") || "").toLowerCase();
        if (q) $("#searchTerm").textContent = "“" + params.get("q") + "”";
        let rows = MOCK.workers.filter((w) =>
          !q || (w.name + " " + w.headline + " " + w.skill + " " + w.city + " " + w.state).toLowerCase().includes(q));
        list.innerHTML = rows.map(resultItemHtml).join("") ||
          '<div class="card"><div class="empty"><span class="e-ico">' + I.search + "</span>" +
          "<h3>No matches</h3><p>Try different keywords, remove some filters, or <a href=\"post-job.html\">post a job</a> instead.</p></div></div>";
        $("#rcCount").textContent = rows.length;
      }
      /* filter interactions */
      $$("#filters input, #filters select").forEach((el) =>
        el.addEventListener("change", () => {
          const f = {
            mode: $$("#filters input[name=mode]:checked")?.[0]?.value || "",
            state: $("#fState") ? $("#fState").value : "",
            verified: $("#fVerified") ? $("#fVerified").checked : false
          };
          let rows = MOCK.workers.filter((w) =>
            (!q || (w.name + " " + w.headline + " " + w.skill + " " + w.city + " " + w.state).toLowerCase().includes(q)) &&
            (!f.mode || w.mode === f.mode) &&
            (!f.state || w.state === f.state) &&
            (!f.verified || w.verified));
          rows.sort((a, b) => (b.promoted ? 1 : 0) - (a.promoted ? 1 : 0) || b.rating - a.rating);
          const listEl = $("#results");
          listEl.innerHTML = rows.map(resultItemHtml).join("") ||
            '<div class="card"><div class="empty"><span class="e-ico">' + I.search + "</span>" +
            "<h3>No matches</h3><p>Try removing a filter or two.</p></div></div>";
          $("#rcCount").textContent = rows.length;
        }));
      /* chips */
      $$("#chips .chip").forEach((c) => c.addEventListener("click", () => {
        $$("#chips .chip").forEach((x) => x.classList.remove("active"));
        c.classList.add("active");
        const inp = $("#qInput"); if (inp) inp.value = c.textContent;
        location.search = "?q=" + encodeURIComponent(c.textContent);
      }));
    }

    /* Service detail: package selection */
    if (page === "service") {
      let chosen = { name: "Standard", price: 85000, days: 10 };
      const sumP = $("#sumPrice"), sumM = $("#sumMeta");
      const paint = () => {
        if (sumP) { sumP.textContent = ngn(chosen.price); sumM.textContent = "Delivery in " + chosen.days + " days · 2 revisions included"; }
      };
      $$(".pkg-card[data-pkg]").forEach((card) => card.addEventListener("click", () => {
        $$(".pkg-card").forEach((c) => c.classList.remove("selected"));
        card.classList.add("selected");
        chosen = JSON.parse(card.getAttribute("data-pkg"));
        paint();
      }));
      paint();
      $$(".pkg-custom .btn").forEach((b) => b.addEventListener("click", () =>
        toast("Custom request sent — the worker will reply with a quote.", "success")));
    }

    /* Order detail: demo state switch */
    if (page === "order") {
      const sel = $("#stateDemo");
      const map = {
        paid: ["st-royal", "Paid · in escrow"],
        in_progress: ["st-amber", "In progress"],
        delivered: ["st-teal", "Delivered · awaiting your approval"],
        completed: ["st-green", "Completed"],
        disputed: ["st-red", "In dispute · funds frozen"]
      };
      const applyState = (st) => {
        $$(".order-state-block").forEach((b) => b.style.display = b.getAttribute("data-state") === st ? "" : "none");
        const chip = $("#orderStateChip");
        chip.className = "st " + map[st][0];
        chip.textContent = map[st][1];
      };
      if (sel) {
        sel.addEventListener("change", () => applyState(sel.value));
        const preset = new URLSearchParams(location.search).get("state");
        if (preset && map[preset]) { sel.value = preset; applyState(preset); }
      }
      $$("#orderActions .btn").forEach((b) =>
        b.addEventListener("click", () => {
          const m = b.getAttribute("data-msg");
          if (m) { toast(m, "success"); b.disabled = true; }
        }));
    }

    /* Messages: send + soft warning + auto reply */
    if (page === "messages") {
      const body = $("#chatBody"), input = $("#chatInput");
      const warn = $("#chatWarn");
      const replyLines = [
        "Morning! I can start on Monday morning.",
        "Yes — the quote includes all materials for the tiling.",
        "I've attached the before/after photos of my last job.",
        "Sounds good. Once the order is paid, we can lock in the schedule."
      ];
      let ri = 0;
      if (input) {
        input.addEventListener("input", () => {
          warn.classList.toggle("show", /(\+?234[0-9 -]{6,})|(\b0[0-9]{10,11}\b)/.test(input.value));
        });
        input.addEventListener("keydown", (e) => {
          if (e.key === "Enter" && input.value.trim()) {
            addBubble(input.value.trim(), true);
            input.value = "";
            warn.classList.remove("show");
            setTimeout(() => addBubble(replyLines[ri++ % replyLines.length], false), 1100);
          }
        });
        $("#chatSend").addEventListener("click", () => {
          if (input.value.trim()) {
            addBubble(input.value.trim(), true);
            input.value = "";
            warn.classList.remove("show");
            setTimeout(() => addBubble(replyLines[ri++ % replyLines.length], false), 1100);
          }
        });
      }
      function addBubble(text, me) {
        const row = document.createElement("div");
        row.className = "bubble-row" + (me ? " me" : "");
        row.innerHTML = '<div class="bubble ' + (me ? "me" : "them") + '"></div>';
        row.querySelector(".bubble").textContent = text;
        const t = document.createElement("span");
        t.className = "b-time";
        t.textContent = new Date().toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
        row.querySelector(".bubble").appendChild(t);
        body.appendChild(row);
        body.scrollTop = body.scrollHeight;
      }
    }

    /* Wallet: withdraw form */
    if (page === "wallet") {
      const form = $("#withdrawForm");
      if (form) form.addEventListener("submit", (e) => {
        e.preventDefault();
        const amt = $("#wdAmount").value.replace(/[^0-9]/g, "");
        if (!amt || Number(amt) < 5000) {
          toast("Minimum withdrawal is ₦5,000.", "error");
          return;
        }
        toast("Withdrawal of " + ngn(Number(amt)) + " requested. We'll pay within 1 business day.", "success");
        form.reset();
      });
    }

    /* Service form: dynamic rows */
    if (page === "service-form") {
      const addPkg = $("#addPkg"), addQ = $("#addQ");
      if (addPkg) addPkg.addEventListener("click", () => {
        const wrap = $("#pkgRows");
        if (wrap.children.length >= 3) { toast("Maximum 3 packages per service.", "error"); return; }
        wrap.insertAdjacentHTML("beforeend",
          '<div class="pkg-row card card-pad mb-2"><div class="row spread mb-1"><span class="bold small" style="color:var(--ink-3)">PACKAGE ' + (wrap.children.length + 1) +
          '</span><button type="button" class="link-muted rm-pkg">Remove</button></div><div class="form-row-2">' +
          '<div class="field"><label>Package name</label><input class="input" placeholder="e.g. Standard"></div>' +
          '<div class="field"><label>Price (₦)</label><input class="input money-input" placeholder="85,000"></div></div>' +
          '<div class="form-row-2 mt-2"><div class="field"><label>Delivery (days)</label><input class="input" type="number" min="1" placeholder="10"></div>' +
          '<div class="field"><label>Revisions included</label><input class="input" type="number" min="0" max="10" placeholder="2"></div></div></div>');
        wireRm();
      });
      if (addQ) addQ.addEventListener("click", () => {
        const wrap = $("#qRows");
        wrap.insertAdjacentHTML("beforeend",
          '<div class="row mb-1" style="gap:8px"><input class="input" placeholder="Question for the client (e.g. How many pages?)">' +
          '<button type="button" class="icon-btn rm-q" style="flex:none">' + I.x + "</button></div>");
        wireRm();
      });
      function wireRm() {
        $$(".rm-pkg").forEach((b) => b.onclick = () => b.closest(".pkg-row").remove());
        $$(".rm-q").forEach((b) => b.onclick = () => b.closest(".row").remove());
      }
      wireRm();
      $("#serviceForm")?.addEventListener("submit", (e) => {
        e.preventDefault();
        toast("Service saved and live. It's now visible in search.", "success");
        setTimeout(() => location.href = "worker-services.html", 900);
      });
    }

    /* Login: tabs + OTP (login and register share the OTP step).
       When /js/api.js is present the PHP backend owns this page — do not fake-verify. */
    if (page === "login" && window.SkApi) {
      return;
    }
    if (page === "login") {
      const main = $("#authStepMain"), otp = $("#authStepOtp");
      let regRole = "client";
      const activateMode = (m) => {
        $$(".auth-tab").forEach((t) => t.classList.toggle("active", t.getAttribute("data-mode") === m));
        $$("form.tab-panel").forEach((p) => p.classList.toggle("active", p.getAttribute("data-panel") === m));
      };
      $$(".auth-tab").forEach((t) => t.addEventListener("click", (e) => {
        e.preventDefault();
        const m = t.getAttribute("data-mode");
        activateMode(m);
        const hash = m === "register" ? "#regForm" : "#loginForm";
        if (location.hash !== hash) location.hash = hash;
      }));
      const modeFromUrl = () =>
        new URLSearchParams(location.search).get("mode") ||
        (location.hash === "#regForm" ? "register" : location.hash === "#loginForm" ? "login" : null);
      const mode = modeFromUrl();
      if (mode) activateMode(mode);
      window.addEventListener("hashchange", () => { const m = modeFromUrl(); if (m) activateMode(m); });
      const showOtp = (msg) => {
        if (main) main.style.display = "none";
        if (otp) otp.style.display = "";
        toast(msg);
        const first = $(".otp-box");
        if (first) first.focus();
      };
      $("#loginForm")?.addEventListener("submit", (e) => {
        e.preventDefault();
        showOtp("We sent a 6-digit code to your phone.");
      });
      $("#regForm")?.addEventListener("submit", (e) => {
        e.preventDefault();
        const sel = $$("#regForm input.pill-check:checked")[0];
        regRole = sel && sel.id === "joinAs_1_0" ? "worker" : "client";
        showOtp("Account created — we sent a 6-digit code to your phone.");
      });
      const boxes = $$(".otp-box");
      boxes.forEach((b, i) => b.addEventListener("input", () => {
        b.value = b.value.replace(/\D/g, "").slice(0, 1);
        if (b.value && i < boxes.length - 1) boxes[i + 1].focus();
      }));
      $("#otpForm")?.addEventListener("submit", (e) => {
        e.preventDefault();
        toast("Phone verified. Welcome to Skilvi!", "success");
        setTimeout(() => location.href = regRole === "worker" ? "worker-dashboard.html" : "client-dashboard.html", 1000);
      });
    }

    /* Admin: fake table actions — skipped when the PHP API owns the console. */
    if (page === "admin" && !window.SkApi) {
      $$("[data-act]").forEach((b) => b.addEventListener("click", () => {
        const act = b.getAttribute("data-act");
        const name = b.getAttribute("data-name") || "item";
        const msgs = {
          approve: ["Requested: " + name + " approved.", "success"],
          reject: ["Action recorded: " + name + " rejected (reason saved to audit log).", "success"],
          suspend: [null, null],
          unsuspend: ["User reinstated.", "success"],
          dismiss: ["Report dismissed — no action taken.", "success"],
          warn: ["Warning sent to " + name + ".", "success"],
          ban: ["User banned. Action logged to audit.", "success"],
          view: [null, null]
        };
        if (act === "suspend") { openReasonModal("Suspend user", name, () => toast(name + " suspended. They can see the reason after logging in.", "success")); return; }
        if (act === "view") { toast("Opening " + name + "…"); return; }
        if (act === "release") {
          const amt = b.getAttribute("data-amount") || "0";
          if (Number(amt) > 250000) {
            openReasonModal("Force release " + ngn(Number(amt)), name + " — above ₦250,000 threshold",
              () => toast("Release queued. A second admin's approval is required.", "success"), true);
          } else {
            openReasonModal("Force release " + ngn(Number(amt)), name, () => toast("Escrow released to worker wallet.", "success"));
          }
          return;
        }
        toast(msgs[act][0] || "Action recorded.", msgs[act][1] || "success");
      }));
    }

    /* Post job */
    if (page === "post-job") {
      $("#postJobForm")?.addEventListener("submit", (e) => {
        e.preventDefault();
        toast("Job published. Workers in your category will be notified.", "success");
        setTimeout(() => location.href = "jobs.html", 900);
      });
    }

    /* Disputes: open form */
    if (page === "disputes") {
      $("#disputeForm")?.addEventListener("submit", (e) => {
        e.preventDefault();
        toast("Dispute opened. Escrow funds are frozen while Skilvi reviews.", "success");
        e.target.reset();
      });
    }

    /* Settings: save buttons */
    if (page === "settings") {
      $$("[data-save]").forEach((b) => b.addEventListener("click", () =>
        toast("Saved.", "success")));
      $$("[data-danger]").forEach((b) => b.addEventListener("click", () =>
        openReasonModal("Deactivate account", "This will hide your profile and block new orders. You can reactivate anytime.", () => toast("Account deactivated.", "success"))));
    }

    /* Checkout: pay → processing → receipt page */
    if (page === "checkout") {
      const pay = $("#payBtn");
      if (pay) pay.addEventListener("click", () => {
        pay.disabled = true;
        pay.textContent = "Contacting your bank…";
        setTimeout(() => { location.href = "payment-success.html"; }, 1400);
      });
    }

    /* Job detail: apply + award */
    if (page === "job-detail") {
      $("#applyForm")?.addEventListener("submit", (e) => {
        e.preventDefault();
        toast("Proposal submitted. You'll be notified when the client responds.", "success");
        e.target.reset();
      });
      $$(".prop-award").forEach((b) => b.addEventListener("click", () =>
        openReasonModal("Award job", b.getAttribute("data-name"), () => {
          toast("Order created — OR-1043. Taking the client to secure checkout…", "success");
          setTimeout(() => { location.href = "checkout.html"; }, 1800);
        })));
      $$(".prop-shortlist").forEach((b) => b.addEventListener("click", () => {
        b.textContent = "Shortlisted ✓";
        b.disabled = true;
        toast("Shortlisted.", "success");
      }));
    }
  }

  /* Reason modal (reusable) */
  let reasonModal;
  function ensureReasonModal() {
    if (reasonModal) return reasonModal;
    const m = document.createElement("div");
    m.className = "modal-backdrop";
    m.innerHTML =
      '<div class="modal"><div class="modal-head"><h3></h3>' +
      '<button class="icon-btn" data-modal-close aria-label="Close">' + I.x + "</button></div>" +
      '<div class="modal-body"><div class="field"><label>Reason</label>' +
      '<textarea class="textarea" placeholder="Add a note (recorded in the audit log)"></textarea></div></div>' +
      '<div class="modal-foot"><button class="btn btn-secondary" data-modal-close>Cancel</button>' +
      '<button class="btn btn-primary" id="rmConfirm">Confirm</button></div></div>';
    document.body.appendChild(m);
    m.addEventListener("click", (e) => { if (e.target === m || e.target.closest("[data-modal-close]")) m.classList.remove("open"); });
    reasonModal = m;
    return m;
  }
  function openReasonModal(title, sub, onConfirm, requireSecond) {
    const m = ensureReasonModal();
    m.querySelector("h3").textContent = title;
    m.querySelector(".modal-body").insertAdjacentHTML("afterbegin",
      '<p class="small muted mb-2">' + sub + (requireSecond ? " <b>(Second admin approval required.)</b>" : "") + "</p>");
    m.classList.add("open");
    const confirm = m.querySelector("#rmConfirm");
    confirm.textContent = requireSecond ? "Request approval" : "Confirm";
    confirm.onclick = () => { m.classList.remove("open"); onConfirm(); };
    m.querySelectorAll(".modal-body > p").forEach((p, i) => { if (i > 0) p.remove(); });
  }
  window.openReasonModal = openReasonModal;

  /* result card html (search) */
  function resultItemHtml(w) {
    const svc = MOCK.services.find((s) => s.workerId === w.id);
    return '<div class="result-item">' +
      (w.promoted ? '<span class="tag tag-promoted" style="grid-column:1/-1;justify-self:start">Promoted</span>' : "") +
      '<span class="avatar ' + w.tone + '">' + w.init + "</span>" +
      '<div class="ri-main"><div class="ri-top"><span class="ri-name"><a href="worker-profile.html">' + w.name + "</a></span>" +
      (w.verified ? '<span class="badge-verified">' + I.shield + "Verified</span>" : "") +
      (w.promoted ? '<span class="tag tag-promoted">Promoted</span>' : "") + "</div>" +
      '<div class="ri-skill">' + w.headline + "</div>" +
      '<div class="ri-meta"><span>' + I.pin + w.city + ", " + w.state + "</span>" +
      "<span>" + SkStars(w.rating) + " <b style='color:var(--ink)'>" + w.rating + "</b> (" + w.reviews + ")</span>" +
      "<span>" + I.clock + "Replies " + w.resp + "</span>" +
      "<span>" + I.checkc + w.jobs + " orders completed</span></div>" +
      (svc ? '<div class="ri-svc"><div class="svc-line"><b><a href="service-detail.html">' + svc.title + "</a></b>" +
      '<span>from <span class="amount">' + ngn(svc.from) + "</span></span></div></div>" : "") +
      "</div>" +
      '<div class="ri-side"><span class="rating-line">' + SkStars(w.rating) + " <b>" + w.rating + "</b></span>" +
      (svc ? '<a class="btn btn-secondary btn-sm" href="service-detail.html">View service</a>' : "") +
      '<a class="btn btn-primary btn-sm" href="worker-profile.html">View profile</a></div></div>';
  }

  /* ---------------- Boot ---------------- */
  let ACTIVE = document.body.getAttribute("data-active") || "";

  document.addEventListener("DOMContentLoaded", () => {
    /* chrome is now static HTML in the pages (see tools/staticize.js);
       these fallbacks only run if a page still ships with placeholders */
    if (document.body.hasAttribute("data-chrome")) renderPublicHeader(ACTIVE);
    const footer = $("#site-footer");
    if (footer && document.body.hasAttribute("data-chrome")) renderFooter();
    if (document.body.hasAttribute("data-shell") && document.getElementById("side") && !document.querySelector("aside.side .side-item")) {
      renderShell(document.body.getAttribute("data-shell"));
    }

    /* mobile nav: the checkbox hack (label[for=navCheck]) opens/closes it
       purely in CSS — JS only closes the menu after a link is tapped */
    const navCheck = $("#navCheck");
    if (navCheck) $$("#mobileNav a").forEach((a) =>
      a.addEventListener("click", () => { navCheck.checked = false; }));

    /* shell sidebar (mobile drawer): close on backdrop or item tap */
    const sideCheck = $("#sideCheck");
    if (sideCheck) {
      $$(".side-backdrop").forEach((b) => b.addEventListener("click", () => { sideCheck.checked = false; }));
      $$("#side a").forEach((a) => a.addEventListener("click", () => { sideCheck.checked = false; }));
    }

    initTabs(document);
    initModals();
    moneyInputs();
    initByPage();

    /* public search forms (topbar + hero) */
    $$("form.topsearch, form.hero-search").forEach((f) => f.addEventListener("submit", (e) => {
      e.preventDefault();
      const inp = f.querySelector("input");
      const q = inp ? inp.value.trim() : "";
      location.href = "search.html" + (q ? "?q=" + encodeURIComponent(q) : "");
    }));
    /* radio-pill groups (login role picker, post-job options) */
    $$("[data-radio]").forEach((g) =>
      $$(".radio-pill", g).forEach((p) => p.addEventListener("click", () => {
        $$(".radio-pill", g).forEach((x) => x.classList.remove("active"));
        p.classList.add("active");
      })));
    /* Prototype-only toasts. Live PHP pages must not fake success. */
    if (!window.SkApi) {
      $$("form[data-toast]").forEach((f) =>
        f.addEventListener("submit", (e) => { e.preventDefault(); toast(f.getAttribute("data-toast"), "success"); }));
      $$("button[data-toast]").forEach((b) =>
        b.addEventListener("click", () => toast(b.getAttribute("data-toast"))));
    }
  });
})();
