/* Display mode: system (default), light, dark. Shared across pages via localStorage. */
(function () {
  "use strict";
  const KEY = "skilvi_theme";
  const ORDER = ["system", "light", "dark"];
  const ICO = {
    system: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>',
    light: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 3v2M12 19v2M5 12H3M21 12h-2M6.2 6.2 4.8 4.8M19.2 19.2l-1.4-1.4M6.2 17.8 4.8 19.2M19.2 4.8l-1.4 1.4"/></svg>',
    dark: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 14.5A8.5 8.5 0 1 1 9.5 3 7 7 0 0 0 21 14.5Z"/></svg>',
  };
  const LABEL = { system: "System display", light: "Light", dark: "Dark" };

  function pref() {
    try { return localStorage.getItem(KEY) || "system"; } catch (e) { return "system"; }
  }
  function resolved(p) {
    if (p === "dark") return "dark";
    if (p === "light") return "light";
    return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
  }
  function apply() {
    const p = pref();
    const t = resolved(p);
    document.documentElement.setAttribute("data-theme", t);
    document.documentElement.setAttribute("data-theme-pref", p);
    document.querySelectorAll("[data-theme-switch]").forEach((el) => {
      el.setAttribute("aria-label", "Display: " + LABEL[p]);
      el.title = "Display: " + LABEL[p] + " — click to change";
      if (el.classList.contains("theme-switch-line")) {
        el.innerHTML = ICO[p] + "<span>" + LABEL[p] + "</span>";
      } else {
        el.innerHTML = ICO[p];
      }
    });
  }
  function cycle() {
    const i = ORDER.indexOf(pref());
    try { localStorage.setItem(KEY, ORDER[(i + 1) % ORDER.length]); } catch (e) { /* private mode */ }
    apply();
  }
  function btn(line) {
    const b = document.createElement("button");
    b.type = "button";
    b.setAttribute("data-theme-switch", "1");
    b.className = line ? "theme-switch theme-switch-line" : "icon-btn theme-switch";
    return b;
  }
  function mount() {
    document.querySelectorAll(".topact, .lp-nav-right").forEach((el) => {
      if (!el.querySelector("[data-theme-switch]")) el.insertBefore(btn(false), el.firstChild);
    });
    document.querySelectorAll(".sb-actions").forEach((el) => {
      if (!el.querySelector("[data-theme-switch]")) {
        const first = el.firstChild;
        el.insertBefore(btn(false), first);
      }
    });
    document.querySelectorAll("#mobileNav, .lp-menu-in").forEach((el) => {
      if (!el.querySelector("[data-theme-switch]")) el.appendChild(btn(true));
    });
    document.querySelectorAll(".side-foot").forEach((el) => {
      if (!el.querySelector("[data-theme-switch]")) el.insertBefore(btn(false), el.firstChild);
    });
    apply();
  }

  document.addEventListener("click", (e) => {
    const b = e.target && e.target.closest && e.target.closest("[data-theme-switch]");
    if (!b) return;
    e.preventDefault();
    cycle();
  });
  try {
    window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", apply);
  } catch (e) { /* old Safari */ }
  window.addEventListener("storage", (e) => {
    if (e.key === KEY) apply();
  });

  window.SkTheme = { apply, cycle, mount, pref };
  apply();
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", mount);
  else mount();
})();
