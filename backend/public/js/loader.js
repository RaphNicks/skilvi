/* Full-screen video loader for real navigations. */
(function () {
  "use strict";
  var KEY = "skilvi_nav";

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
    if (el) el.setAttribute("aria-hidden", "false");
    var v = vid();
    if (v) {
      try { v.currentTime = 0; } catch (e) { /* ignore */ }
      var p = v.play();
      if (p && p.catch) p.catch(function () {});
    }
  }
  function hide() {
    try { sessionStorage.removeItem(KEY); } catch (e) { /* private mode */ }
    document.documentElement.classList.remove("sk-loading");
    var el = box();
    if (el) el.setAttribute("aria-hidden", "true");
    var v = vid();
    if (v) v.pause();
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

  document.addEventListener("click", function (e) {
    if (e.defaultPrevented || e.button !== 0) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target && e.target.closest && e.target.closest("a[href]");
    if (!internalLink(a)) return;
    e.preventDefault();
    show();
    var href = a.href;
    requestAnimationFrame(function () {
      location.href = href;
    });
  }, true);

  window.addEventListener("pageshow", function (e) {
    if (e.persisted) hide();
  });
  if (document.readyState === "complete") hide();
  else window.addEventListener("load", hide);
  window.setTimeout(function () { if (isOn()) hide(); }, 12000);
  window.SkLoader = { show: show, hide: hide };
})();
