(function () {
  "use strict";
  const api = (window.SkApi && window.SkApi.api) || (async (p) => (await fetch(p)).json().then((b) => b.data));
  const toast = (window.SkApi && window.SkApi.toast) || (window.Sk && window.Sk.toast) || ((m) => console.log(m));
  const busy = (window.SkApi && window.SkApi.busy) || function () {};
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const I = window.SkIconSvg || {};
  const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const params = new URLSearchParams(location.search);
  const page = document.body.getAttribute("data-page") || "";

  function init(s) {
    const p = String(s || "").trim().split(/\s+/);
    if (!p[0]) return "?";
    return (p[0][0] + (p[1] ? p[1][0] : "")).toUpperCase();
  }

  function gate(err) {
    if (err && err.status === 401) {
      location.href = "/login.html?next=" + encodeURIComponent(location.pathname + location.search);
      return true;
    }
    return false;
  }

  function paintMe(me) {
    if (!me) return;
    document.querySelectorAll(".side-user .su-name").forEach((el) => {
      el.textContent = me.full_name || "You";
    });
    document.querySelectorAll(".sb-user .su-name").forEach((el) => {
      el.textContent = (me.full_name || "").split(" ")[0] || "You";
    });
    document.querySelectorAll(".side-user .avatar, .sb-user .avatar").forEach((el) => {
      el.textContent = me.initials || "?";
    });
    document.querySelectorAll(".side-user .su-role").forEach((el) => {
      el.textContent = me.verified ? "Worker · Verified" : "Worker";
    });
  }

  async function dash() {
    const me = await api("/api/me");
    paintMe(me);
    const d = await api("/api/dashboard/worker");
    const s = d.stats || {};
    const first = (me.full_name || "").split(" ")[0];
    const h1 = $(".ph-title");
    if (h1) {
      h1.innerHTML = "Hello, " + esc(first) +
        (s.verified
          ? ' <span class="badge-verified" id="wbBadge">' + (I.shield || "") + " Verified</span>"
          : "");
    }
    const sub = $(".ph-sub");
    if (sub) {
      const pct = s.profile_strength != null ? s.profile_strength : d.profile_strength;
      sub.textContent = (pct != null ? "Your profile is " + pct + "% complete. " : "")
        + (s.verified
          ? "Identity checked — not a skill certificate."
          : "Add a bio and a live service so clients can hire you.");
    }
    if ($("#wdStats")) {
      const stats = [
        [I.wallet2 || "", "Available balance", s.available_label || "₦0", (s.pending_label || "₦0") + " pending in escrow"],
        [I.briefcase || "", "Active orders", String(s.active_orders || 0), (s.delivered || 0) + " delivered, " + (s.in_progress || 0) + " in progress"],
        [I.jobs || "", "Proposals pending", String(s.proposals_pending || 0), "waiting on clients"],
        [I.star || "", "Rating", String(s.rating || 0), (s.reviews || 0) + " reviews"],
      ];
      $("#wdStats").innerHTML = stats.map(([i, l, v, delta]) =>
        '<div class="stat-card"><div class="label"><span class="stat-icon">' + i + "</span>" + l + '</div><div class="value">' + esc(v) + '</div><div class="delta">' + esc(delta) + "</div></div>"
      ).join("");
    }
    const q = document.querySelector(".queue-card");
    if (q) {
      const needs = d.needs || [];
      q.innerHTML = needs.map((n) =>
        '<div class="queue-row"><span class="avatar ' + esc(n.tone || "a2") + '">' + esc(init(n.who)) + "</span>" +
        '<div class="qr-main"><div class="qr-t">' + esc(n.title) + '</div><div class="qr-s">' + esc(n.sub) + "</div></div>" +
        '<span class="st ' + esc(n.chip) + '">' + esc(n.label) + "</span>" +
        '<a class="btn btn-secondary btn-sm" href="' + esc(n.href) + '">Open</a></div>'
      ).join("") || '<p class="tiny faint">Nothing needs you right now.</p>';
      const tag = q.closest(".card") && q.closest(".card").querySelector(".tag");
      if (tag) tag.textContent = String(needs.length);
    }
    const kvs = $$(".grid.grid-2 .card.card-pad .kv .v.amount, .grid.grid-2 .card.card-pad .kv .v");
    if (kvs[0]) kvs[0].textContent = (d.wallet && d.wallet.available_label) || "₦0";
    if (kvs[1]) kvs[1].textContent = (d.wallet && d.wallet.pending_label) || "₦0";
    const week = document.querySelector(".grid.grid-2 .card.card-pad .amount");
    if (week && d.wallet) week.textContent = d.wallet.lifetime_label;
    if ($("#wdRecent")) {
      $("#wdRecent").innerHTML = (d.recent || []).map((o) =>
        '<tr><td><span class="cell-main">' + esc(o.title) + '</span><div class="cell-sub">' + esc(o.id) + "</div></td>" +
        "<td>" + esc(o.client_name) + '</td><td class="num amount">' + esc(o.amount_label) + '</td><td class="num">' + esc(o.worker_net) + "</td>" +
        '<td><span class="st ' + esc(o.chip) + '">' + esc(o.stateLabel) + "</span></td>" +
        '<td class="right"><a class="btn btn-secondary btn-sm" href="order-detail.html?id=' + encodeURIComponent(o.id) + '">Open</a></td></tr>'
      ).join("") || '<tr><td colspan="6" class="muted">No orders yet.</td></tr>';
    }
  }

  function paintWithdrawErrors(fields) {
    if (window.SkApi && window.SkApi.showFieldErrors) {
      window.SkApi.showFieldErrors({ fields: fields }, $("#withdrawForm") || document);
    }
  }

  function readWithdraw() {
    return {
      bank_name: ($("#wdBank") && $("#wdBank").value) || "",
      account_number: String(($("#wdAcct") && $("#wdAcct").value) || "").replace(/\D/g, ""),
      account_name: (($("#wdName") && $("#wdName").value) || "").trim(),
      amount_naira: String(($("#wdAmount") && $("#wdAmount").value) || "0").replace(/\D/g, ""),
    };
  }

  function validateWithdraw(minNaira, availableNaira) {
    const v = readWithdraw();
    const fields = {};
    const naira = Number(v.amount_naira || 0);
    const min = minNaira != null ? minNaira : 5000;
    if (!v.bank_name) fields.bank_name = "Pick a bank.";
    if (v.account_number.length !== 10) fields.account_number = "Use a 10-digit NUBAN.";
    if (v.account_name.length < 3) fields.account_name = "Account name as it appears at the bank.";
    if (!naira || naira < min) fields.amount = "Minimum withdrawal is ₦" + Number(min).toLocaleString("en-NG") + ".";
    else if (availableNaira != null && naira > availableNaira) fields.amount = "That is more than your available balance.";
    return fields;
  }

  function bindWithdrawForm(wallet) {
    const form = $("#withdrawForm");
    if (!form || form.dataset.bound === "1") return;
    form.dataset.bound = "1";
    const minNaira = wallet && wallet.min_naira != null ? wallet.min_naira : 5000;
    const availableNaira = wallet && wallet.available_naira != null ? wallet.available_naira : null;

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const btn = form.querySelector('button[type="submit"]');
      const wrap = $("#wdOtpWrap");
      const code = ($("#wdCode") && $("#wdCode").value.trim()) || "";
      if (wrap && wrap.style.display !== "none") {
        if (code.length !== 6) {
          paintWithdrawErrors({ code: "Enter the 6-digit code we sent." });
          toast("Please fix the highlighted fields.", "error");
          return;
        }
        busy(btn, true);
        try {
          await api("/api/withdrawals/confirm", { body: { code } });
          toast("Withdrawal requested. We will pay it to your bank.", "success");
          location.reload();
        } catch (err) {
          if (!gate(err)) {
            if (window.SkApi && window.SkApi.showFieldErrors) window.SkApi.showFieldErrors(err, form);
            toast(err.message, "error");
          }
        } finally {
          busy(btn, false);
        }
        return;
      }
      const fields = validateWithdraw(minNaira, availableNaira);
      if (Object.keys(fields).length) {
        paintWithdrawErrors(fields);
        toast("Please fix the highlighted fields.", "error");
        return;
      }
      busy(btn, true);
      try {
        const v = readWithdraw();
        const otp = await api("/api/withdrawals/start", { body: v });
        if (wrap) wrap.style.display = "";
        if ($("#wdOtpHint")) {
          const via = otp.channel === "email" ? "email" : "phone";
          $("#wdOtpHint").textContent = "Code sent to " + (otp.phone_mask || "you") + " (" + via + ")" +
            (otp.dev_code ? " · dev " + otp.dev_code : "") + ".";
        }
        if ($("#wdCode")) $("#wdCode").focus();
        toast("Enter the code we sent, then request again.", "success");
      } catch (err) {
        if (!gate(err)) {
          if (window.SkApi && window.SkApi.showFieldErrors) window.SkApi.showFieldErrors(err, form);
          toast(err.message, "error");
        }
      } finally {
        busy(btn, false);
      }
    });

    const addBank = $("#wdAddBank");
    if (addBank) {
      addBank.addEventListener("click", () => {
        if ($("#wdBank")) { $("#wdBank").value = ""; $("#wdBank").focus(); }
        if ($("#wdAcct")) $("#wdAcct").value = "";
        if ($("#wdName")) $("#wdName").value = "";
        toast("Enter the new bank, 10-digit NUBAN, and account name.", "success");
      });
    }
  }

  function exportLedgerCsv(rows) {
    const lines = [["Date", "Movement", "Reference", "Amount", "Balance"]];
    (rows || []).forEach((l) => {
      lines.push([l.date || "", l.label || "", l.ref || "", l.amount_label || "", l.bal_label || ""]);
    });
    if (lines.length === 1) {
      toast("No ledger rows to export yet.", "error");
      return;
    }
    const csv = lines.map((r) => r.map((c) => '"' + String(c).replace(/"/g, '""') + '"').join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "skilvi-ledger.csv";
    a.click();
    URL.revokeObjectURL(a.href);
    toast("Ledger downloaded.", "success");
  }

  async function wallet() {
    const me = await api("/api/me");
    paintMe(me);
    if (params.get("tab") === "withdrawals") {
      const form = $("#withdrawForm");
      if (form) form.scrollIntoView({ block: "start" });
    }
    let d = { wallet: {}, withdrawals: [], transactions: [] };
    try {
      d = await api("/api/wallet");
    } catch (err) {
      if (gate(err)) return;
      toast(err.message || "Could not load wallet.", "error");
    }
    const w = d.wallet || {};
    const cards = $$(".wallet-card .value");
    if (cards[0]) cards[0].textContent = w.available_label || "₦0";
    if (cards[1]) cards[1].textContent = w.pending_label || "₦0";
    if (cards[2]) cards[2].textContent = w.lifetime_label || "₦0";
    const pendingSub = $$(".wallet-card .sub");
    if (pendingSub[0]) pendingSub[0].textContent = "Available for withdrawal";
    if (pendingSub[1]) pendingSub[1].textContent = "In escrow until clients approve work";
    if (pendingSub[2]) pendingSub[2].textContent = "Lifetime earnings";
    const hint = $("#wdAmount") && $("#wdAmount").closest(".field") && $("#wdAmount").closest(".field").querySelector(".hint");
    if (hint) hint.textContent = "Maximum: " + (w.available_label || "₦0") + " · minimum " + (w.min_label || "₦5,000");
    const sel = $("#wdBank");
    if (sel) {
      const saved = w.banks || [];
      const opts = w.bank_options || [];
      const current = sel.value;
      sel.innerHTML = '<option value="">Pick a bank</option>' +
        saved.map((b) => '<option value="' + esc(b.bank_name) + '" data-acct="' + esc(b.account_number || "") + '" data-name="' + esc(b.account_name || "") + '">' + esc(b.label) + "</option>").join("") +
        opts.map((b) => '<option value="' + esc(b) + '">' + esc(b) + "</option>").join("");
      if (current) sel.value = current;
      sel.addEventListener("change", () => {
        const o = sel.options[sel.selectedIndex];
        if (o && o.dataset.acct && $("#wdAcct")) $("#wdAcct").value = o.dataset.acct;
        if (o && o.dataset.name && $("#wdName")) $("#wdName").value = o.dataset.name;
      });
    }
    const list = $("#wdList");
    if (list) {
      const rows = d.withdrawals || [];
      list.innerHTML = rows.map((wrow) =>
        '<div class="kv"><span class="k">' + esc(wrow.date) + " · " + esc(wrow.bank) + ' <span class="cell-sub">' + esc(wrow.id) + "</span></span>" +
        '<span class="v"><span class="amount">' + esc(wrow.amount_label) + '</span> <span class="st ' + esc(wrow.chip) + '" style="margin-left:8px">' + esc(wrow.stateLabel) + "</span></span></div>"
      ).join("") || '<p class="tiny faint">No withdrawals yet.</p>';
    }
    const typeDot = { settlement: "var(--green)", commission: "var(--red)", withdrawal: "var(--amber)" };
    const tx = d.transactions || [];
    if ($("#wdLedger")) {
      $("#wdLedger").innerHTML = tx.map((l) =>
        '<tr><td class="cell-sub">' + esc(l.date) + '</td><td><span class="ledger-type"><span class="lt-dot" style="background:' + (typeDot[l.type] || "var(--ink-3)") + '"></span>' + esc(l.label) + "</span>" +
        '<div class="cell-sub">' + esc(l.ref) + "</div></td>" +
        '<td class="num amount" style="color:' + (l.amount_kobo < 0 ? "var(--red)" : "var(--ink)") + '">' + esc(l.amount_label) + "</td>" +
        '<td class="num cell-sub">' + esc(l.bal_label) + "</td></tr>"
      ).join("") || '<tr><td colspan="4" class="muted">Ledger is empty until escrow releases.</td></tr>';
    }
    bindWithdrawForm(w);
    const exp = $("#wdExport");
    if (exp && !exp.dataset.bound) {
      exp.dataset.bound = "1";
      exp.addEventListener("click", () => exportLedgerCsv(tx));
    }
  }

  function pkgLine(p) {
    const naira = p.price_naira || Math.round((p.price_kobo || 0) / 100);
    const price = (window.Sk && window.Sk.ngn) ? window.Sk.ngn(naira) : "₦" + Number(naira).toLocaleString("en-NG");
    return esc(p.name || "Package") + " · " + price + " · " + (p.days || 0) + " days · " + (p.revisions || 0) + " revision" + ((p.revisions === 1) ? "" : "s");
  }

  async function services() {
    const me = await api("/api/me");
    paintMe(me);
    const list = await api("/api/services?owner=me");
    let host = $("#wsList");
    if (!host) {
      host = document.createElement("div");
      host.id = "wsList";
      const alert = $("#svAlert");
      if (alert && alert.parentNode) alert.parentNode.insertBefore(host, alert.nextSibling);
    }
    if (!list.length) {
      host.innerHTML = '<div class="card card-pad"><p class="tiny faint">No services yet. Add one so clients can hire you without posting a job.</p></div>';
      return;
    }
    host.innerHTML = list.map((s) => {
      const pkgs = (s.packages || []).map((p) => '<div class="kv"><span class="k">' + esc(p.name) + '</span><span class="v">' + pkgLine(p) + "</span></div>").join("");
      const live = s.live;
      return '<div class="card card-pad mt-3" data-sid="' + esc(s.id) + '"><div class="row spread" style="align-items:flex-start;gap:12px"><div>' +
        '<div class="row" style="gap:10px;flex-wrap:wrap"><h3 style="font-size:16px">' + esc(s.title) + '</h3>' +
        '<span class="st ' + (live ? "st-green" : "st-gray") + '">' + (live ? "Active · searchable" : "Paused · hidden from search") + "</span></div>" +
        '<p class="small muted mt-1">' + esc(s.category || "Uncategorised") + " · " + esc(s.mode) + " · from " + esc(s.from_label) + "</p></div>" +
        '<div class="row" style="gap:8px;flex:none">' +
        '<a class="btn btn-secondary btn-sm" href="service-detail.html?id=' + encodeURIComponent(s.id) + '">View</a>' +
        '<a class="btn btn-secondary btn-sm" href="worker-service-form.html?id=' + encodeURIComponent(s.id) + '">Edit</a>' +
        '<label class="switch" title="Pause service"><input type="checkbox" class="live-toggle"' + (live ? "" : " checked") + '><span class="track"></span></label>' +
        "</div></div><hr class=\"divider mt-3 mb-2\">" + pkgs + "</div>";
    }).join("");
    $$(".live-toggle", host).forEach((t) => t.addEventListener("change", async () => {
      const card = t.closest("[data-sid]");
      try {
        const updated = await api("/api/services/" + encodeURIComponent(card.getAttribute("data-sid")) + "/pause", { body: {} });
        const chip = card.querySelector(".st");
        if (updated.live) {
          chip.className = "st st-green";
          chip.textContent = "Active · searchable";
          toast("Service is live again.", "success");
        } else {
          chip.className = "st st-gray";
          chip.textContent = "Paused · hidden from search";
          toast("Service paused. Active orders are unaffected.", "success");
        }
      } catch (err) {
        t.checked = !t.checked;
        if (!gate(err)) toast(err.message, "error");
      }
    }));
  }

  function readPackages() {
    return $$(".pkg-row").map((row) => {
      const inputs = row.querySelectorAll("input");
      return {
        name: (inputs[0] && inputs[0].value) || "Package",
        price_naira: Number(String((inputs[1] && inputs[1].value) || "0").replace(/\D/g, "")),
        days: Number((inputs[2] && inputs[2].value) || 7),
        revisions: Number((inputs[3] && inputs[3].value) || 1),
      };
    });
  }

  function modeValue() {
    const checked = document.querySelector('input[name="work_mode"]:checked');
    if (checked) return checked.value;
    if ($("#pj_1_0") && $("#pj_1_0").checked) return "hybrid";
    return "remote";
  }

  async function serviceForm() {
    const me = await api("/api/me");
    paintMe(me);
    const form = $("#serviceForm");
    if (!form) return;
    const title = form.querySelector('input[name="title"]') || form.querySelector(".input");
    const category = form.querySelector('select[name="category"]') || form.querySelector("select");
    const desc = form.querySelector('textarea[name="description"]') || form.querySelector("textarea");
    if (title && !title.name) title.name = "title";
    if (category && !category.name) category.name = "category";
    if (desc && !desc.name) desc.name = "description";

    const id = params.get("id") || "";
    if (id) {
      try {
        const s = await api("/api/me/services/" + encodeURIComponent(id));
        const h1 = $(".ph-title");
        if (h1) h1.textContent = "Edit service";
        if (title) title.value = s.title || "";
        if (desc) desc.value = s.description || "";
        if (category && s.category) {
          const opt = Array.from(category.options).find((o) => o.text === s.category || o.value === s.category);
          if (opt) category.value = opt.value;
        }
        const mode = document.querySelector('input[name="work_mode"][value="' + (s.mode || "remote") + '"]');
        if (mode) mode.checked = true;
        const pkgs = s.packages || [];
        const rows = $$(".pkg-row");
        pkgs.forEach((p, i) => {
          const row = rows[i];
          if (!row) return;
          const inputs = row.querySelectorAll("input");
          if (inputs[0]) inputs[0].value = p.name || "";
          if (inputs[1]) inputs[1].value = String(p.price_naira || Math.round((p.price_kobo || 0) / 100));
          if (inputs[2]) inputs[2].value = String(p.days || "");
          if (inputs[3]) inputs[3].value = String(p.revisions || "");
        });
      } catch (err) {
        if (!gate(err)) toast(err.message, "error");
      }
    }

    async function save(draft) {
      const btn = form.querySelector(draft ? ".btn-secondary" : 'button[type="submit"]');
      const body = {
        title: (title && title.value) || "",
        category: (category && (category.options[category.selectedIndex] ? category.options[category.selectedIndex].text : category.value)) || "",
        description: (desc && desc.value) || "",
        work_mode: modeValue(),
        packages: readPackages(),
        draft: !!draft,
      };
      busy(btn, true);
      try {
        let saved;
        if (id) {
          saved = await api("/api/services/" + encodeURIComponent(id), { method: "POST", body });
        } else {
          saved = await api("/api/services", { body });
        }
        toast(draft ? "Saved as draft." : "Service is live.", "success");
        location.href = "worker-services.html";
        return saved;
      } catch (err) {
        if (!gate(err)) {
          if (window.SkApi && window.SkApi.showFieldErrors) window.SkApi.showFieldErrors(err, form);
          toast(err.message, "error");
        }
      } finally {
        busy(btn, false);
      }
    }

    form.addEventListener("submit", (e) => {
      e.preventDefault();
      save(false);
    });
    const draftBtn = form.querySelector("#saveDraft") || Array.from(form.querySelectorAll(".btn-secondary")).find((b) => /draft/i.test(b.textContent || ""));
    if (draftBtn) {
      draftBtn.removeAttribute("data-toast");
      draftBtn.addEventListener("click", (e) => {
        e.preventDefault();
        save(true);
      });
    }

    const addPkg = $("#addPkg");
    const addQ = $("#addQ");
    function wireRm() {
      $$(".rm-pkg").forEach((b) => { b.onclick = () => { const row = b.closest(".pkg-row"); if (row) row.remove(); }; });
      $$(".rm-q").forEach((b) => { b.onclick = () => { const row = b.closest(".row"); if (row) row.remove(); }; });
    }
    if (addPkg) {
      addPkg.addEventListener("click", () => {
        const wrap = $("#pkgRows");
        if (!wrap) return;
        if (wrap.querySelectorAll(".pkg-row").length >= 3) { toast("Maximum 3 packages per service.", "error"); return; }
        const n = wrap.querySelectorAll(".pkg-row").length + 1;
        wrap.insertAdjacentHTML("beforeend",
          '<div class="pkg-row card card-pad mb-2"><div class="row spread mb-1"><span class="bold small" style="color:var(--ink-3)">PACKAGE ' + n +
          '</span><button type="button" class="link-muted rm-pkg">Remove</button></div><div class="form-row-2">' +
          '<div class="field"><label>Package name</label><input class="input" placeholder="e.g. Standard"></div>' +
          '<div class="field"><label>Price (₦)</label><input class="input money-input" placeholder="85,000"></div></div>' +
          '<div class="form-row-2 mt-2"><div class="field"><label>Delivery (days)</label><input class="input" type="number" min="1" placeholder="10"></div>' +
          '<div class="field"><label>Revisions included</label><input class="input" type="number" min="0" max="10" placeholder="2"></div></div></div>');
        wrap.querySelectorAll(".money-input").forEach((inp) => {
          if (inp.dataset.wired) return;
          inp.dataset.wired = "1";
          inp.addEventListener("input", () => {
            const v = inp.value.replace(/[^0-9]/g, "");
            inp.value = v ? Number(v).toLocaleString("en-NG") : "";
          });
        });
        wireRm();
      });
    }
    if (addQ) {
      addQ.addEventListener("click", () => {
        const wrap = $("#qRows");
        if (!wrap) return;
        if (wrap.children.length >= 6) { toast("Maximum 6 questions.", "error"); return; }
        wrap.insertAdjacentHTML("beforeend",
          '<div class="row mb-1" style="gap:8px"><input class="input" placeholder="Question for the client (e.g. How many pages?)">' +
          '<button type="button" class="icon-btn rm-q" style="flex:none">×</button></div>');
        wireRm();
      });
    }
    wireRm();
    $$("#areaChips .chip").forEach((c) => c.addEventListener("click", () => c.classList.toggle("active")));
    const quoteBtn = $("#customQuote") || form.querySelector(".btn-ghost");
    if (quoteBtn && /custom quote/i.test(quoteBtn.textContent || "")) {
      quoteBtn.removeAttribute("data-toast");
      quoteBtn.addEventListener("click", () => {
        const on = quoteBtn.getAttribute("data-on") === "1";
        quoteBtn.setAttribute("data-on", on ? "0" : "1");
        quoteBtn.textContent = on ? "Enable “custom quote” option" : "Custom quote on — clients can request a price";
        toast(on ? "Custom quote turned off." : "Custom quote on — clients can request a price outside your packages.", "success");
      });
    }
    const travel = $("#travelField");
    const travelNote = $("#travelNoteField");
    const syncTravel = () => {
      const v = (document.querySelector('input[name="work_mode"]:checked') || {}).value;
      const on = v === "on-site" || v === "hybrid";
      if (travel) travel.style.display = on ? "" : "none";
      if (travelNote) travelNote.style.display = on ? "" : "none";
    };
    $$('input[name="work_mode"]').forEach((m) => m.addEventListener("change", syncTravel));
    syncTravel();
  }

  document.addEventListener("DOMContentLoaded", () => {
    const run = async () => {
      try {
        if (page === "worker-dash") await dash();
        else if (page === "wallet") await wallet();
        else if (page === "worker-services") await services();
        else if (page === "service-form") await serviceForm();
      } catch (err) {
        if (!gate(err)) {
          console.error(err);
          toast(err.message || "Could not load.", "error");
        }
      }
    };
    run();
  });
})();
