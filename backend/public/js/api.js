/* Skilvi Ajax helper — JSON envelope {ok, data, error}, CSRF double-submit. */
(function () {
  "use strict";

  function csrf() {
    const m = document.cookie.match(/(?:^|; )skilvi_csrf=([^;]*)/);
    return m ? decodeURIComponent(m[1]) : "";
  }

  function tabToken() {
    try { return sessionStorage.getItem("skilvi_auth") || ""; } catch (e) { return ""; }
  }
  function setTabToken(t) {
    try {
      if (t) sessionStorage.setItem("skilvi_auth", t);
      else sessionStorage.removeItem("skilvi_auth");
    } catch (e) { /* private mode */ }
  }
  document.addEventListener("click", (e) => {
    const a = e.target && e.target.closest && e.target.closest('a[href*="logout"]');
    if (a) setTabToken("");
  });

  async function api(path, opts) {
    opts = opts || {};
    const tok = tabToken();
    const headers = Object.assign(
      {
        Accept: "application/json",
        "X-CSRF-Token": csrf(),
      },
      tok ? { Authorization: "Bearer " + tok, "X-Skilvi-Token": tok } : {},
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
    let wrap = document.querySelector(".toast-wrap");
    if (!wrap) {
      wrap = document.createElement("div");
      wrap.className = "toast-wrap";
      document.body.appendChild(wrap);
    }
    const t = document.createElement("div");
    t.className = "toast" + (type ? " " + type : "");
    t.textContent = String(msg || "");
    wrap.appendChild(t);
    setTimeout(() => { t.style.opacity = "0"; t.style.transition = "opacity .3s"; setTimeout(() => t.remove(), 320); }, 3400);
  }

  const XICO = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>';

  let dialogBusy = null;
  function dialogRoot() {
    let root = document.getElementById("skDialog");
    if (root) return root;
    root = document.createElement("div");
    root.id = "skDialog";
    root.className = "modal-backdrop sk-dialog";
    root.setAttribute("role", "dialog");
    root.setAttribute("aria-modal", "true");
    root.setAttribute("aria-labelledby", "skDialogTitle");
    root.innerHTML =
      '<div class="modal" role="document">' +
        '<div class="modal-head"><div><div class="sk-dialog-kicker">Skilvi</div><h3 id="skDialogTitle"></h3></div>' +
        '<button type="button" class="icon-btn" data-sk-dialog-cancel aria-label="Close">' + XICO + "</button></div>" +
        '<div class="modal-body"><p class="small muted" id="skDialogBody"></p>' +
        '<div class="field mt-2" id="skDialogField" hidden><label id="skDialogLabel">Reason</label>' +
        '<input class="input" id="skDialogInput" autocomplete="off">' +
        '<textarea class="textarea" id="skDialogText" rows="3" hidden></textarea></div></div>' +
        '<div class="modal-foot">' +
        '<button type="button" class="btn btn-secondary" data-sk-dialog-cancel id="skDialogCancel">Cancel</button>' +
        '<button type="button" class="btn btn-primary" id="skDialogOk">OK</button></div></div>';
    document.body.appendChild(root);
    return root;
  }

  function closeDialog(root) {
    root.classList.remove("open");
    document.documentElement.classList.remove("sk-dialog-lock");
  }

  function openDialog(opts) {
    opts = opts || {};
    const root = dialogRoot();
    const title = root.querySelector("#skDialogTitle");
    const body = root.querySelector("#skDialogBody");
    const field = root.querySelector("#skDialogField");
    const label = root.querySelector("#skDialogLabel");
    const input = root.querySelector("#skDialogInput");
    const area = root.querySelector("#skDialogText");
    const okBtn = root.querySelector("#skDialogOk");
    const cancelBtn = root.querySelector("#skDialogCancel");
    const kind = opts.kind || "confirm";
    const inputType = opts.input || (kind === "prompt" ? "textarea" : "");
    title.textContent = opts.title || "Skilvi";
    body.textContent = opts.body || "";
    body.hidden = !opts.body;
    field.hidden = kind !== "prompt";
    cancelBtn.hidden = kind === "alert";
    okBtn.textContent = opts.ok || (kind === "alert" ? "OK" : kind === "prompt" ? "Continue" : "Confirm");
    okBtn.className = "btn " + (opts.danger ? "btn-solid-danger" : "btn-primary");
    label.textContent = opts.label || "Reason";
    input.hidden = inputType !== "text" && inputType !== "password";
    area.hidden = inputType !== "textarea";
    input.type = inputType === "password" ? "password" : "text";
    input.value = inputType === "textarea" ? "" : (opts.value || "");
    area.value = inputType === "textarea" ? (opts.value || "") : "";
    input.placeholder = opts.placeholder || "";
    area.placeholder = opts.placeholder || "";
    root.classList.add("open");
    document.documentElement.classList.add("sk-dialog-lock");
    const focusEl = kind === "prompt" ? (inputType === "textarea" ? area : input) : okBtn;
    setTimeout(() => { try { focusEl.focus(); } catch (e) {} }, 30);

    return new Promise((resolve) => {
      if (dialogBusy) {
        try { dialogBusy(kind === "prompt" ? null : false); } catch (e) {}
      }
      const done = (val) => {
        root.removeEventListener("click", onClick);
        document.removeEventListener("keydown", onKey, true);
        dialogBusy = null;
        closeDialog(root);
        resolve(val);
      };
      dialogBusy = done;
      const submit = () => {
        if (kind !== "prompt") {
          done(true);
          return;
        }
        const raw = ((inputType === "textarea" ? area.value : input.value) || "").trim();
        const min = Number(opts.minLength || 0);
        if (min && raw.length < min) {
          toast("Need at least " + min + " characters.", "error");
          return;
        }
        if (!raw && opts.required !== false) {
          toast("Fill this in to continue.", "error");
          return;
        }
        done(raw);
      };
      const onClick = (e) => {
        if (e.target === root || (e.target.closest && e.target.closest("[data-sk-dialog-cancel]"))) {
          e.preventDefault();
          done(kind === "prompt" ? null : false);
          return;
        }
        if (e.target === okBtn || (e.target.closest && e.target.closest("#skDialogOk"))) {
          e.preventDefault();
          submit();
        }
      };
      const onKey = (e) => {
        if (!root.classList.contains("open")) return;
        if (e.key === "Escape") {
          e.preventDefault();
          done(kind === "prompt" ? null : false);
        }
        if (e.key === "Enter" && kind === "prompt" && inputType !== "textarea") {
          e.preventDefault();
          submit();
        }
      };
      root.addEventListener("click", onClick);
      document.addEventListener("keydown", onKey, true);
    });
  }

  const dialog = {
    confirm(opts) { return openDialog(Object.assign({ kind: "confirm" }, opts)); },
    prompt(opts) { return openDialog(Object.assign({ kind: "prompt" }, opts)); },
    alert(opts) { return openDialog(Object.assign({ kind: "alert" }, typeof opts === "string" ? { body: opts } : opts)); },
  };

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

  window.SkApi = { api, csrf, busy, toast, dialog, bindOtpBoxes, refreshBadges, showFieldErrors, clearFieldErrors, setTabToken, tabToken };
})();
