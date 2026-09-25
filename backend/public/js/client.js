(function () {
  "use strict";
  const api = (window.SkApi && window.SkApi.api) || (async (p) => (await fetch(p)).json().then((b) => b.data));
  const toast = (window.SkApi && window.SkApi.toast) || (window.Sk && window.Sk.toast) || ((m) => console.log(m));
  const busy = (window.SkApi && window.SkApi.busy) || function () {};
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const I = window.SkIconSvg || {};
  const ngn = (window.Sk && window.Sk.ngn) || ((n) => "₦" + Number(n).toLocaleString("en-NG"));
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

  async function postJob() {
    const form = $("#postJobForm");
    if (!form) return;
    if (window.SkGeo) {
      window.SkGeo.bind({ country: "#pjCountry", state: "#pjState", city: "#pjCity", values: { country: "Nigeria" } });
    }
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const fields = form.querySelectorAll("input:not([type=radio]):not([type=checkbox]), select, textarea");
      const title = form.querySelector("[name=title]") || fields[0];
      const category = form.querySelector("[name=category]") || fields[1];
      const budget = form.querySelector("[name=budget]") || fields[2];
      const desc = form.querySelector("[name=description]") || form.querySelector("textarea");
      const state = form.querySelector("[name=state]") || fields[3];
      const city = form.querySelector("[name=city]") || fields[4];
      const deadline = form.querySelector("[name=deadline]") || form.querySelector("input[type=date]");
      const maxEl = form.querySelector("[name=max_proposals]") || fields[fields.length - 1];
      const btn = form.querySelector("button[type=submit]");
      const body = {
        title: (title && title.value) || "",
        category: (category && category.value) || "",
        budget_naira: Number(String((budget && budget.value) || "0").replace(/\D/g, "")),
        budget_type: ($("input[name=pj1]:checked") && $("#pj_1_1") && $("#pj_1_1").checked) ? "negotiable" : "fixed",
        description: (desc && desc.value) || "",
        work_mode: $("#pj_2_0") && $("#pj_2_0").checked ? "remote" : ($("#pj_2_2") && $("#pj_2_2").checked ? "hybrid" : "on-site"),
        state: (state && state.value) || "",
        city: (city && city.value) || "",
        deadline: (deadline && deadline.value) || "",
        max_proposals: Number(String((maxEl && maxEl.value) || "").replace(/\D/g, "")) || 0,
      };
      busy(btn, true);
      try {
        const job = await api("/api/jobs", { body });
        toast("Job " + job.id + " is live.", "success");
        location.href = "job-detail.html?id=" + encodeURIComponent(job.id);
      } catch (err) {
        if (!gate(err)) {
          if (window.SkApi && window.SkApi.showFieldErrors) window.SkApi.showFieldErrors(err, form);
          toast(err.message, "error");
        }
      } finally {
        busy(btn, false);
      }
    });
  }

  async function clientDash() {
    if (!$("#cdStats") && !$("#cdOrders")) return;
    const d = await api("/api/me/dashboard");
    const s = d.stats || {};
    const Ico = I;
    const stats = [
      [Ico.briefcase || "", "Active orders", String(s.active_orders || 0), (s.needs_approval || 0) + " need your approval"],
      [Ico.wallet2 || "", "Spent on Skilvi", ngn(s.spent_naira || 0), (d.orders || []).length + " orders"],
      [Ico.jobs || "", "Open jobs", String(s.open_jobs || 0), (s.open_proposals || 0) + " proposals"],
      [Ico.shield || "", "Escrow-protected", "100%", "of your payments"],
    ];
    if ($("#cdStats")) {
      $("#cdStats").innerHTML = stats.map(([i, l, v, delta]) =>
        '<div class="stat-card"><div class="label"><span class="stat-icon">' + i + "</span>" + l + '</div><div class="value">' + v + '</div><div class="delta">' + delta + "</div></div>"
      ).join("");
    }
    const orders = d.orders || [];
    const row = (o) =>
      '<tr><td><span class="cell-main">' + esc(o.title) + '</span><div class="cell-sub">' + esc(o.id) + "</div></td>" +
      '<td><span class="row" style="gap:8px"><span class="avatar sm ' + esc(o.tone) + '">' + esc(init(o.party)) + "</span>" + esc(o.party) + "</span></td>" +
      '<td class="num amount">' + esc(o.amount_label) + "</td><td>" + esc(o.date) + "</td>" +
      '<td><span class="st ' + esc(o.chip) + '">' + esc(o.stateLabel) + "</span></td>" +
      '<td class="right nowrap"><a class="btn btn-secondary btn-sm" href="order-detail.html?id=' + encodeURIComponent(o.id) + '">Open</a></td></tr>';
    if ($("#cdOrders")) $("#cdOrders").innerHTML = orders.map(row).join("") || '<tr><td colspan="6" class="muted">No orders yet.</td></tr>';
    if ($("#cdRecent")) {
      $("#cdRecent").innerHTML = orders.slice(0, 3).map((o) =>
        '<div class="queue-row" style="padding:10px 0"><span class="avatar sm ' + esc(o.tone) + '">' + esc(init(o.party)) + "</span>" +
        '<div class="qr-main"><div class="qr-t">' + esc(o.title) + '</div><div class="qr-s">' + esc(o.id) + " · " + esc(o.amount_label) + "</div></div>" +
        '<span class="st ' + esc(o.chip) + '">' + esc(o.stateLabel) + '</span><a class="link" style="font-size:12.5px" href="order-detail.html?id=' + encodeURIComponent(o.id) + '">Open</a></div>'
      ).join("") || '<p class="tiny faint">No orders yet. Post a job to get started.</p>';
    }
    const jobsTbody = document.querySelector('[data-panel="jobs"] tbody');
    if (jobsTbody) {
      const jobs = d.jobs || [];
      jobsTbody.innerHTML = jobs.map((j) =>
        '<tr><td><span class="cell-main">' + esc(j.title) + '</span><div class="cell-sub">' + esc(j.mode) + " · " + esc(j.loc) + "</div></td>" +
        '<td class="num amount">' + esc(j.budget_label) + "</td><td>" + esc(j.time) + "</td>" +
        "<td><b>" + j.proposals + "</b></td>" +
        '<td><span class="st ' + (j.status === "open" ? "st-royal" : "st-green") + '">' + esc(j.status) + "</span></td>" +
        '<td class="right"><a class="btn btn-primary btn-sm" href="job-detail.html?id=' + encodeURIComponent(j.id) + '">Open</a></td></tr>'
      ).join("") || '<tr><td colspan="6" class="muted">No jobs posted yet.</td></tr>';
    }
    const tabCount = $$(".tabs .tab .count");
    if (tabCount[0]) tabCount[0].textContent = orders.length;
    if (tabCount[1]) tabCount[1].textContent = (d.jobs || []).length;
    const actionHost = document.querySelector('[data-panel="overview"] .queue-card');
    if (actionHost) {
      const needs = (d.orders || []).filter((o) => o.status === "completion_submitted" || o.ui_status === "delivered");
      const jobsNeed = (d.jobs || []).filter((j) => j.status === "open" && (j.proposals || 0) > 0);
      const bits = [];
      needs.forEach((o) => {
        bits.push('<div class="queue-row"><span class="avatar sm ' + esc(o.tone) + '">' + esc(init(o.party)) + "</span>" +
          '<div class="qr-main"><div class="qr-t">' + esc(o.id) + " · " + esc(o.title) + "</div>" +
          '<div class="qr-s">Review the work and approve to release ' + esc(o.amount_label) + "</div></div>" +
          '<span class="st ' + esc(o.chip) + '">' + esc(o.stateLabel) + "</span>" +
          '<a class="btn btn-primary btn-sm" href="order-detail.html?id=' + encodeURIComponent(o.id) + '">Review</a></div>');
      });
      jobsNeed.forEach((j) => {
        bits.push('<div class="queue-row"><div class="qr-main"><div class="qr-t">Job · ' + esc(j.title) + "</div>" +
          '<div class="qr-s">' + j.proposals + " proposal" + (j.proposals === 1 ? "" : "s") + "</div></div>" +
          '<a class="btn btn-secondary btn-sm" href="job-detail.html?id=' + encodeURIComponent(j.id) + '">Review</a></div>');
      });
      actionHost.innerHTML = bits.join("") || '<p class="tiny faint">Nothing needs you right now.</p>';
    }
    const payBody = document.querySelector('[data-panel="payments"] tbody');
    if (payBody) {
      const pays = d.payments || [];
      payBody.innerHTML = pays.map((p) =>
        "<tr><td class=\"cell-main\">" + esc(p.code) + "</td><td class=\"cell-sub\">" + esc(p.order || "—") +
        "</td><td>" + esc(p.method) + '</td><td class="num amount">' + esc(p.amount_label) + "</td><td>" + esc(p.date) +
        '</td><td><span class="st ' + esc(p.chip) + '">' + esc(p.stateLabel) + "</span></td></tr>"
      ).join("") || '<tr><td colspan="6" class="muted">No payments yet.</td></tr>';
    }
    const tab = params.get("tab");
    if (tab) {
      const btn = document.querySelector('[data-tab="' + tab + '"]');
      if (btn) btn.click();
    }
  }

  function setStateBlocks(ui) {
    $$(".order-state-block").forEach((el) => {
      el.style.display = el.getAttribute("data-state") === ui ? "" : "none";
    });
  }

  function paintTimeline(o) {
    const host = $("#odTimeline");
    if (!host) return;
    const ui = o.ui_status || "";
    const doneThrough = {
      awaiting_payment: 0,
      paid: 1,
      in_progress: 2,
      delivered: 3,
      completed: 5,
      cancelled: 0,
      disputed: 2,
    }[ui];
    const currentAt = {
      awaiting_payment: 1,
      paid: 2,
      in_progress: 3,
      delivered: 4,
      completed: -1,
      cancelled: -1,
      disputed: -1,
    }[ui];
    const steps = [
      ["Order created", "The order was opened."],
      ["Payment verified — held in escrow", ui === "awaiting_payment" ? "Not paid yet. Pay to fund escrow." : "Held by Skilvi until you approve."],
      ["Work started", "The worker marks the order as started."],
      ["Delivered for approval", "The worker submits work for your approval."],
      ["Awaiting client approval", "Payment releases 5 business days after delivery if you do not respond."],
      ["Completed & settled", "Worker share after 10% commission · Skilvi fee"],
    ];
    if (ui === "cancelled") {
      steps.push(["Cancelled", o.escrow === "Refunded" ? "Escrow refunded." : "Order closed without payment."]);
    }
    if (ui === "disputed") {
      steps.push(["In dispute", "Escrow is frozen until Skilvi decides."]);
    }
    host.innerHTML = steps.map((s, i) => {
      let cls = "tl-item";
      if (ui === "cancelled" && i === steps.length - 1) cls += " current";
      else if (ui === "disputed" && i === steps.length - 1) cls += " current";
      else if (i === currentAt) cls += " current";
      else if (typeof doneThrough === "number" && i <= doneThrough && i !== currentAt) cls += " done";
      return '<div class="' + cls + '"><span class="tl-dot"></span><div class="tl-t">' + s[0] + '</div><div class="tl-s">' + s[1] + "</div></div>";
    }).join("");
  }

  async function orderDetail() {
    const id = params.get("id") || params.get("order") || "";
    if (!id) return;
    let o;
    try {
      o = await api("/api/orders/" + encodeURIComponent(id));
    } catch (err) {
      if (gate(err)) return;
      toast(err.message, "error");
      return;
    }
    const crumb = $("main .small.muted");
    if (crumb) crumb.innerHTML = '<a href="client-dashboard.html">My orders</a> / ' + esc(o.id);
    const h1 = $("h1, .ph-title");
    if (h1) h1.textContent = o.title;
    const chip = $("#orderStateChip");
    if (chip) {
      chip.className = "st " + o.chip;
      chip.textContent = o.stateLabel;
    }
    const sub = $(".ph-sub");
    if (sub) sub.textContent = "Order " + o.id + " · " + o.date;
    const price = $(".sc-price");
    if (price) price.textContent = o.amount_label;
    const kvs = $$("aside .kv .v");
    if (kvs[0]) kvs[0].textContent = o.worker_name + (o.worker_rating ? " · " + o.worker_rating + "★" : "");
    if (kvs[1]) kvs[1].textContent = o.client_name;
    if (kvs[2]) kvs[2].innerHTML = '<span class="st ' + esc(o.chip) + '" style="padding:1px 8px">' + esc(o.escrow) + "</span>";
    if (kvs[3]) kvs[3].textContent = o.worker_net + " worker · " + o.fee_label + " Skilvi";
    if ($("#odEscrow")) {
      let note = "Nothing is in escrow yet. Pay to fund this order.";
      if (o.escrow === "Held") note = esc(o.amount_label) + " held by Skilvi until you approve.";
      else if (o.escrow === "Released") note = esc(o.amount_label) + " released to the worker.";
      else if (o.escrow === "Refunded") note = "Escrow was refunded.";
      else if (o.escrow === "Frozen") note = esc(o.amount_label) + " frozen while this order is in dispute.";
      else if (o.escrow === "Unpaid") note = "Unpaid — " + esc(o.amount_label) + " is not in escrow yet.";
      $("#odEscrow").innerHTML = (I.shield || "") + "<span><b>Escrow status:</b> " + note + "</span>";
    }
    const demo = $("#stateDemo");
    if (demo && demo.closest(".card")) demo.closest(".card").style.display = "none";
    paintTimeline(o);
    setStateBlocks(o.ui_status);
    const deliv = $("#odDeliverables");
    if (deliv) {
      const hasDelivery = o.ui_status === "delivered" || o.ui_status === "completed";
      deliv.hidden = !hasDelivery;
      if (hasDelivery) {
        $$(".deliverable", deliv).forEach((el) => { el.style.display = "none"; });
        let noteEl = $("#odDelivNote");
        if (!noteEl) {
          noteEl = document.createElement("p");
          noteEl.id = "odDelivNote";
          noteEl.className = "small muted";
          deliv.appendChild(noteEl);
        }
        noteEl.textContent = o.note || "The worker marked this delivered. No files were attached.";
      }
    }

    const msgBtn = document.querySelector('.ph-actions a[href="messages.html"]');
    if (msgBtn) {
      msgBtn.href = o.conversation_id
        ? "messages.html?id=" + o.conversation_id
        : "messages.html";
    }
    const report = document.querySelector('.ph-actions .btn-danger');
    if (report) {
      report.removeAttribute("data-toast");
      if (o.actions && o.actions.dispute) {
        report.addEventListener("click", () => { location.href = "disputes.html?order=" + encodeURIComponent(o.id); });
      } else if (o.dispute) {
        report.textContent = "View dispute";
        report.addEventListener("click", () => { location.href = "dispute-detail.html?id=" + encodeURIComponent(o.dispute.id); });
      } else {
        report.style.display = "none";
      }
    }
    if (o.dispute) {
      const link = document.querySelector('[data-state="disputed"] a.btn-secondary');
      if (link) link.href = "dispute-detail.html?id=" + encodeURIComponent(o.dispute.id);
    }
    const a = o.actions || {};
    if (a.pay && o.checkout_url) {
      const pay = $("#odPayNow");
      if (pay) pay.href = o.checkout_url;
      toast("This order still needs payment before work can start.", "error");
    } else if (o.ui_status === "awaiting_payment" && o.viewer && o.viewer.is_worker) {
      const box = document.querySelector('[data-state="awaiting_payment"] .action-panel');
      if (box) {
        const t = box.querySelector(".ap-title");
        const p = box.querySelector("p");
        if (t) t.textContent = "Waiting on payment";
        if (p) p.textContent = "The client has not funded escrow yet. They will be asked to pay each time they open this order.";
        const row = box.querySelector(".row");
        if (row) row.innerHTML = '<a class="btn btn-secondary" href="' + (o.conversation_id ? "messages.html?id=" + o.conversation_id : "messages.html") + '">Message the client</a>';
      }
    }
    const workerPanel = o.viewer && o.viewer.is_worker && (a.start || a.submit);
    if (workerPanel) {
      const host = document.querySelector('.order-state-block[data-state="' + o.ui_status + '"] .row') || $("#orderActions");
      if (host) {
        host.innerHTML = a.start
          ? '<button class="btn btn-primary" type="button" id="odStart">Mark as started</button>'
          : '<textarea class="textarea" id="odNote" placeholder="What did you deliver?" style="min-height:70px;flex:1"></textarea><button class="btn btn-primary" type="button" id="odSubmit">Submit for approval</button>';
      }
    }
    const bind = async (sel, path, body, ok) => {
      const el = $(sel);
      if (!el) return;
      el.addEventListener("click", async () => {
        try {
          const data = await api("/api/orders/" + encodeURIComponent(o.id) + "/" + path, { body: body || {} });
          toast(ok, "success");
          location.href = "order-detail.html?id=" + encodeURIComponent(data.id);
        } catch (err) { toast(err.message, "error"); }
      });
    };
    if (a.approve) {
      const btn = document.querySelector('[data-state="delivered"] .btn-primary');
      if (btn) {
        btn.removeAttribute("data-msg");
        btn.addEventListener("click", async (e) => {
          e.preventDefault();
          try {
            await api("/api/orders/" + encodeURIComponent(o.id) + "/approve", { body: {} });
            toast("Payment released to the worker's wallet.", "success");
            location.reload();
          } catch (err) { toast(err.message, "error"); }
        });
      }
    }
    if (a.revision) {
      const btn = document.querySelector('[data-state="delivered"] .btn-secondary');
      if (btn) {
        btn.removeAttribute("data-msg");
        btn.addEventListener("click", async (e) => {
          e.preventDefault();
          try {
            await api("/api/orders/" + encodeURIComponent(o.id) + "/revision", { body: {} });
            toast("Revision requested.", "success");
            location.reload();
          } catch (err) { toast(err.message, "error"); }
        });
      }
    }
    if (a.cancel) {
      const unpaidCancel = $("#odCancelUnpaid");
      if (unpaidCancel) {
        unpaidCancel.addEventListener("click", async (e) => {
          e.preventDefault();
          try {
            await api("/api/orders/" + encodeURIComponent(o.id) + "/cancel", { body: {} });
            toast("Order cancelled.", "success");
            location.reload();
          } catch (err) { toast(err.message, "error"); }
        });
      }
      const btn = document.querySelector('[data-state="paid"] .btn-danger');
      if (btn) {
        btn.removeAttribute("data-msg");
        btn.addEventListener("click", async (e) => {
          e.preventDefault();
          try {
            await api("/api/orders/" + encodeURIComponent(o.id) + "/cancel", { body: {} });
            toast("Order cancelled. Escrow refunded.", "success");
            location.reload();
          } catch (err) { toast(err.message, "error"); }
        });
      }
    }
    bind("#odStart", "start", {}, "Work marked as started.");
    const subBtn = $("#odSubmit");
    if (subBtn) {
      subBtn.addEventListener("click", async () => {
        try {
          await api("/api/orders/" + encodeURIComponent(o.id) + "/submit", { body: { note: ($("#odNote") && $("#odNote").value) || "" } });
          toast("Delivery submitted. Waiting on the client.", "success");
          location.reload();
        } catch (err) { toast(err.message, "error"); }
      });
    }
    if (a.review) {
      const go = document.querySelector('[data-state="completed"] .btn-primary');
      if (go) {
        go.removeAttribute("data-msg");
        go.addEventListener("click", (e) => {
          e.preventDefault();
          location.href = "review.html?id=" + encodeURIComponent(o.id);
        });
      }
    }
  }

  async function reviewPage() {
    const id = params.get("id") || params.get("order") || "";
    if (!id || !$("#rvSubmit")) return;
    const o = await api("/api/orders/" + encodeURIComponent(id));
    const sub = $(".ph-sub");
    if (sub) sub.textContent = "Order " + o.id + " · " + o.title;
    if ($("#rvWho")) {
      $("#rvWho").innerHTML =
        '<div class="row" style="gap:12px;align-items:center;flex-wrap:wrap">' +
        '<span class="avatar ' + esc(o.worker_tone) + '">' + esc(init(o.worker_name)) + "</span>" +
        '<div class="grow"><div class="bold" style="font-size:15px">' + esc(o.worker_name) + "</div>" +
        '<span class="tiny faint">Rate the completed work</span></div>' +
        '<div class="right"><div class="amount" style="font-size:16px">' + esc(o.amount_label) + '</div><div class="tiny faint">order value</div></div></div>';
    }
    let score = 0;
    const labels = { 1: "Not satisfied", 2: "Below expectations", 3: "It was okay", 4: "Very good", 5: "Excellent" };
    const starBtns = $$("#rvStars .rv-star");
    const paint = () => {
      starBtns.forEach((b) => { b.style.color = Number(b.dataset.v) <= score ? "#F59E0B" : "var(--line-2)"; });
      if ($("#rvScore")) $("#rvScore").textContent = score ? score + ".0" : "—";
      if ($("#rvLabel")) $("#rvLabel").textContent = score ? labels[score] : "Tap a star to rate";
      validate();
    };
    starBtns.forEach((b) => b.addEventListener("click", () => { score = Number(b.dataset.v); paint(); }));
    const text = $("#rvText");
    function validate() {
      if ($("#rvSubmit")) $("#rvSubmit").disabled = !(score >= 1 && text && text.value.trim().length >= 20);
    }
    if (text) text.addEventListener("input", () => {
      if ($("#rvCount")) $("#rvCount").textContent = text.value.length + " / 1000";
      validate();
    });
    $$("#rvTags .rv-tag").forEach((t) => t.addEventListener("click", () => t.classList.toggle("active")));
    $("#rvSubmit").addEventListener("click", async () => {
      try {
        const tags = $$("#rvTags .rv-tag.active").map((t) => t.textContent.trim());
        await api("/api/orders/" + encodeURIComponent(id) + "/review", { body: { rating: score, comment: text.value, tags } });
        if ($("#rvForm")) $("#rvForm").style.display = "none";
        if ($("#rvDone")) $("#rvDone").style.display = "";
        toast("Review published.", "success");
      } catch (err) { toast(err.message, "error"); }
    });
  }

  async function workerJobs() {
    if (!$("#wjFeed") && !$("#wjProps")) return;
    const jobs = await api("/api/jobs?per=24");
    const Ico = I;
    const jobItems = jobs.items || [];
    if ($("#wjCountJobs")) $("#wjCountJobs").textContent = String(jobs.total != null ? jobs.total : jobItems.length);
    if ($("#wjFeed")) {
      $("#wjFeed").innerHTML = (jobs.items || []).map((j) =>
        '<div class="job-card"><div class="jc-top"><h3><a href="job-detail.html?id=' + esc(j.id) + '">' + esc(j.title) + "</a></h3>" +
        '<span class="jc-budget">' + esc(j.budget_label) + "</span></div>" +
        '<div class="jc-meta"><span>' + (j.mode === "remote" ? (Ico.code || "") : (Ico.truck || "")) + esc(j.mode) + "</span>" +
        "<span>" + (Ico.pin || "") + esc(j.loc) + "</span><span>" + (Ico.clock || "") + esc(j.time) + "</span></div>" +
        '<div class="jc-foot"><span class="jc-proposals">' + j.proposals + " proposals so far</span>" +
        '<a class="btn btn-primary btn-sm" href="job-detail.html?id=' + encodeURIComponent(j.id) + '">View &amp; apply</a></div></div>'
      ).join("") || '<p class="tiny faint">No open jobs right now.</p>';
    }
    const props = await api("/api/proposals/mine");
    const propList = Array.isArray(props) ? props : [];
    if ($("#wjCountProps")) $("#wjCountProps").textContent = String(propList.length);
    const stMap = { sent: "st-amber", accepted: "st-green", rejected: "st-gray", withdrawn: "st-gray" };
    if ($("#wjProps")) {
      $("#wjProps").innerHTML = propList.map((p) =>
        '<tr><td><span class="cell-main">' + esc(p.title) + '</span><div class="cell-sub">' + esc(p.job) + "</div></td>" +
        '<td class="num amount">' + esc(p.bid_label) + "</td><td>" + (p.days ? p.days + " days" : "—") + "</td><td>" + esc(p.date) + "</td>" +
        '<td><span class="st ' + (stMap[p.status] || "st-gray") + '">' + esc(p.status) + "</span></td>" +
        '<td class="right nowrap"><a class="btn btn-secondary btn-sm" href="job-detail.html?id=' + encodeURIComponent(p.job) + '">View</a>' +
        (p.status === "sent" ? ' <button class="btn btn-ghost btn-sm prop-wd" type="button" data-id="' + p.id + '">Withdraw</button>' : "") +
        "</td></tr>"
      ).join("") || '<tr><td colspan="6" class="muted">You have not sent a proposal yet.</td></tr>';
      $$(".prop-wd").forEach((b) => b.addEventListener("click", async () => {
        try {
          await api("/api/proposals/" + b.getAttribute("data-id") + "/withdraw", { body: {} });
          toast("Proposal withdrawn.", "success");
          location.reload();
        } catch (err) { toast(err.message, "error"); }
      }));
    }
  }

  function orderRow(o) {
    const next = o.status === "funded" ? "Mark started" : (o.status === "in_progress" ? "Submit delivery" : (o.status === "completion_submitted" ? "Wait for client" : "—"));
    return '<tr><td><span class="cell-main">' + esc(o.title) + '</span><div class="cell-sub">' + esc(o.id) + " · " + esc(o.client_name) + "</div></td>" +
      '<td class="num amount">' + esc(o.amount_label) + "</td>" +
      '<td><span class="tag">' + esc(o.escrow) + "</span></td>" +
      '<td><span class="st ' + esc(o.chip) + '">' + esc(o.stateLabel) + "</span></td>" +
      '<td class="cell-sub">' + next + "</td>" +
      '<td class="right nowrap"><a class="btn btn-secondary btn-sm" href="order-detail.html?id=' + encodeURIComponent(o.id) + '">Open</a></td></tr>';
  }

  async function workerOrders() {
    if (!$("#woTable")) return;
    const orders = await api("/api/orders?role=worker");
    const head = '<table class="table"><thead><tr><th>Order</th><th class="num">Amount</th><th>Escrow</th><th>Status</th><th>Next action</th><th></th></tr></thead><tbody>';
    const fill = (el, rows) => {
      if (!el) return;
      el.innerHTML = head + (rows.map(orderRow).join("") || '<tr><td colspan="6" class="muted">Nothing here.</td></tr>') + "</tbody></table>";
    };
    const list = Array.isArray(orders) ? orders : [];
    fill($("#woTable"), list);
    fill($("#woTableActive"), list.filter((o) => o.status === "in_progress"));
    fill($("#woTableDelivered"), list.filter((o) => o.status === "completion_submitted"));
    fill($("#woTableCompleted"), list.filter((o) => o.status === "released"));
    fill($("#woTableDisputed"), list.filter((o) => o.status === "disputed"));
    const set = (id, n) => { if ($(id)) $(id).textContent = String(n); };
    set("#woCountAll", list.length);
    set("#woCountActive", list.filter((o) => o.status === "in_progress").length);
    set("#woCountDelivered", list.filter((o) => o.status === "completion_submitted").length);
    set("#woCountCompleted", list.filter((o) => o.status === "released").length);
    set("#woCountDisputed", list.filter((o) => o.status === "disputed").length);
  }

  document.addEventListener("DOMContentLoaded", () => {
    const run = async () => {
      try {
        if (page === "post-job") await postJob();
        else if (page === "client-dash") await clientDash();
        else if (page === "order") await orderDetail();
        else if (page === "review") await reviewPage();
        else if (page === "worker-jobs") await workerJobs();
        else if (page === "worker-orders") await workerOrders();
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
