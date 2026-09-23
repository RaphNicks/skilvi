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
    const raw = await res.text();
    let body = null;
    try {
      body = raw ? JSON.parse(raw) : null;
    } catch (e) {
      const hint = (raw || "").replace(/\s+/g, " ").slice(0, 160);
      throw Object.assign(
        new Error(hint ? "Server error: " + hint : "The server sent a bad response."),
        { status: res.status }
      );
    }
    if (!body || body.ok !== true) {
      const err = (body && body.error) || {};
      const ex = new Error(err.message || "Request failed");
      ex.code = err.code;
      ex.status = res.status;
      ex.fields = err.fields || null;
      if (ex.fields || ex.code === "credentials" || ex.code === "email_taken" || ex.code === "phone_taken") {
        showFieldErrors(ex);
      }
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

  const FIELD_ALIAS = {
    budget: ["budget", "budget_naira"],
    work_mode: ["work_mode", "pj2"],
    identifier: ["identifier", "email"],
    email: ["email", "identifier", "meEmail"],
    phone: ["phone", "mePhone"],
    password: ["password", "mePassword"],
    full_name: ["full_name", "meName"],
    bid: ["bid", "propBid", "bidNaira"],
    cover_note: ["cover_note", "coverNote"],
    packages: ["packages"],
    body: ["body", "msgInput", "chatInput"],
    join_as: ["join_as"],
    headline: ["headline", "meHeadline"],
    bio: ["bio", "meBio"],
    state: ["state", "meState"],
    city: ["city", "meCity"],
    amount: ["amount", "amount_naira", "wdAmount"],
    bank_name: ["bank_name", "wdBank"],
    account_number: ["account_number", "wdAcct"],
    account_name: ["account_name", "wdName"],
    code: ["code", "wdCode"],
  };

  function clearFieldErrors(root) {
    root = root || document;
    root.querySelectorAll(".field.invalid").forEach((f) => f.classList.remove("invalid"));
    root.querySelectorAll(".pkg-row.invalid").forEach((f) => f.classList.remove("invalid"));
    root.querySelectorAll(".form-error[data-sk-err]").forEach((el) => el.remove());
  }

  function markField(el, message) {
    if (!el) return;
    const field = el.closest(".field") || el.closest(".pkg-row") || el.parentElement;
    if (!field) return;
    field.classList.add("invalid");
    let msg = field.querySelector(".form-error");
    if (!msg) {
      msg = document.createElement("span");
      msg.className = "form-error";
      msg.setAttribute("data-sk-err", "1");
      field.appendChild(msg);
    }
    msg.textContent = message;
    msg.style.display = "block";
    const clear = () => {
      field.classList.remove("invalid");
      if (msg && msg.getAttribute("data-sk-err")) msg.remove();
    };
    el.addEventListener("input", clear, { once: true });
    el.addEventListener("change", clear, { once: true });
  }

  function findControl(root, key) {
    const names = FIELD_ALIAS[key] || [key];
    for (let i = 0; i < names.length; i++) {
      const n = names[i];
      const el =
        root.querySelector('[name="' + n + '"]') ||
        root.querySelector('[data-field="' + n + '"]') ||
        (/^[A-Za-z_][\w-]*$/.test(n) ? root.querySelector("#" + n) : null);
      if (el) return el;
    }
    if (key === "packages") {
      return root.querySelector("#pkgRows .pkg-row input") || root.querySelector(".pkg-row input");
    }
    return null;
  }

  function showFieldErrors(err, root, opts) {
    root = root || document;
    opts = opts || {};
    if (!opts.keep) clearFieldErrors(root);
    let fields = Object.assign({}, (err && err.fields) || {});
    if (err && err.code === "credentials") {
      fields.identifier = fields.identifier || err.message;
      fields.password = fields.password || err.message;
    }
    if (err && err.code === "email_taken") fields.email = fields.email || err.message;
    if (err && err.code === "phone_taken") fields.phone = fields.phone || err.message;
    const keys = Object.keys(fields);
    if (!keys.length) return false;
    let first = null;
    keys.forEach((key) => {
      const el = findControl(root, key);
      if (!el) return;
      markField(el, fields[key]);
      if (!first) first = el;
    });
    if (first) {
      try { first.focus({ preventScroll: true }); } catch (e) { try { first.focus(); } catch (e2) {} }
      try { first.scrollIntoView({ behavior: "smooth", block: "center" }); } catch (e) {}
    }
    return !!first;
  }

  document.addEventListener("invalid", (e) => {
    const el = e.target;
    if (!el || el.tagName === "FORM") return;
    let message = "This field is required.";
    if (el.validity) {
      if (el.validity.typeMismatch && el.type === "email") message = "Enter a working email.";
      else if (el.validity.tooShort) message = "Use at least " + el.minLength + " characters.";
      else if (el.validity.valueMissing) {
        const lab = el.closest(".field") && el.closest(".field").querySelector("label");
        const name = lab ? lab.textContent.replace(/\*/g, "").replace(/\s+/g, " ").trim() : "";
        message = name ? name + " is required." : "This field is required.";
      } else if (el.validationMessage) message = el.validationMessage;
    }
    markField(el, message);
  }, true);

  document.addEventListener("submit", (e) => {
    if (e.target && e.target.tagName === "FORM") clearFieldErrors(e.target);
  }, true);

  window.SkApi = { api, csrf, busy, toast, bindOtpBoxes, refreshBadges, showFieldErrors, clearFieldErrors };
})();
