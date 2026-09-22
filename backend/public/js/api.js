/* Skilvi Ajax helper — JSON envelope {ok, data, error}, CSRF double-submit. */
(function () {
  "use strict";

  function csrf() {
    const m = document.cookie.match(/(?:^|; )skilvi_csrf=([^;]*)/);
    return m ? decodeURIComponent(m[1]) : "";
  }

  async function api(path, opts) {
    opts = opts || {};
    const headers = Object.assign(
      {
        Accept: "application/json",
        "X-CSRF-Token": csrf(),
      },
      opts.body && !(opts.body instanceof FormData)
        ? { "Content-Type": "application/json" }
        : {},
      opts.headers || {}
    );
    const init = {
      method: opts.method || (opts.body ? "POST" : "GET"),
      credentials: "same-origin",
      headers,
    };
    if (opts.body !== undefined) {
      init.body = opts.body instanceof FormData ? opts.body : JSON.stringify(opts.body);
    }
    const res = await fetch(path, init);
    let body = null;
    try {
      body = await res.json();
    } catch (e) {
      throw Object.assign(new Error("The server sent a bad response."), { status: res.status });
    }
    if (!body || body.ok !== true) {
      const err = (body && body.error) || {};
      const ex = new Error(err.message || "Request failed");
      ex.code = err.code;
      ex.status = res.status;
      ex.fields = err.fields || null;
      throw ex;
    }
    return body.data;
  }

  function busy(btn, on) {
    if (!btn) return;
    if (on) {
      btn.dataset.prev = btn.textContent;
      btn.disabled = true;
      btn.textContent = "Please wait…";
    } else {
      btn.disabled = false;
      if (btn.dataset.prev) btn.textContent = btn.dataset.prev;
    }
  }

  function toast(msg, type) {
    if (window.Sk && typeof window.Sk.toast === "function") {
      window.Sk.toast(msg, type);
      return;
    }
    alert(msg);
  }

  function bindOtpBoxes(root) {
    const boxes = Array.from((root || document).querySelectorAll(".otp-box"));
    if (!boxes.length) return () => "";
    boxes.forEach((el, i) => {
      el.addEventListener("input", () => {
        el.value = el.value.replace(/\D/g, "").slice(0, 1);
        if (el.value && boxes[i + 1]) boxes[i + 1].focus();
        const code = boxes.map((b) => b.value).join("");
        if (code.length === 6) {
          const form = el.closest("form");
          if (form) form.requestSubmit();
        }
      });
      el.addEventListener("keydown", (e) => {
        if (e.key === "Backspace" && !el.value && boxes[i - 1]) {
          boxes[i - 1].focus();
        }
      });
      el.addEventListener("paste", (e) => {
        const t = (e.clipboardData || window.clipboardData).getData("text").replace(/\D/g, "").slice(0, 6);
        if (!t) return;
        e.preventDefault();
        t.split("").forEach((ch, j) => {
          if (boxes[j]) boxes[j].value = ch;
        });
        boxes[Math.min(t.length, boxes.length) - 1].focus();
      });
    });
    return () => boxes.map((b) => b.value).join("");
  }

  async function refreshBadges() {
    try {
      const b = await api("/api/me/badges");
      document.querySelectorAll('.side-item[href="messages.html"] .badge-n, a.side-item[href="messages.html"] .badge-n').forEach((el) => {
        el.textContent = b.messages || "";
        el.style.display = b.messages ? "" : "none";
      });
      document.querySelectorAll('.side-item[href="disputes.html"] .badge-n').forEach((el) => {
        if (!el) return;
      });
      document.querySelectorAll(".icon-btn .dot, a.icon-btn .dot").forEach((el) => {
        el.style.display = b.notifications ? "" : "none";
      });
    } catch (e) { /* guests */ }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", refreshBadges);
  } else {
    refreshBadges();
  }
  setInterval(refreshBadges, 30000);

  window.SkApi = { api, csrf, busy, toast, bindOtpBoxes, refreshBadges };
})();
