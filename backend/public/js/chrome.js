/* Session chrome: real name, Sign out, public header when logged in. */
(function () {
  "use strict";
  const api = window.SkApi && window.SkApi.api;
  if (!api) return;

  function home(me) {
    const r = me.roles || [];
    if (r.indexOf("admin") !== -1) return "/admin/index.html";
    if (r.indexOf("client") !== -1) return "/client-dashboard.html";
    return "/worker-dashboard.html";
  }

  function roleLine(me) {
    const r = (me.roles || []).filter((x) => x !== "admin");
    if (!r.length) return me.verified ? "Verified" : "Account";
    return r.map((x) => x.charAt(0).toUpperCase() + x.slice(1)).join(" · ")
      + (me.verified ? " · Verified" : "");
  }

  function setHeader(me) {
    const dash = home(me);
    const topact = document.querySelector(".topact");
    if (topact) {
      topact.innerHTML =
        '<a class="btn btn-ghost" href="' + dash + '">Dashboard</a>' +
        '<a class="btn btn-primary" href="/logout.html">Sign out</a>';
    }
    const mob = document.querySelector("#mobileNav");
    if (mob) {
      Array.from(mob.querySelectorAll('a[href*="login"]')).forEach((a) => a.remove());
      const d = document.createElement("a");
      d.href = dash;
      d.textContent = "Dashboard";
      const o = document.createElement("a");
      o.href = "/logout.html";
      o.textContent = "Sign out";
      mob.appendChild(d);
      mob.appendChild(o);
    }
  }

  function paint(me) {
    document.querySelectorAll(".su-name").forEach((el) => {
      const firstOnly = el.closest(".sb-user") || (el.tagName === "SPAN" && el.classList.contains("su-name") && el.parentElement && el.parentElement.classList.contains("sb-user"));
      el.textContent = firstOnly ? (me.full_name || "").split(" ")[0] : me.full_name;
    });
    document.querySelectorAll(".side-user .avatar, .sb-user .avatar").forEach((el) => {
      el.textContent = me.initials || "?";
    });
    document.querySelectorAll(".su-role").forEach((el) => {
      el.textContent = roleLine(me);
    });
    const welcome = document.querySelector(".ph-title");
    if (welcome && /Welcome back|Hello,/i.test(welcome.textContent || "")) {
      const first = (me.full_name || "").split(" ")[0] || "there";
      if (/Welcome back/i.test(welcome.textContent)) welcome.textContent = "Welcome back, " + first;
    }
    const sub = document.querySelector(".ph-sub");
    if (sub && /Adaeze Boutique|Lagos, Nigeria/.test(sub.textContent || "")) {
      sub.textContent = [me.city, me.state].filter(Boolean).join(", ") || "Your dashboard";
    }
  }

  async function run() {
    let me = null;
    try {
      me = await api("/api/me");
    } catch (e) {
      me = null;
    }
    if (!me) return;
    setHeader(me);
    paint(me);
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", run);
  else run();
})();
