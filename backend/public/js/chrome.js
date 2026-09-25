/* Session chrome: real name, role-correct sidebar, Sign out. */
(function () {
  "use strict";
  const api = window.SkApi && window.SkApi.api;
  if (!api) return;

  const ICO = {
    grid: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/></svg>',
    briefcase: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="12" rx="2"/><path d="M9 8V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/></svg>',
    jobs: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6M9 13h6M9 17h3"/></svg>',
    heart: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20.3S3.8 15 3.8 9.6a4.6 4.6 0 0 1 8.2-2.9 4.6 4.6 0 0 1 8.2 2.9c0 5.4-8.2 10.7-8.2 10.7Z"/></svg>',
    card: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>',
    layers: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/></svg>',
    wallet: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7H5a2 2 0 0 1 0-4h13v4"/><path d="M20 7a1.5 1.5 0 0 1 1.5 1.5v11A1.5 1.5 0 0 1 20 21H6a2 2 0 0 1-2-2V5"/><circle cx="16.5" cy="14" r="1"/></svg>',
    down: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v13"/><path d="m8 7 4-4 4 4"/><path d="M4 14v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/></svg>',
    shield: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v6c0 4.4 3 7.4 7 9 4-1.6 7-4.6 7-9V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg>',
    zap: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/></svg>',
    msg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H8l-4 4V6a1 1 0 0 1 1-1Z"/></svg>',
    scale: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m-7 0h14"/><path d="m5 7-3 6a3.5 3.5 0 0 0 6 0L5 7Zm14 0-3 6a3.5 3.5 0 0 0 6 0l-3-6Z"/><path d="M5 7h14"/></svg>',
    gear: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7 7 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.06-.4.1-.8.1-1.2Z"/></svg>',
    logout: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4"/><path d="m15 8 4 4-4 4m4-4H9"/></svg>',
  };

  function rolesOf(me) {
    const r = me && me.roles;
    if (Array.isArray(r)) return r;
    if (typeof r === "string") return r.split(",").map((s) => s.trim()).filter(Boolean);
    return [];
  }
  function hasRole(me, role) {
    return rolesOf(me).some((r) => String(r).toLowerCase() === role);
  }
  function isWorker(me) { return hasRole(me, "worker"); }
  function isClient(me) { return hasRole(me, "client"); }
  function isAdmin(me) { return hasRole(me, "admin"); }
  function isBoth(me) { return isWorker(me) && isClient(me); }

  function home(me) {
    if (isAdmin(me)) return "/admin/index.html";
    if (isClient(me) && !isWorker(me)) return "/client-dashboard.html";
    if (isWorker(me) && !isClient(me)) return "/worker-dashboard.html";
    if (isClient(me)) return "/client-dashboard.html";
    return "/worker-dashboard.html";
  }

  function roleLine(me) {
    const bits = [];
    if (isWorker(me)) bits.push("Worker");
    if (isClient(me)) bits.push("Client");
    if (!bits.length) bits.push("Account");
    if (me.verified) bits.push("Verified");
    return bits.join(" · ");
  }

  function file() {
    return (location.pathname.split("/").pop() || "").replace(/^\//, "") || "index.html";
  }

  const WORKER_ONLY = [
    "worker-dashboard.html", "worker-orders.html", "worker-services.html",
    "worker-service-form.html", "worker-jobs.html", "worker-wallet.html",
    "verification.html", "promotion.html",
  ];
  const CLIENT_ONLY = ["client-dashboard.html", "saved.html", "post-job.html"];
  const SHARED = [
    "account-settings.html", "messages.html", "disputes.html", "dispute-detail.html",
    "notifications.html", "order-detail.html", "review.html", "checkout.html",
    "payment-success.html",
  ];

  function item(href, ico, label, active) {
    return '<a class="side-item' + (active ? " active" : "") + '" href="' + href + '">' + ico + "<span>" + label + "</span></a>";
  }

  function tabHasToken() {
    if (window.SkApi && window.SkApi.tabToken) return !!window.SkApi.tabToken();
    try { return !!sessionStorage.getItem("skilvi_auth"); } catch (e) { return false; }
  }

  const SHELL_KEY = "skilvi_shell";
  function rememberShell(want) {
    try { sessionStorage.setItem(SHELL_KEY, want); } catch (e) { /* private mode */ }
  }
  function recalledShell() {
    try { return sessionStorage.getItem(SHELL_KEY) || ""; } catch (e) { return ""; }
  }

  function activeKey() {
    const f = file();
    const tab = new URLSearchParams(location.search).get("tab") || "";
    if (f === "client-dashboard.html") {
      if (tab === "orders" || tab === "jobs" || tab === "payments") return tab;
      return "overview";
    }
    if (f === "worker-wallet.html") return tab === "withdrawals" ? "withdrawals" : "wallet";
    if (f === "notifications.html") return "";
    return document.body.getAttribute("data-active") || "";
  }

  function workerSide(me, active) {
    return '<div class="side-brand"><a class="brand" href="worker-dashboard.html"><img class="brand-logo" src="assets/img/skilvi-logo-word.png" alt="Skilvi"></a></div>' +
      '<div class="side-label">Work</div>' +
      item("worker-dashboard.html", ICO.grid, "Overview", active === "overview") +
      item("worker-orders.html", ICO.briefcase, "My work", active === "orders") +
      item("worker-services.html", ICO.layers, "Services", active === "services") +
      item("worker-jobs.html", ICO.jobs, "Jobs & proposals", active === "jobs") +
      '<div class="side-label">Money</div>' +
      item("worker-wallet.html", ICO.wallet, "Wallet", active === "wallet") +
      item("worker-wallet.html?tab=withdrawals", ICO.down, "Withdrawals", active === "withdrawals") +
      item("verification.html", ICO.shield, "Verification", active === "verification") +
      item("promotion.html", ICO.zap, "Promotion", active === "promotion") +
      '<div class="side-label">Account</div>' +
      item("messages.html", ICO.msg, "Messages", active === "messages") +
      item("disputes.html", ICO.scale, "Disputes", active === "disputes") +
      item("account-settings.html", ICO.gear, "Settings", active === "settings") +
      item("/logout.html", ICO.logout, "Sign out", false) +
      foot(me, "a1", "worker");
  }

  function clientSide(me, active) {
    return '<div class="side-brand"><a class="brand" href="client-dashboard.html"><img class="brand-logo" src="assets/img/skilvi-logo-word.png" alt="Skilvi"></a></div>' +
      '<div class="side-label">Marketplace</div>' +
      item("client-dashboard.html", ICO.grid, "Overview", active === "overview") +
      item("client-dashboard.html?tab=orders", ICO.briefcase, "My orders", active === "orders") +
      item("client-dashboard.html?tab=jobs", ICO.jobs, "My jobs", active === "jobs") +
      item("saved.html", ICO.heart, "Saved workers", active === "saved") +
      item("client-dashboard.html?tab=payments", ICO.card, "Payments", active === "payments") +
      '<div class="side-label">Account</div>' +
      item("messages.html", ICO.msg, "Messages", active === "messages") +
      item("disputes.html", ICO.scale, "Disputes", active === "disputes") +
      item("account-settings.html", ICO.gear, "Settings", active === "settings") +
      item("/logout.html", ICO.logout, "Sign out", false) +
      foot(me, "a2", "client");
  }

  function foot(me, tone, shell) {
    const swap = isBoth(me)
      ? (shell === "client"
          ? item("worker-dashboard.html", ICO.layers, "Earn as a worker", false)
          : item("client-dashboard.html", ICO.heart, "Hire as a client", false))
      : "";
    return '<div class="side-foot">' + swap +
      '<div class="side-user"><span class="avatar sm ' + tone + '">' +
      (me.initials || "?") + '</span><div><div class="su-name">' + (me.full_name || "You") +
      '</div><div class="su-role">' + roleLine(me) + "</div></div></div></div>";
  }

  function gate(me) {
    const f = file();
    if (WORKER_ONLY.indexOf(f) !== -1 && !isWorker(me)) {
      location.replace(home(me));
      return true;
    }
    if (CLIENT_ONLY.indexOf(f) !== -1 && !isClient(me)) {
      location.replace(home(me));
      return true;
    }
    return false;
  }

  function applySide(me) {
    const side = document.querySelector("aside.side");
    if (!side) return;
    if ((document.body.getAttribute("data-shell") || "") === "admin") return;
    const f = file();
    if (WORKER_ONLY.indexOf(f) !== -1) rememberShell("worker");
    if (CLIENT_ONLY.indexOf(f) !== -1) rememberShell("client");
    const active = activeKey();
    document.body.setAttribute("data-active", active);
    let want = document.body.getAttribute("data-shell") || "";
    if (SHARED.indexOf(f) !== -1) {
      if (isWorker(me) && isClient(me)) {
        want = recalledShell() || "client";
      } else if (isWorker(me) && !isClient(me)) want = "worker";
      else if (isClient(me) && !isWorker(me)) want = "client";
      else if (want !== "worker" && want !== "client") want = isWorker(me) ? "worker" : "client";
    }
    if (want === "client") side.innerHTML = clientSide(me, active);
    else if (want === "worker") side.innerHTML = workerSide(me, active);
    if (window.SkTheme && window.SkTheme.mount) window.SkTheme.mount();
  }

  function markPublicNav() {
    const file = (location.pathname.split("/").pop() || "index.html").replace(/^\//, "") || "index.html";
    const map = {
      "search.html": "search.html",
      "worker-profile.html": "search.html",
      "service-detail.html": "search.html",
      "category.html": "category.html",
      "jobs.html": "jobs.html",
      "job-detail.html": "jobs.html",
      "post-job.html": "jobs.html",
      "login.html": "login.html",
      "forgot-password.html": "login.html",
    };
    const want = map[file] || "";
    document.querySelectorAll(".lp-nav-mid a, .lp-menu-in a").forEach((a) => {
      const href = ((a.getAttribute("href") || "").split("#")[0] || "").replace(/^\//, "");
      const on = want !== "" && href === want;
      a.classList.toggle("active", on);
    });
    document.querySelectorAll(".lp-signin").forEach((a) => {
      a.classList.toggle("active", want === "login.html");
    });
  }

  function setHeader(me) {
    const dash = home(me);
    const right = document.querySelector(".lp-nav-right") || document.querySelector(".topact");
    if (right) {
      Array.from(right.querySelectorAll("a")).forEach((a) => a.remove());
      const sign = document.createElement("a");
      sign.className = "lp-signin";
      sign.href = dash;
      sign.textContent = "Dashboard";
      const out = document.createElement("a");
      out.className = "lp-btn lp-btn-solid lp-btn-sm";
      out.href = "/logout.html";
      out.textContent = "Sign out";
      right.appendChild(sign);
      right.appendChild(out);
    }
    const mob = document.querySelector(".lp-menu-in") || document.querySelector("#mobileNav");
    if (mob) {
      Array.from(mob.querySelectorAll('a[href*="login"], a[href*="logout"], a.lp-btn')).forEach((a) => a.remove());
      if (!mob.querySelector('a[href="' + dash + '"]')) {
        const d = document.createElement("a");
        d.href = dash;
        d.textContent = "Dashboard";
        mob.appendChild(d);
      }
      if (!mob.querySelector('a[href="/logout.html"]')) {
        const o = document.createElement("a");
        o.className = "lp-btn lp-btn-solid lp-btn-sm";
        o.href = "/logout.html";
        o.textContent = "Sign out";
        mob.appendChild(o);
      }
    }
  }

  function paint(me) {
    const first = (me.full_name || "").split(" ")[0] || "there";
    document.querySelectorAll(".side-user .su-name").forEach((el) => {
      el.textContent = me.full_name || "You";
    });
    document.querySelectorAll(".sb-user .su-name").forEach((el) => {
      el.textContent = first;
    });
    document.querySelectorAll(".side-user .avatar, .sb-user .avatar").forEach((el) => {
      el.textContent = me.initials || "?";
    });
    document.querySelectorAll(".su-role").forEach((el) => {
      el.textContent = roleLine(me);
    });
    const welcome = document.querySelector(".ph-title");
    if (welcome) {
      const t = welcome.textContent || "";
      if (/Welcome back/i.test(t)) welcome.textContent = "Welcome back, " + first;
      else if (/^Hello\b/i.test(t)) welcome.textContent = "Hello, " + first;
    }
    const sub = document.querySelector(".ph-sub");
    if (sub && /Adaeze Boutique|Lagos, Nigeria|92% complete|Chinedu/.test(sub.textContent || "")) {
      sub.textContent = [me.city, me.state].filter(Boolean).join(", ") || "Your dashboard";
    }
  }

  async function run() {
    const onAdmin = (location.pathname || "").indexOf("/admin/") !== -1;
    let me = null;
    try {
      me = await api("/api/me");
    } catch (e) {
      me = null;
    }
    if (!me) {
      if (tabHasToken() && window.SkApi && window.SkApi.setTabToken) window.SkApi.setTabToken("");
      const f = file();
      if (onAdmin || WORKER_ONLY.indexOf(f) !== -1 || CLIENT_ONLY.indexOf(f) !== -1 || SHARED.indexOf(f) !== -1) {
        location.replace("/login.html?next=" + encodeURIComponent(location.pathname + location.search));
      }
      markPublicNav();
      if (window.SkTheme && window.SkTheme.mount) window.SkTheme.mount();
      return;
    }
    if (onAdmin) {
      if (!isAdmin(me)) {
        location.replace(home(me));
        return;
      }
      setHeader(me);
      paint(me);
      markPublicNav();
      if (window.SkTheme && window.SkTheme.mount) window.SkTheme.mount();
      return;
    }
    if (gate(me)) return;
    applySide(me);
    setHeader(me);
    paint(me);
    markPublicNav();
    if (window.SkTheme && window.SkTheme.mount) window.SkTheme.mount();
    paintBadges();
    bindDashTabs(me);
    window.__me = me;
    if (me.public_code) {
      document.querySelectorAll('a[href="worker-profile.html"], a[href="worker-profile.html?id="], a[href="/worker-profile.html"]').forEach((a) => {
        a.href = "worker-profile.html?id=" + encodeURIComponent(me.public_code);
      });
    }
  }

  function bindDashTabs(me) {
    const f = file();
    if (f !== "client-dashboard.html" && f !== "worker-wallet.html") return;
    document.querySelectorAll("[data-tabs] [data-tab]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const t = btn.getAttribute("data-tab") || "";
        const next = (t && t !== "overview") ? (location.pathname + "?tab=" + encodeURIComponent(t)) : location.pathname;
        history.replaceState({}, "", next);
        applySide(me);
        paint(me);
      });
    });
  }

  async function paintBadges() {
    try {
      const b = await api("/api/me/badges");
      const n = (b && b.notifications) || 0;
      document.querySelectorAll(".icon-btn .dot").forEach((el) => {
        el.style.display = n > 0 ? "" : "none";
      });
    } catch (e) { /* keep static */ }
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", run);
  else run();
})();
