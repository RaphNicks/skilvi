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

  async function disputesPage() {
    const list = await api("/api/disputes");
    let host = $("#dspList");
    if (!host) {
      host = document.querySelector(".grid.grid-2 .stack");
    }
    if (host) {
      host.innerHTML = list.map((d) =>
        '<div class="card card-pad"><div class="row spread" style="align-items:flex-start;gap:10px"><div>' +
        '<div class="row" style="gap:8px;flex-wrap:wrap"><span class="bold" style="font-size:14.5px">' + esc(d.id) + " · " + esc(d.order) + "</span>" +
        '<span class="st ' + esc(d.chip) + '">' + esc(d.stateLabel) + "</span></div>" +
        '<p class="small muted mt-1">' + esc(d.title) + " · " + esc(d.amount_label) + " · " + esc(d.date) +
        (d.mine ? " · opened by you" : "") + "</p></div>" +
        (d.sla ? '<span class="sla warn">' + esc(d.sla) + "</span>" : "") + "</div>" +
        '<div class="kv mt-2"><span class="k">Reason</span><span class="v">' + esc(d.reason) + "</span></div>" +
        (d.resolution ? '<div class="kv"><span class="k">Outcome</span><span class="v">' + esc(d.resolution) + "</span></div>" : "") +
        '<div class="row mt-2" style="gap:8px"><a class="btn btn-primary btn-sm" href="dispute-detail.html?id=' + encodeURIComponent(d.id) + '">Open case</a>' +
        '<a class="btn btn-secondary btn-sm" href="messages.html">Messages</a></div></div>'
      ).join("") || '<div class="card card-pad"><p class="tiny faint">No disputes. Keep talking in the order thread first — most issues never need this.</p></div>';
    }
    const form = $("#disputeForm");
    if (!form) return;
    const selects = form.querySelectorAll("select");
    const ta = form.querySelector("textarea");
    if (selects[0] && !selects[0].name) selects[0].name = "order_id";
    if (selects[1] && !selects[1].name) selects[1].name = "reason";
    if (ta && !ta.name) ta.name = "description";
    try {
      const orders = await api("/api/orders");
      const openable = orders.filter((o) => ["funded", "in_progress", "completion_submitted"].indexOf(o.status) !== -1);
      if (selects[0]) {
        selects[0].innerHTML = '<option value="">Select an order with active escrow…</option>' +
          openable.map((o) => '<option value="' + esc(o.id) + '">' + esc(o.id) + " · " + esc(o.title) + " · " + esc(o.amount_label) + "</option>").join("");
        const pre = params.get("order");
        if (pre) selects[0].value = pre;
      }
    } catch (e) { /* keep static options */ }
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const order = (selects[0] && selects[0].value) || "";
      const reason = (selects[1] && (selects[1].options[selects[1].selectedIndex] ? selects[1].options[selects[1].selectedIndex].text : selects[1].value)) || "";
      const description = (ta && ta.value) || "";
      const btn = form.querySelector('button[type="submit"]');
      if (!order) { toast("Pick an order.", "error"); return; }
      busy(btn, true);
      try {
        const d = await api("/api/orders/" + encodeURIComponent(order) + "/dispute", { body: { reason, description } });
        toast("Dispute " + d.id + " opened. Escrow is frozen.", "success");
        location.href = "dispute-detail.html?id=" + encodeURIComponent(d.id);
      } catch (err) {
        if (!gate(err)) toast(err.message, "error");
      } finally {
        busy(btn, false);
      }
    });
  }

  async function disputeDetail() {
    const id = params.get("id") || params.get("dsp") || "";
    if (!id) return;
    let d;
    try {
      d = await api("/api/disputes/" + encodeURIComponent(id));
    } catch (err) {
      if (!gate(err)) toast(err.message, "error");
      return;
    }
    const crumb = $("main .small.muted");
    if (crumb) crumb.innerHTML = '<a href="disputes.html">Disputes</a> / ' + esc(d.id);
    const h1 = $(".ph-title");
    if (h1) h1.textContent = d.id + " · " + d.order;
    const chip = document.querySelector(".page-head .st");
    if (chip) { chip.className = "st " + d.chip; chip.textContent = d.stateLabel; }
    const sub = $(".ph-sub");
    if (sub) sub.textContent = d.title + " · " + d.amount_label + " frozen · opened " + d.date;
    const note = $("#caseNote");
    if (note) {
      note.innerHTML = (I.flag || "") + "<span><b>Escrow frozen:</b> " + esc(d.amount_label) +
        " cannot be released, withdrawn or refunded while this case is open.</span>";
    }
    const tl = document.querySelector(".timeline");
    if (tl && d.timeline) {
      tl.innerHTML = d.timeline.map((t) =>
        '<div class="tl-item' + (t.done ? " done" : "") + (t.current ? " current" : "") + '"><span class="tl-dot"></span>' +
        '<div class="tl-t">' + esc(t.title) + '</div><div class="tl-s">' + esc(t.sub) + "</div></div>"
      ).join("");
    }
    const view = document.querySelector('.ph-actions a[href="order-detail.html"]');
    if (view) view.href = "order-detail.html?id=" + encodeURIComponent(d.order);
    const msg = document.querySelector('.ph-actions a[href="messages.html"]');
    if (msg && d.conversation_id) msg.href = "messages.html?id=" + d.conversation_id;
    const stack = document.querySelector(".order-grid .stack");
    if (stack && !$("#dspThread")) {
      const box = document.createElement("div");
      box.className = "card card-pad";
      box.id = "dspThread";
      stack.appendChild(box);
    }
    if ($("#dspThread")) {
      $("#dspThread").innerHTML = "<h3 style=\"font-size:14px\">Case messages</h3>" +
        (d.messages || []).map((m) =>
          '<div class="kv mt-2"><span class="k">' + esc(m.name) + " · " + esc(m.time) + '</span><span class="v">' + esc(m.body) + "</span></div>"
        ).join("") +
        (d.can_reply
          ? '<div class="field mt-2"><label>Reply</label><textarea class="textarea" id="dspReply" placeholder="Add evidence or a reply. Stay on Skilvi."></textarea></div>' +
            '<button class="btn btn-primary btn-sm mt-2" type="button" id="dspSend">Send reply</button>'
          : "");
      if ($("#dspSend")) {
        $("#dspSend").addEventListener("click", async () => {
          try {
            await api("/api/disputes/" + encodeURIComponent(d.id) + "/reply", { body: { body: ($("#dspReply") && $("#dspReply").value) || "" } });
            toast("Reply sent.", "success");
            location.reload();
          } catch (err) { toast(err.message, "error"); }
        });
      }
    }
    const adminCard = document.querySelector("aside .card.card-pad[style]");
    if (adminCard) {
      if (!d.can_resolve) {
        adminCard.style.display = "none";
      } else {
        const btn = adminCard.querySelector(".btn-primary");
        const sel = adminCard.querySelector("select");
        const ta = adminCard.querySelector("textarea");
        if (btn) {
          btn.removeAttribute("data-toast");
          btn.addEventListener("click", async () => {
            const label = sel ? sel.value : "Full release to worker";
            let decision = "release";
            let pct = 100;
            if (/refund/i.test(label) && !/partial|60/i.test(label)) { decision = "refund"; pct = 0; }
            else if (/60|partial|split/i.test(label)) { decision = "split"; pct = 60; }
            try {
              await api("/api/admin/disputes/" + encodeURIComponent(d.id) + "/resolve", {
                body: { decision, worker_pct: pct, note: (ta && ta.value) || "Resolved after review of the order thread and evidence." },
              });
              toast("Resolution applied. Both parties notified.", "success");
              location.reload();
            } catch (err) { toast(err.message, "error"); }
          });
        }
      }
    }
  }

  function renderThread(t) {
    return '<div class="thread-item' + (t.active ? " active" : "") + '" data-id="' + t.id + '">' +
      '<span class="avatar sm ' + esc(t.tone) + '">' + esc(t.initials) + "</span>" +
      '<div class="ti-main"><div class="ti-top"><span class="ti-name">' + esc(t.other) + '</span><span class="ti-time">' + esc(t.time) + "</span></div>" +
      '<div class="ti-sub">' + esc(t.order ? t.order + " · " : "") + esc(t.preview || t.title) + "</div></div>" +
      (t.unread ? '<span class="ti-unread"></span>' : "") + "</div>";
  }

  function renderBubbles(msgs) {
    return (msgs || []).map((m) =>
      '<div class="bubble-row ' + (m.mine ? "me" : "them") + '"><div class="bubble ' + (m.mine ? "me" : "them") + '">' +
      esc(m.body) + '<span class="b-time">' + esc(m.time) + "</span></div></div>"
    ).join("") || '<p class="tiny faint" style="padding:16px">No messages yet. Keep payment on Skilvi.</p>';
  }

  async function messagesPage() {
    const list = await api("/api/messages");
    const host = $(".thread-list");
    let current = params.get("id") || (list[0] && String(list[0].id)) || "";
    if (host) {
      host.innerHTML = list.map((t) => {
        t.active = String(t.id) === String(current);
        return renderThread(t);
      }).join("") || '<p class="tiny faint" style="padding:16px">No threads yet. They appear when an order is live.</p>';
      const emptyHead = !list.length;
      const orderBtn = document.querySelector(".chat-head a.btn");
      if (orderBtn) orderBtn.style.display = emptyHead ? "none" : "";
      if (emptyHead && $("#chName")) $("#chName").textContent = "Select a conversation";
      if (emptyHead && $("#chSub")) $("#chSub").textContent = "Threads appear when an order is live.";
      $$(".thread-item", host).forEach((el) => el.addEventListener("click", () => {
        current = el.getAttribute("data-id");
        history.replaceState({}, "", "messages.html?id=" + current);
        loadThread(current, list);
      }));
    }
    if (current) await loadThread(current, list);
    const send = $("#chatSend");
    const input = $("#chatInput");
    const go = async () => {
      if (!current || !input) return;
      const body = input.value.trim();
      if (!body) return;
      try {
        const t = await api("/api/messages/" + encodeURIComponent(current), { body: { body } });
        input.value = "";
        if ($("#chatBody")) $("#chatBody").innerHTML = renderBubbles(t.messages);
        $("#chatBody") && ($("#chatBody").scrollTop = $("#chatBody").scrollHeight);
      } catch (err) { toast(err.message, "error"); }
    };
    if (send) send.addEventListener("click", go);
    if (input) input.addEventListener("keydown", (e) => { if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); go(); } });
    setInterval(async () => {
      if (!current || document.hidden) return;
      try {
        const t = await api("/api/messages/" + encodeURIComponent(current));
        if ($("#chatBody")) $("#chatBody").innerHTML = renderBubbles(t.messages);
      } catch (e) { /* keep last */ }
    }, 30000);
  }

  async function loadThread(id, list) {
    const t = await api("/api/messages/" + encodeURIComponent(id));
    $$(".thread-item").forEach((el) => el.classList.toggle("active", el.getAttribute("data-id") === String(id)));
    const name = $("#chName") || $(".ch-name");
    if (name) name.innerHTML = esc(t.other) + (t.verified ? ' <span class="badge-verified">' + (I.shield || "") + " Verified</span>" : "");
    const sub = $("#chSub") || $(".ch-sub");
    if (sub) sub.textContent = (t.order ? "Order " + t.order + " · " : "") + t.title + (t.amount_label ? " · " + t.amount_label + " in escrow" : "");
    const av = document.querySelector(".chat-head .avatar");
    if (av) { av.textContent = t.initials; av.className = "avatar sm " + t.tone; }
    const orderBtn = document.querySelector(".chat-head a.btn");
    if (orderBtn) {
      if (t.order) {
        orderBtn.href = "order-detail.html?id=" + encodeURIComponent(t.order);
        orderBtn.style.display = "";
      } else {
        orderBtn.style.display = "none";
      }
    }
    if ($("#chatBody")) {
      $("#chatBody").innerHTML = renderBubbles(t.messages);
      $("#chatBody").scrollTop = $("#chatBody").scrollHeight;
    }
  }

  async function notificationsPage() {
    const data = await api("/api/notifications");
    const items = data.items || [];
    const row = (n) =>
      '<a class="queue-row" style="text-decoration:none;color:inherit" href="' + esc(n.href) + '">' +
      '<span class="stat-icon" style="width:34px;height:34px;background:var(--bg)">' + (I.briefcase || "") + "</span>" +
      '<div class="qr-main"><div class="qr-t' + (n.unread ? "" : " faint") + '">' + esc(n.title) + '</div><div class="qr-s">' + esc(n.body || "") + "</div></div>" +
      '<div class="right nowrap" style="display:flex;align-items:center;gap:10px"><span class="tiny faint">' + esc(n.time) + "</span>" +
      (n.unread ? '<span class="ti-unread" style="display:block"></span>' : "") + "</div></a>";
    const fill = (sel, cat) => {
      const el = $(sel);
      if (!el) return;
      const rows = items.filter((x) => !cat || x.cat === cat);
      el.innerHTML = rows.map(row).join("") || '<p class="tiny faint" style="padding:16px">Nothing here.</p>';
    };
    fill("#ntListAll");
    fill("#ntListMoney", "money");
    fill("#ntListOrders", "orders");
    fill("#ntListMsgs", "msgs");
    const setCount = (sel, n) => {
      const el = $(sel);
      if (el) el.textContent = String(n);
    };
    setCount("#ntCountAll", items.length);
    setCount("#ntCountMoney", items.filter((x) => x.cat === "money").length);
    setCount("#ntCountOrders", items.filter((x) => x.cat === "orders").length);
    setCount("#ntCountMsgs", items.filter((x) => x.cat === "msgs").length);
    const mark = $("#markRead");
    if (mark) {
      mark.addEventListener("click", async () => {
        try {
          await api("/api/notifications/read-all", { body: {} });
          $$(".ti-unread").forEach((d) => d.remove());
          toast("All notifications marked as read.", "success");
        } catch (err) { toast(err.message, "error"); }
      });
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    const run = async () => {
      try {
        if (page === "disputes") await disputesPage();
        else if (page === "dispute-case") await disputeDetail();
        else if (page === "messages") await messagesPage();
        else if (page === "notifications") await notificationsPage();
      } catch (err) {
        if (!gate(err)) toast(err.message || "Could not load.", "error");
      }
    };
    run();
  });
})();
