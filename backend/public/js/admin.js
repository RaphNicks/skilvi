(function () {
  "use strict";
  const api = (window.SkApi && window.SkApi.api) || (async (p) => (await fetch(p)).json().then((b) => b.data));
  const toast = (window.SkApi && window.SkApi.toast) || (window.Sk && window.Sk.toast) || ((m) => alert(m));
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const file = (location.pathname.split("/").pop() || "index.html").replace(/^\//, "");
  const I = window.SkIconSvg || {};

  function gate(err) {
    if (err && err.status === 401) {
      location.href = "/login.html?next=" + encodeURIComponent(location.pathname);
      return true;
    }
    if (err && err.status === 403) {
      toast("This console is for Skilvi staff.", "error");
      return true;
    }
    return false;
  }
  function emptyRow(cols, msg) {
    return '<tr><td colspan="' + cols + '" class="muted">' + esc(msg) + "</td></tr>";
  }
  function kpiHtml(k) {
    return '<div class="kpi"><div class="k-label">' + esc(k.label) + '</div><div class="k-value">' + esc(k.value) + '</div><div class="k-sub">' + esc(k.sub || "") + "</div></div>";
  }
  async function act(path, body, ok) {
    try {
      await api(path, { body: body || {} });
      toast(ok || "Saved.", "success");
      location.reload();
    } catch (err) {
      if (!gate(err)) toast(err.message, "error");
    }
  }

  async function dash() {
    const d = await api("/api/admin/dashboard");
    if ($("#kpiGrid")) $("#kpiGrid").innerHTML = (d.kpis || []).map(kpiHtml).join("");
    const gmv = d.gmv || [];
    const max = Math.max(1, ...gmv.map((x) => x.kobo));
    if ($("#gmxBars")) {
      $("#gmxBars").innerHTML = gmv.map((x, i) => {
        const peak = x.kobo === max && x.kobo > 0 ? " peak" : "";
        return '<div class="bar' + peak + '" style="height:' + Math.round((x.kobo / max) * 100) + '%"><span class="bar-tip">' + esc(x.label) + "</span></div>";
      }).join("");
    }
    if ($("#gmxLabels")) $("#gmxLabels").innerHTML = gmv.map((x) => "<span>" + esc(x.day) + "</span>").join("");
    if ($("#gmxDisputes")) {
      $("#gmxDisputes").innerHTML = (d.disputes || []).slice(0, 5).map((x) =>
        '<div class="queue-row"><div class="qr-main"><div class="qr-t">' + esc(x.id) + " · " + esc(x.title) + '</div><div class="qr-s">' +
        esc(x.amount_label) + " frozen · " + esc(x.date) + "</div></div>" +
        (x.sla ? '<span class="sla warn">' + esc(x.sla) + "</span>" : "") +
        '<a class="btn btn-secondary btn-sm" href="../dispute-detail.html?id=' + encodeURIComponent(x.id) + '">Open</a></div>'
      ).join("") || '<p class="tiny faint">No open disputes.</p>';
    }
    if ($("#gmxWd")) {
      $("#gmxWd").innerHTML = (d.withdrawals || []).slice(0, 5).map((w) =>
        '<div class="queue-row"><span class="avatar sm a1">' + esc((w.worker || "?").split(" ").map((p) => p[0]).join("").slice(0, 2)) + "</span>" +
        '<div class="qr-main"><div class="qr-t">' + esc(w.worker) + '</div><div class="qr-s">' + esc(w.bank) + " · " + esc(w.date) + "</div></div>" +
        '<span class="amount" style="font-size:14px">' + esc(w.amount_label) + "</span>" +
        '<button class="btn btn-primary btn-sm wd-ok" type="button" data-id="' + esc(w.id) + '">Approve</button> ' +
        '<button class="btn btn-danger btn-sm wd-no" type="button" data-id="' + esc(w.id) + '">Hold</button></div>'
      ).join("") || '<p class="tiny faint">No pending withdrawals.</p>';
      $$(".wd-ok").forEach((b) => b.addEventListener("click", () => act("/api/admin/withdrawals/" + encodeURIComponent(b.dataset.id) + "/action", { action: "approve" }, "Approved.")));
      $$(".wd-no").forEach((b) => b.addEventListener("click", () => act("/api/admin/withdrawals/" + encodeURIComponent(b.dataset.id) + "/action", { action: "reject" }, "Held — funds returned.")));
    }
    const signupHost = document.querySelectorAll(".grid.grid-2")[1];
    const box = signupHost && signupHost.querySelector(".queue-card");
    if (box) {
      box.innerHTML = (d.signups || []).slice(0, 6).map((s) =>
        '<div class="queue-row"><span class="avatar sm ' + esc(s.tone) + '">' + esc(s.initials) + "</span>" +
        '<div class="qr-main"><div class="qr-t">' + esc(s.name) + '</div><div class="qr-s">' + esc(s.sub) + "</div></div>" +
        '<span class="st st-royal">' + esc(s.when) + "</span></div>"
      ).join("") || '<p class="tiny faint">No recent signups.</p>';
    }
    const needs = document.querySelector(".card.card-pad.mt-3 .stack");
    if (needs && d.needs) {
      needs.innerHTML = d.needs.map((n) =>
        '<div class="row spread" style="gap:12px;flex-wrap:wrap"><span class="st ' + esc(n.chip) + '">' + esc(n.label) + "</span>" +
        '<span class="small">' + esc(n.text) + '</span><a class="link" style="font-size:13px" href="' + esc(n.href) + '">Open →</a></div>'
      ).join("");
    }
  }

  async function users() {
    const load = async () => {
      const q = ($("#uSearch") && $("#uSearch").value) || "";
      const role = ($("#uRole") && $("#uRole").value) || "";
      const st = ($("#uState") && $("#uState").value) || "";
      const roleQ = /worker/i.test(role) ? "worker" : (/client/i.test(role) ? "client" : "");
      const stQ = /suspend/i.test(st) ? "suspended" : (/ban/i.test(st) ? "banned" : (/active/i.test(st) ? "active" : ""));
      const list = await api("/api/admin/users?q=" + encodeURIComponent(q) + "&role=" + roleQ + "&status=" + stQ);
      const flagged = $("#uFlags") && $("#uFlags").checked;
      const rows = flagged ? list.filter((u) => u.flags > 0) : list;
      if ($("#uBody")) {
        $("#uBody").innerHTML = rows.map((u) =>
          '<tr><td><span class="row" style="gap:10px"><span class="avatar sm ' + esc(u.tone) + '">' + esc(u.initials) + "</span><span>" +
          '<span class="cell-main">' + esc(u.name) + (u.flags ? ' <span class="flag">' + u.flags + "</span>" : "") + '</span>' +
          '<div class="cell-sub mono">' + esc(u.phone) + "</div></span></span></td>" +
          "<td>" + esc(u.role) + "</td><td>" + u.orders + "</td>" +
          '<td class="cell-sub">' + esc(u.skill || "—") + "</td><td>" + esc(u.joined) + "</td>" +
          '<td><span class="st ' + esc(u.chip) + '">' + esc(u.stateLabel) + "</span></td>" +
          '<td class="right nowrap">' +
          (u.status === "active"
            ? '<button class="btn btn-danger btn-sm u-act" data-id="' + u.id + '" data-act="suspend" type="button">Suspend</button>'
            : '<button class="btn btn-primary btn-sm u-act" data-id="' + u.id + '" data-act="activate" type="button">Activate</button>') +
          "</td></tr>"
        ).join("") || emptyRow(7, "No users match.");
        $$(".u-act").forEach((b) => b.addEventListener("click", () => {
          const reason = b.dataset.act === "suspend" ? (prompt("Reason the user will see:") || "") : "Reactivated by staff.";
          if (b.dataset.act === "suspend" && reason.trim().length < 8) { toast("Need a short reason.", "error"); return; }
          act("/api/admin/users/" + b.dataset.id + "/action", { action: b.dataset.act, reason }, "Updated.");
        }));
      }
    };
    ["#uSearch", "#uRole", "#uState", "#uFlags"].forEach((s) => { const el = $(s); if (el) el.addEventListener("input", load); if (el) el.addEventListener("change", load); });
    await load();
  }

  async function orders() {
    const load = async () => {
      const st = ($("#oState") && $("#oState").value) || "";
      const q = ($("#oSearch") && $("#oSearch").value) || "";
      const list = await api("/api/admin/orders?status=" + encodeURIComponent(st) + "&q=" + encodeURIComponent(q));
      if ($("#oBody")) {
        $("#oBody").innerHTML = list.map((o) =>
          '<tr><td><span class="cell-main">' + esc(o.title) + '</span><div class="cell-sub">' + esc(o.id) + "</div></td>" +
          '<td class="cell-sub">' + esc(o.parties) + '</td><td class="num amount">' + esc(o.amount_label) + "</td>" +
          "<td>" + esc(o.date) + '</td><td><span class="st ' + esc(o.chip) + '">' + esc(o.stateLabel) + "</span></td>" +
          '<td class="right nowrap"><a class="btn btn-secondary btn-sm" href="../order-detail.html?id=' + encodeURIComponent(o.id) + '">Timeline</a> ' +
          (o.can_release ? '<button class="btn btn-danger btn-sm o-rel" data-id="' + esc(o.id) + '" type="button">Force release</button>' : "—") +
          "</td></tr>"
        ).join("") || emptyRow(6, "No orders.");
        $$(".o-rel").forEach((b) => b.addEventListener("click", () => {
          const reason = prompt("Reason for force-release (audited):") || "";
          act("/api/admin/orders/" + encodeURIComponent(b.dataset.id) + "/action", { action: "release", reason }, "Released.");
        }));
      }
    };
    ["#oState", "#oAmount", "#oSearch"].forEach((s) => { const el = $(s); if (el) el.addEventListener("change", load); if (el) el.addEventListener("input", load); });
    await load();
  }

  async function payments() {
    const load = async () => {
      const st = ($("#pState") && $("#pState").value) || "";
      const m = ($("#pMethod") && $("#pMethod").value) || "";
      const q = ($("#pSearch") && $("#pSearch").value) || "";
      const list = await api("/api/admin/payments?status=" + encodeURIComponent(st) + "&method=" + encodeURIComponent(m) + "&q=" + encodeURIComponent(q));
      if ($("#pBody")) {
        $("#pBody").innerHTML = list.map((p) =>
          '<tr><td class="cell-main">' + esc(p.id) + '</td><td class="cell-sub">' + esc(p.order || "—") + "</td><td>" + esc(p.payer) + "</td>" +
          "<td>" + esc(p.method) + '</td><td class="num amount">' + esc(p.amount_label) + "</td><td>" + esc(p.date) + "</td>" +
          '<td><span class="st ' + esc(p.chip) + '">' + esc(p.stateLabel) + "</span></td></tr>"
        ).join("") || emptyRow(7, "No payments.");
      }
    };
    ["#pState", "#pMethod", "#pSearch"].forEach((s) => { const el = $(s); if (el) { el.addEventListener("change", load); el.addEventListener("input", load); } });
    await load();
  }

  async function withdrawals() {
    const list = await api("/api/admin/withdrawals");
    const open = list.filter((w) => w.status === "pending" || w.status === "approved");
    const done = list.filter((w) => w.status === "paid" || w.status === "rejected");
    if ($("#wBody")) {
      $("#wBody").innerHTML = open.map((w) =>
        '<tr><td class="cell-main">' + esc(w.id) + "</td><td>" + esc(w.worker) + '</td><td class="cell-sub">' + esc(w.bank) + "</td>" +
        '<td class="num amount">' + esc(w.amount_label) + "</td><td>" + esc(w.date) + '</td><td class="cell-sub">' + esc(w.stateLabel) + "</td>" +
        '<td class="right nowrap"><button class="btn btn-primary btn-sm w-ok" data-id="' + esc(w.id) + '" type="button">Approve &amp; pay</button> ' +
        '<button class="btn btn-danger btn-sm w-no" data-id="' + esc(w.id) + '" type="button">Hold</button></td></tr>'
      ).join("") || emptyRow(7, "Queue is empty.");
      $$(".w-ok").forEach((b) => b.addEventListener("click", () => act("/api/admin/withdrawals/" + encodeURIComponent(b.dataset.id) + "/action", { action: "paid" }, "Marked paid.")));
      $$(".w-no").forEach((b) => b.addEventListener("click", () => act("/api/admin/withdrawals/" + encodeURIComponent(b.dataset.id) + "/action", { action: "reject" }, "Rejected — wallet restored.")));
    }
    if ($("#wDone")) {
      $("#wDone").innerHTML = done.map((w) =>
        '<tr><td class="cell-main">' + esc(w.id) + "</td><td>" + esc(w.worker) + '</td><td class="cell-sub">' + esc(w.bank) + "</td>" +
        '<td class="num amount">' + esc(w.amount_label) + "</td><td>" + esc(w.date) + "</td>" +
        '<td><span class="st ' + esc(w.chip) + '">' + esc(w.stateLabel) + "</span></td></tr>"
      ).join("") || emptyRow(6, "None yet.");
    }
  }

  async function disputes() {
    const list = await api("/api/admin/disputes");
    const open = list.filter((d) => d.status === "open");
    if ($("#dBody")) {
      $("#dBody").innerHTML = list.map((d) =>
        '<tr><td><span class="cell-main">' + esc(d.id) + '</span><div class="cell-sub">' + esc(d.stateLabel) + "</div></td>" +
        '<td class="cell-sub">' + esc(d.order) + '</td><td class="num amount">' + esc(d.amount_label) + "</td>" +
        "<td>" + esc(d.mine ? "You" : (d.opener || "—")) + '</td><td class="cell-sub">' + esc(d.reason) + "</td>" +
        "<td>" + (d.sla ? '<span class="sla warn">' + esc(d.sla) + "</span>" : "—") + "</td>" +
        '<td class="right"><a class="btn btn-primary btn-sm" href="../dispute-detail.html?id=' + encodeURIComponent(d.id) + '">Open case</a></td></tr>'
      ).join("") || emptyRow(7, "No disputes.");
    }
    const tag = document.querySelector(".ph-actions .tag");
    if (tag) tag.textContent = open.length + " open";
  }

  async function verifications() {
    const list = await api("/api/admin/verifications");
    if ($("#vBody")) {
      $("#vBody").innerHTML = list.map((v) =>
        '<tr><td><span class="row" style="gap:9px"><span class="avatar sm ' + esc(v.tone) + '">' + esc(v.initials) + "</span>" +
        '<span class="cell-main">' + esc(v.name) + "</span></span></td>" +
        '<td class="cell-sub">' + esc(v.docs) + "</td>" +
        '<td><span class="st ' + (v.paid ? "st-green" : "st-gray") + '">' + esc(v.paid_label) + "</span></td>" +
        "<td>" + esc(v.date) + "</td><td><span class=\"sla\">" + esc(v.sla) + "</span></td>" +
        '<td class="right nowrap">' + (v.can_review
          ? '<button class="btn btn-primary btn-sm v-ok" data-id="' + v.id + '" type="button">Approve</button> ' +
            '<button class="btn btn-danger btn-sm v-no" data-id="' + v.id + '" type="button">Reject</button>'
          : '<span class="st st-green">' + esc(v.status) + "</span>") + "</td></tr>"
      ).join("") || emptyRow(6, "Queue is empty.");
      $$(".v-ok").forEach((b) => b.addEventListener("click", () => act("/api/admin/verifications/" + b.dataset.id + "/action", { action: "approve" }, "Badge is live. Identity only — not a skill certificate.")));
      $$(".v-no").forEach((b) => b.addEventListener("click", () => {
        const reason = prompt("Reject reason:") || "Documents unclear — please resubmit";
        act("/api/admin/verifications/" + b.dataset.id + "/action", { action: "reject", reason }, "Rejected.");
      }));
    }
  }

  async function promotions() {
    const d = await api("/api/admin/promotions");
    if ($("#pmKpis")) $("#pmKpis").innerHTML = (d.kpis || []).map(kpiHtml).join("");
    if ($("#pmBody")) {
      $("#pmBody").innerHTML = (d.items || []).map((p) =>
        '<tr><td><span class="row" style="gap:9px"><span class="avatar sm ' + esc(p.tone) + '">' + esc(p.initials) + '</span><span class="cell-main">' + esc(p.name) + "</span></td>" +
        "<td>" + esc(p.plan) + '</td><td class="cell-sub">' + esc(p.skill) + "</td><td>" + esc(p.starts) + "</td><td>" + esc(p.ends) + "</td>" +
        '<td class="num amount">' + esc(p.amount_label) + '</td><td><span class="st ' + esc(p.chip) + '">' + esc(p.stateLabel) + "</span></td>" +
        '<td class="right">' + (p.can_pause
          ? '<button class="btn btn-danger btn-sm p-pause" data-id="' + p.id + '" type="button">Pause</button>'
          : '<span class="cell-sub">—</span>') + "</td></tr>"
      ).join("") || emptyRow(8, "No promotion purchases.");
      $$(".p-pause").forEach((b) => b.addEventListener("click", () => act("/api/admin/promotions/" + b.dataset.id + "/pause", {}, "Paused. Organic ranking is unchanged.")));
    }
  }

  async function categories() {
    const tree = await api("/api/admin/categories");
    if ($("#catTree")) {
      $("#catTree").innerHTML = tree.map((p) =>
        '<div class="queue-card mb-2"><div class="qc-head"><h3>' + esc(p.name) + "</h3>" +
        '<span class="row" style="gap:10px"><span class="tag">' + p.workers + " workers</span>" +
        (p.mode ? '<span class="tag">' + esc(p.mode) + "</span>" : "") + "</span></div>" +
        (p.subs || []).map((s) =>
          '<div class="queue-row"><span class="cell-sub" style="width:220px;flex:none;font-weight:500;color:var(--ink-2)">' + esc(s.name) + "</span>" +
          '<span class="cell-sub" style="flex:1">slug: ' + esc(s.slug) + "</span>" +
          '<span class="st ' + (s.active ? "st-green" : "st-gray") + '">' + (s.active ? "Active" : "Archived") + "</span>" +
          (s.active
            ? '<button class="btn btn-ghost btn-sm c-arch" data-id="' + s.id + '" type="button">Archive</button>'
            : '<button class="btn btn-secondary btn-sm c-arch" data-id="' + s.id + '" data-on="1" type="button">Activate</button>') +
          "</div>"
        ).join("") + "</div>"
      ).join("");
      $$(".c-arch").forEach((b) => b.addEventListener("click", () =>
        act("/api/admin/categories/" + b.dataset.id + "/action", { action: b.dataset.on ? "activate" : "archive" }, "Category updated.")
      ));
    }
  }

  async function reports() {
    const list = await api("/api/admin/reports");
    if ($("#rBody")) {
      $("#rBody").innerHTML = list.map((r) =>
        '<tr><td class="cell-main">' + esc(r.id) + "</td><td>" + esc(r.target) + '</td><td class="cell-sub">' + esc(r.reason) + "</td>" +
        "<td>" + r.count + (r.threshold ? ' <span class="flag">threshold</span>' : "") + "</td><td>" + esc(r.date) + "</td>" +
        '<td><span class="st ' + esc(r.chip) + '">' + esc(r.stateLabel) + "</span></td>" +
        '<td class="right nowrap">' + (r.status === "open"
          ? '<button class="btn btn-ghost btn-sm r-act" data-id="' + esc(r.id) + '" data-act="dismiss" type="button">Dismiss</button> ' +
            '<button class="btn btn-secondary btn-sm r-act" data-id="' + esc(r.id) + '" data-act="warn" type="button">Warn</button> ' +
            '<button class="btn btn-danger btn-sm r-act" data-id="' + esc(r.id) + '" data-act="ban" type="button">Ban</button>'
          : "—") + "</td></tr>"
      ).join("") || emptyRow(7, "No reports.");
      $$(".r-act").forEach((b) => b.addEventListener("click", () =>
        act("/api/admin/reports/" + encodeURIComponent(b.dataset.id) + "/action", { action: b.dataset.act, note: "Staff decision" }, "Updated.")
      ));
    }
  }

  async function audit() {
    const list = await api("/api/admin/audit");
    if ($("#aBody")) {
      $("#aBody").innerHTML = list.map((a) =>
        '<tr><td class="cell-sub nowrap">' + esc(a.when) + '</td><td class="cell-sub">' + esc(a.admin) + '</td>' +
        '<td class="cell-main">' + esc(a.action) + '</td><td class="cell-sub">' + esc(a.target) + "</td>" +
        '<td class="cell-sub">' + esc(a.reason) + '</td><td class="cell-sub mono">' + esc(a.ip) + "</td></tr>"
      ).join("") || emptyRow(6, "No audit rows yet.");
    }
  }

  async function settings() {
    const s = await api("/api/admin/settings");
    const fields = $$(".field input, .field select");
    const map = [
      s.fee_percent, s.min_package_naira, s.max_package_naira, null,
      s.auto_release_days, null, null,
      s.dispute_ack_days, s.dispute_resolve_days, 2, null,
      (s.min_withdrawal_kobo / 100).toLocaleString("en-NG"),
      s.auto_approve_wd_naira.toLocaleString("en-NG"), 3, 3,
      (s.verification_kobo / 100).toLocaleString("en-NG"), 2,
      (s.promo_search_kobo / 100).toLocaleString("en-NG"),
      (s.promo_category_kobo / 100).toLocaleString("en-NG"), 3,
    ];
    fields.forEach((el, i) => { if (map[i] != null && el && !el.disabled) el.value = map[i]; });
    const rec = await api("/api/admin/audit");
    if ($("#setRecent")) {
      $("#setRecent").innerHTML = rec.slice(0, 6).map((a) =>
        '<div class="kv"><span class="k">' + esc(a.when) + '</span><span class="v">' + esc(a.admin) + " · " + esc(a.action) + "</span></div>"
      ).join("") || '<p class="tiny faint">No recent staff actions.</p>';
    }
    if ($("#saveAll")) {
      $("#saveAll").addEventListener("click", () => {
        const body = {
          fee_percent: fields[0] && fields[0].value,
          min_package_naira: fields[1] && fields[1].value,
          max_package_naira: fields[2] && fields[2].value,
          auto_release_days: fields[4] && fields[4].value,
          dispute_ack_days: fields[7] && fields[7].value,
          dispute_resolve_days: fields[8] && fields[8].value,
          min_withdrawal_naira: fields[11] && fields[11].value,
          auto_approve_wd_naira: fields[12] && fields[12].value,
          verification_naira: fields[15] && fields[15].value,
          promo_search_naira: fields[17] && fields[17].value,
          promo_category_naira: fields[18] && fields[18].value,
        };
        act("/api/admin/settings", body, "Settings saved. Audited.");
      });
    }
  }

  async function support() {
    const list = await api("/api/admin/support");
    if ($("#tList")) {
      $("#tList").innerHTML = list.map((t) =>
        '<div class="card card-pad"><div class="row spread" style="align-items:flex-start;gap:10px"><div class="row" style="gap:10px">' +
        '<span class="avatar sm ' + esc(t.tone) + '">' + esc(t.initials) + "</span><div>" +
        '<div class="bold" style="font-size:14px">' + esc(t.name) + (t.flags ? ' <span class="flag">' + t.flags + " flags</span>" : "") + "</div>" +
        '<div class="tiny faint">' + esc(t.subject) + " · " + esc(t.date) + "</div></div></div>" +
        '<span class="st ' + (t.status === "open" ? "st-amber" : "st-green") + '">' + esc(t.status) + "</span></div>" +
        '<p class="small mt-2" style="color:var(--ink-2);line-height:1.6">' + esc(t.body || t.subject) + "</p>" +
        '<div class="field mt-2"><textarea class="textarea t-body" data-id="' + t.id + '" placeholder="Reply from support@skilvi.ng"></textarea></div>' +
        '<div class="row mt-2" style="gap:8px"><button class="btn btn-primary btn-sm t-send" data-id="' + t.id + '" type="button">Reply</button>' +
        '<a class="btn btn-secondary btn-sm" href="users.html">View account</a></div></div>'
      ).join("") || '<div class="card card-pad"><p class="tiny faint">Inbox is empty.</p></div>';
      $$(".t-send").forEach((b) => b.addEventListener("click", () => {
        const ta = $('.t-body[data-id="' + b.dataset.id + '"]');
        act("/api/admin/support/" + b.dataset.id + "/reply", { body: (ta && ta.value) || "" }, "Reply sent.");
      }));
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    const run = async () => {
      try {
        if (file === "index.html" || file === "" || file === "admin") await dash();
        else if (file === "users.html") await users();
        else if (file === "orders.html") await orders();
        else if (file === "payments.html") await payments();
        else if (file === "withdrawals.html") await withdrawals();
        else if (file === "disputes.html") await disputes();
        else if (file === "verification.html") await verifications();
        else if (file === "promotions.html") await promotions();
        else if (file === "categories.html") await categories();
        else if (file === "reports.html") await reports();
        else if (file === "audit-log.html") await audit();
        else if (file === "settings.html") await settings();
        else if (file === "support.html") await support();
      } catch (err) {
        if (!gate(err)) toast(err.message || "Could not load the console.", "error");
      }
    };
    run();
  });
})();
