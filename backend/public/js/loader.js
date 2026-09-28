/* Full-screen video loader for real navigations. */
(function () {
  "use strict";
  var KEY = "skilvi_nav";
  /* Test only: extra wait after the overlay is up so you can see it. Set to 0 when done. */
  var TEST_NAV_MS = 2500;
  var pendingGo = false;

  function box() {
    return document.getElementById("skLoader");
  }
  function vid() {
    var b = box();
    return b ? b.querySelector("video") : null;
  }
  function isOn() {
    return document.documentElement.classList.contains("sk-loading");
  }
  function show() {
    try { sessionStorage.setItem(KEY, "1"); } catch (e) { /* private mode */ }
    document.documentElement.classList.add("sk-loading");
    var el = box();
    if (el) {
      el.setAttribute("aria-hidden", "false");
      void el.offsetWidth;
    }
    var v = vid();
    if (v) {
      try { v.currentTime = 0; } catch (e) { /* ignore */ }
      var p = v.play();
      if (p && p.catch) p.catch(function () {});
    }
  }
  function hide() {
    if (pendingGo) return;
    try { sessionStorage.removeItem(KEY); } catch (e) { /* private mode */ }
    document.documentElement.classList.remove("sk-loading");
    var el = box();
    if (el) el.setAttribute("aria-hidden", "true");
    var v = vid();
    if (v) v.pause();
  }
  function reveal() {
    if (!isOn()) return;
    hide();
  }
  function internalLink(a) {
    if (!a || a.tagName !== "A") return false;
    if (a.hasAttribute("download")) return false;
    if (a.getAttribute("data-no-loader") != null) return false;
    var t = (a.getAttribute("target") || "").toLowerCase();
    if (t && t !== "_self") return false;
    var raw = a.getAttribute("href");
    if (!raw || raw === "#" || /^javascript:/i.test(raw) || /^mailto:/i.test(raw) || /^tel:/i.test(raw)) return false;
    var u;
    try { u = new URL(a.href, location.href); } catch (e) { return false; }
    if (u.origin !== location.origin) return false;
    if (u.pathname === location.pathname && u.search === location.search) return false;
    return true;
  }
  function linkFromEvent(e) {
    if (!e || e.defaultPrevented) return null;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return null;
    var a = e.target && e.target.closest && e.target.closest("a[href]");
    return internalLink(a) ? a : null;
  }
  function go(href) {
    pendingGo = true;
    show();
    window.setTimeout(function () {
      location.href = href;
    }, TEST_NAV_MS);
  }

  /* Cover immediately on press. */
  document.addEventListener("pointerdown", function (e) {
    if (e.button !== 0) return;
    if (linkFromEvent(e)) show();
  }, true);
  document.addEventListener("click", function (e) {
    if (e.button !== 0) return;
    var a = linkFromEvent(e);
    if (!a) return;
    e.preventDefault();
    go(a.href);
  }, true);
  document.addEventListener("keydown", function (e) {
    if (e.key !== "Enter") return;
    if (e.metaKey || e.ctrlKey || e.altKey) return;
    var a = document.activeElement;
    if (!internalLink(a)) return;
    e.preventDefault();
    go(a.href);
  }, true);

  window.addEventListener("pageshow", function (e) {
    if (e.persisted) hide();
  });
  /* Hide as soon as this document can display — not after leftover media. */
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", reveal);
  } else {
    reveal();
  }
  window.setTimeout(function () { if (isOn()) hide(); }, 8000);
  window.SkLoader = { show: show, hide: hide };
})();
