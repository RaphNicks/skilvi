(function () {
  "use strict";
  const api = (window.SkApi && window.SkApi.api) || (async (p) => (await fetch(p)).json().then((b) => b.data));
  const toast = (window.SkApi && window.SkApi.toast) || (window.Sk && window.Sk.toast) || ((m) => console.log(m));
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const I = window.SkIconSvg || {};
  const stars = window.SkStars || ((n) => String(n));
  const ngn = (window.Sk && window.Sk.ngn) || ((n) => "₦" + Number(n).toLocaleString("en-NG"));
  const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const params = new URLSearchParams(location.search);
  const page = document.body.getAttribute("data-page") || (document.body.classList.contains("lp") ? "landing" : "");

  function empty(msg) {
    return '<div class="card"><div class="empty"><h3>' + esc(msg) + "</h3></div></div>";
  }

  function jobCard(j) {
    const st = j.mode === "remote" ? "st-royal" : "st-amber";
    const ico = j.mode === "remote" ? I.code : I.truck;
    return '<div class="job-card"><div class="jc-top"><h3><a href="job-detail.html?id=' + esc(j.id) + '">' + esc(j.title) + "</a></h3>" +
      '<span class="jc-budget">' + esc(j.budget_label) + "</span></div>" +
      '<div class="jc-meta"><span>' + (ico || "") + '<span class="st ' + st + '" style="padding:1px 8px">' + esc(j.mode) + "</span></span>" +
      "<span>" + (I.pin || "") + esc(j.loc) + "</span>" +
      "<span>" + (I.clock || "") + "Posted " + esc(j.time) + "</span>" +
      (j.deadline && j.deadline !== "—" ? "<span>" + (I.clock || "") + "Due " + esc(j.deadline) + "</span>" : "") + "</div>" +
      (j.desc ? '<p class="small muted clamp-2">' + esc(j.desc) + "</p>" : "") +
      '<div class="jc-foot"><span class="jc-proposals"><b style="color:var(--ink)">' + j.proposals + "</b> proposals · client " +
      (j.clientRating ? j.clientRating + "★" : "") +
      (j.verified ? ' <span class="badge-verified" style="padding:1px 7px">' + (I.shield || "") + "</span>" : "") +
      '</span><a class="btn btn-primary btn-sm" href="job-detail.html?id=' + esc(j.id) + '">Apply</a></div></div>';
  }

  function resultItem(w) {
    const svc = w.service;
    return '<div class="result-item">' +
      (w.promo ? '<span class="tag tag-promoted" style="grid-column:1/-1;justify-self:start">Promoted</span>' : "") +
      '<span class="avatar ' + esc(w.tone) + '">' + esc(w.init) + "</span>" +
      '<div class="ri-main"><div class="ri-top"><span class="ri-name"><a href="worker-profile.html?id=' + esc(w.id) + '">' + esc(w.name) + "</a></span>" +
      (w.verified ? '<span class="badge-verified">' + (I.shield || "") + "Verified</span>" : "") + "</div>" +
      '<div class="ri-skill">' + esc(w.headline) + "</div>" +
      '<div class="ri-meta"><span>' + (I.pin || "") + esc(w.city) + ", " + esc(w.state) + "</span>" +
      "<span>" + stars(w.rating) + " <b style='color:var(--ink)'>" + w.rating + "</b> (" + w.reviews + ")</span>" +
      "<span>" + (I.clock || "") + "Replies " + esc(w.resp) + "</span>" +
      "<span>" + (I.checkc || "") + w.jobs + " orders completed</span></div>" +
      (svc ? '<div class="ri-svc"><div class="svc-line"><b><a href="service-detail.html?id=' + esc(svc.id) + '">' + esc(svc.title) + "</a></b>" +
        '<span>from <span class="amount">' + ngn(svc.from) + "</span></span></div></div>" : "") +
      "</div>" +
      '<div class="ri-side"><span class="rating-line">' + stars(w.rating) + " <b>" + w.rating + "</b></span>" +
      (svc ? '<a class="btn btn-secondary btn-sm" href="service-detail.html?id=' + esc(svc.id) + '">View service</a>' : "") +
      '<a class="btn btn-primary btn-sm" href="worker-profile.html?id=' + esc(w.id) + '">View profile</a></div></div>';
  }

  async function hydrateJobs() {
    const grid = $("#jobGrid");
    if (!grid) return;
    const mode = ($("#modeSel") && $("#modeSel").value) || "";
    const catBtn = $("#catChips .chip.active");
    const catMap = { "All categories": "", "Digital & Tech": "digital-tech", "Trades & Home": "trades", "Creative & Media": "creative", "Business": "business" };
    const category = catBtn ? (catMap[catBtn.textContent.trim()] || "") : "";
    const data = await api("/api/jobs?mode=" + encodeURIComponent(mode) + "&category=" + encodeURIComponent(category));
    grid.innerHTML = data.items.map(jobCard).join("") || empty("No open jobs match those filters.");
  }

  async function hydrateSearch() {
    const list = $("#results");
    if (!list) return;
    const q = params.get("q") || ($("#qInput") && $("#qInput").value) || "";
    if (q && $("#qInput")) $("#qInput").value = q;
    if (q && $("#searchTerm")) $("#searchTerm").textContent = "“" + q + "”";
    else if ($("#searchTerm")) $("#searchTerm").textContent = "All services";
    const mode = ($$("#filters input[name=mode]:checked")[0] || {}).value || "";
    const state = $("#fState") ? $("#fState").value : "";
    const verified = $("#fVerified") && $("#fVerified").checked ? "1" : "";
    const rating = $("#fRating") ? $("#fRating").value : "";
    const qs = new URLSearchParams({ q, mode, state, verified, rating });
    const data = await api("/api/workers?" + qs.toString());
    list.innerHTML = data.items.map(resultItem).join("") || empty("No matches. Try different keywords.");
    if ($("#rcCount")) $("#rcCount").textContent = data.total;
  }

  async function hydrateCategory() {
    const slug = params.get("c") || params.get("skill") || "trades";
    const data = await api("/api/categories/" + encodeURIComponent(slug));
    const c = data.category;
    if ($("#catHead")) {
      $("#catHead").innerHTML =
        '<div class="row" style="gap:16px; align-items:center">' +
        '<span class="cat-ico" style="width:52px;height:52px">' + (I[c.icon] || I.wrench || "") + "</span>" +
        '<div class="grow"><h1 style="font-size:24px">' + esc(c.name) + "</h1>" +
        '<p class="small muted mt-1">' + esc(c.blurb || "") + "</p></div>" +
        '<div class="stack" style="gap:8px; min-width:150px"><span class="tag tag-royal">' + c.workers + " workers</span>" +
        (c.mode ? '<span class="tag">' + esc(c.mode) + "</span>" : "") + "</div></div>";
    }
    if ($("#subChips")) {
      $("#subChips").innerHTML = (c.subs || []).map((s) =>
        '<a class="chip' + (c.active === s.name ? " active" : "") + '" href="category.html?c=' + encodeURIComponent(s.slug) + '">' + esc(s.name) + "</a>"
      ).join("");
    }
    if ($("#catResults")) $("#catResults").innerHTML = data.workers.map(resultItem).join("") || empty("No workers in this category yet.");
  }

  async function hydrateJobDetail() {
    const id = params.get("id") || "";
    if (!id) {
      toast("This job is not available.", "error");
      return;
    }
    const j = await api("/api/jobs/" + encodeURIComponent(id));
    const main = $("main.container");
    if (!main) return;
    const crumb = $("main .small.muted");
    if (crumb) crumb.innerHTML = '<a href="jobs.html">Jobs</a> / ' + esc(j.title);
    const title = $("main h1");
    if (title) title.textContent = j.title;
    const budget = $(".sc-price");
    if (budget) budget.textContent = j.budget_label;
    const kvs = $$("main .kv");
    if (kvs[0]) kvs[0].querySelector(".v").textContent = j.loc;
    if (kvs[1]) kvs[1].querySelector(".v").textContent = j.deadline;
    if (kvs[2] && j.desc) kvs[2].querySelector(".v").textContent = j.desc;
    const desc = $("main h3.mt-2 + p");
    if (desc && j.description) desc.textContent = j.description;
    const h2 = $$("main h2").find((h) => /proposal/i.test(h.textContent || ""));
    if (h2) h2.textContent = j.proposals.length + " proposal" + (j.proposals.length === 1 ? "" : "s");
    const stack = h2 && h2.nextElementSibling;
    if (stack && stack.classList.contains("stack")) {
      stack.innerHTML = j.proposals.map((p) =>
        '<div class="card card-pad"><div class="row spread" style="align-items:flex-start;gap:12px">' +
        '<div class="row" style="gap:11px"><span class="avatar ' + esc(p.tone) + '">' + esc(p.initials) + "</span><div>" +
        '<div class="bold" style="font-size:14.5px"><a href="worker-profile.html?id=' + esc(p.worker_id) + '">' + esc(p.name) + "</a> " +
        (p.verified ? '<span class="badge-verified">' + (I.shield || "") + " Verified</span>" : "") + "</div>" +
        '<span class="rating-line">' + stars(p.rating) + " <b>" + p.rating + "</b> · " + p.reviews + " reviews · " + p.jobs + " orders</span>" +
        '<p class="small muted mt-1">' + esc(p.cover) + "</p></div></div>" +
        '<div class="right"><div class="amount" style="font-size:18px">' + esc(p.bid_label) + "</div>" +
        (p.days ? '<div class="tiny faint">' + p.days + " days</div>" : "") +
        (p.status && p.status !== "sent" ? '<div class="tiny faint mt-1">' + esc(p.status) + "</div>" : "") +
        (j.viewer && j.viewer.is_client && p.status === "sent" ? '<div class="row mt-2" style="gap:6px;justify-content:flex-end">' +
          '<button class="btn btn-ghost btn-sm prop-act" data-act="shortlist" data-id="' + p.id + '">' + (p.shortlisted ? "Unshortlist" : "Shortlist") + "</button>" +
          '<button class="btn btn-secondary btn-sm prop-act" data-act="reject" data-id="' + p.id + '">Pass</button>' +
          '<button class="btn btn-primary btn-sm prop-act" data-act="accept" data-id="' + p.id + '">Accept</button></div>' : "") +
        "</div></div></div>"
      ).join("");
      $$(".prop-act", stack).forEach((b) => b.addEventListener("click", async () => {
        try {
          const act = b.getAttribute("data-act");
          const data = await api("/api/proposals/" + b.getAttribute("data-id") + "/" + act, { body: {} });
          if (act === "accept") {
            toast("Order " + data.id + " created. Pay into Skilvi escrow to start.", "success");
            location.href = "checkout.html?order=" + encodeURIComponent(data.id);
            return;
          }
          toast(act === "reject" ? "Proposal passed." : (data.shortlisted ? "Shortlisted." : "Removed from shortlist."), "success");
          location.reload();
        } catch (err) { toast(err.message, "error"); }
      }));
    }
    if (j.viewer && j.viewer.is_worker && !j.viewer.proposed && !j.viewer.is_client) {
      const box = document.createElement("div");
      box.className = "card card-pad mt-3";
      box.innerHTML = '<h3 style="font-size:15px">Send a proposal</h3>' +
        '<div class="form-row-2 mt-2"><div class="field"><label>Your bid (₦)</label><input class="input" id="bidNaira" inputmode="numeric" placeholder="280000"></div>' +
        '<div class="field"><label>Days</label><input class="input" id="bidDays" inputmode="numeric" placeholder="10"></div></div>' +
        '<div class="field mt-2"><label>Cover note</label><textarea class="textarea" id="coverNote" rows="4" placeholder="How you\'ll do the work, when you can start."></textarea></div>' +
        '<button class="btn btn-primary mt-2" type="button" id="sendProp">Send proposal</button>';
      (stack || main).parentNode.appendChild(box);
      $("#sendProp").addEventListener("click", async () => {
        try {
          await api("/api/jobs/" + encodeURIComponent(id) + "/propose", {
            body: {
              bid_naira: Number(($('#bidNaira') || {}).value || 0),
              days: Number(($('#bidDays') || {}).value || 0),
              cover_note: ($('#coverNote') || {}).value || "",
            },
          });
          toast("Proposal sent.", "success");
          location.reload();
        } catch (err) {
          toast(err.message, "error");
        }
      });
    }
  }

  async function hydrateProfile() {
    const id = params.get("id") || "";
    if (!id) {
      toast("This profile is not available.", "error");
      return;
    }
    const w = await api("/api/workers/" + encodeURIComponent(id));
    const nameEl = $("main h1, .ph-title, .profile-hero h1");
    // hero name is often in a specific block
    $$(".bold, h1").forEach((el) => {
      if (el.textContent && /Chinedu Okafor/.test(el.textContent) && el.tagName !== "SCRIPT") {
        el.childNodes[0] && el.childNodes[0].nodeType === 3 ? (el.childNodes[0].textContent = w.name + " ") : null;
      }
    });
    const h1 = $("h1");
    if (h1) h1.textContent = w.name;
    $$(".avatar.lg, .avatar.a1").forEach((el, i) => { if (i === 0) { el.textContent = w.init; el.className = "avatar lg " + w.tone; } });
    if ($("#phMeta")) {
      $("#phMeta").innerHTML =
        "<span>" + (I.pin || "") + esc(w.city) + ", " + esc(w.state) + "</span>" +
        "<span>" + (w.mode === "remote" ? I.code : I.truck || "") + esc(w.mode === "remote" ? "Remote work" : "On-site") + "</span>" +
        "<span>" + (I.clock || "") + "Replies " + esc(w.resp) + "</span>" +
        "<span>" + (I.checkc || "") + w.jobs + " orders completed</span>";
    }
    if ($("#ssRating")) $("#ssRating").innerHTML = stars(w.rating) + " " + w.rating;
    if ($("#profileServices") && w.services) {
      $("#profileServices").innerHTML = w.services.map((s) =>
        '<div class="card card-pad"><div class="row spread"><h3 style="font-family:var(--font-body)">' + esc(s.title) +
        '</h3><a class="btn btn-secondary btn-sm" href="service-detail.html?id=' + esc(s.id) + '">View details</a></div>' +
        (s.packages || []).map((p) => '<div class="kv"><span class="k">' + esc(p.name) + "</span><span class=\"v\">" +
          ngn(p.price_naira) + " · " + p.days + " days · " + p.revisions + " revisions</span></div>").join("") + "</div>"
      ).join("");
    }
    if ($("#reviewList") && w.reviews_list) {
      $("#reviewList").innerHTML = w.reviews_list.map((r) =>
        '<div class="review-card"><div class="rv-head"><span class="avatar sm a2">' + esc(r.initials) + "</span>" +
        '<span class="rv-name">' + esc(r.name) + "</span>" + stars(r.stars) +
        '<span class="rv-date">' + esc(r.date) + "</span></div>" +
        '<p class="rv-text">' + esc(r.text) + "</p>" +
        '<div class="rv-tags">' + (r.tags || []).map((t) => '<span class="tag">' + esc(t) + "</span>").join("") + "</div>" +
        (r.reply ? '<div class="rv-reply"><b>' + esc(w.name) + " replied:</b> " + esc(r.reply) + "</div>" : "") + "</div>"
      ).join("");
    }
    if ($("#dist") && w.distribution) {
      $("#dist").innerHTML = w.distribution.map((d) =>
        '<div class="row" style="gap:8px;font-size:12px;color:var(--ink-3)"><span style="width:14px">' + d.stars + "★</span>" +
        '<span style="flex:1;height:6px;background:var(--bg);border-radius:99px;overflow:hidden;display:block"><span style="display:block;height:100%;width:' + d.pct + '%;background:var(--royal-600);border-radius:99px"></span></span>' +
        '<span style="width:26px;text-align:right">' + d.pct + "%</span></div>"
      ).join("");
    }
    try {
      const me = await api("/api/me");
      if (me && me.public_code && me.public_code === w.id) {
        const actions = document.querySelector(".ph-actions");
        if (actions) {
          actions.innerHTML =
            '<a class="btn btn-primary" href="account-settings.html">Edit profile</a>' +
            '<a class="btn btn-secondary" href="worker-services.html">Manage services</a>';
        }
      }
    } catch (e) { /* guests stay on the public hire actions */ }
  }

  async function hydrateService() {
    const id = params.get("id") || "";
    if (!id) {
      toast("This service is not available.", "error");
      return;
    }
    const s = await api("/api/services/" + encodeURIComponent(id));
    const h1 = $("h1");
    if (h1) h1.textContent = s.title;
    if ($("#sumPrice")) $("#sumPrice").textContent = s.from_label;
    if ($("#pkgGrid") && s.packages && s.packages.length) {
      $("#pkgGrid").innerHTML = s.packages.map((p, i) =>
        '<div class="pkg-card' + (i === Math.min(1, s.packages.length - 1) ? " selected" : "") +
        '" data-pkg=\'' + JSON.stringify({ name: p.name, price: p.price_naira, days: p.days }) + "'>" +
        "<div class=\"bold\">" + esc(p.name) + "</div>" +
        '<div class="sc-price" style="font-size:20px">' + ngn(p.price_naira) + "</div>" +
        '<div class="tiny faint">' + p.days + " days · " + p.revisions + " revisions</div></div>"
      ).join("");
      $$(".pkg-card").forEach((card) => card.addEventListener("click", () => {
        $$(".pkg-card").forEach((c) => c.classList.remove("selected"));
        card.classList.add("selected");
        const d = JSON.parse(card.getAttribute("data-pkg"));
        if ($("#sumPrice")) $("#sumPrice").textContent = ngn(d.price);
        if ($("#sumMeta")) $("#sumMeta").textContent = "Delivery in " + d.days + " days";
        if ($("#pkgName")) $("#pkgName").textContent = d.name + " package";
        if ($("#hireBtn")) $("#hireBtn").href = "checkout.html?service=" + encodeURIComponent(s.id) + "&pkg=" + encodeURIComponent(d.name);
      }));
    }
    if ($("#hireBtn")) $("#hireBtn").href = "checkout.html?service=" + encodeURIComponent(s.id);
    const wlink = $('a[href="worker-profile.html"]');
    if (wlink && s.worker) wlink.href = "worker-profile.html?id=" + encodeURIComponent(s.worker.id);
  }

  async function hydrateSaved() {
    const list = $("#svList");
    if (!list) return;
    try {
      const rows = await api("/api/saved");
      if (!rows.length) {
        list.innerHTML = "";
        if ($("#svEmpty")) $("#svEmpty").style.display = "";
        return;
      }
      if ($("#svEmpty")) $("#svEmpty").style.display = "none";
      list.innerHTML = rows.map((w) =>
        '<div class="card card-pad" data-saved="' + esc(w.id) + '">' +
        '<div class="row spread" style="align-items:center;gap:12px;flex-wrap:wrap">' +
        '<div class="row" style="gap:12px"><span class="avatar ' + esc(w.tone) + '">' + esc(w.init) + "</span>" +
        "<div><div class=\"bold\" style=\"font-size:15px\"><a href=\"worker-profile.html?id=" + esc(w.id) + "\">" + esc(w.name) + "</a> " +
        (w.verified ? '<span class="badge-verified">' + (I.shield || "") + "Verified</span>" : "") + "</div>" +
        '<span class="rating-line" style="margin-top:3px">' + stars(w.rating) + " <b>" + w.rating + "</b> · " + esc(w.headline) + "</span></div></div>" +
        '<div class="row" style="gap:8px;flex-wrap:wrap">' +
        '<a class="btn btn-primary btn-sm" href="worker-profile.html?id=' + esc(w.id) + '">View profile</a>' +
        '<button class="icon-btn rm-saved" data-id="' + esc(w.id) + '" data-name="' + esc(w.name) + '" type="button">Remove</button></div></div>' +
        (w.note ? '<p class="tiny faint mt-1">' + esc(w.note) + "</p>" : "") + "</div>"
      ).join("");
      $$(".rm-saved").forEach((b) => b.addEventListener("click", async () => {
        try {
          await api("/api/saved/" + encodeURIComponent(b.getAttribute("data-id")) + "/remove", { body: {} });
          b.closest("[data-saved]").remove();
          toast(b.getAttribute("data-name") + " removed from saved workers.", "success");
          if (!$$("#svList .card").length && $("#svEmpty")) $("#svEmpty").style.display = "";
        } catch (err) { toast(err.message, "error"); }
      }));
    } catch (err) {
      if (err.status === 401) location.href = "/login.html?next=/saved.html";
      else toast(err.message, "error");
    }
  }

  async function hydrateLanding() {
    const data = await api("/api/landing");
    const grid = $(".lp-opp-grid");
    if (grid && data.jobs) {
      if (!data.jobs.length) {
        grid.innerHTML = '<p class="tiny faint">No open jobs yet. Post one to get started.</p>';
      } else
      grid.innerHTML = data.jobs.map((j, i) =>
        '<article class="lp-card' + (i === 1 ? " featured" : "") + '">' +
        '<div class="lp-meta"><span>' + esc(j.loc) + "</span><span>" + esc(j.time) + "</span></div>" +
        "<h3><a href=\"job-detail.html?id=" + esc(j.id) + "\">" + esc(j.title) + "</a></h3>" +
        '<div class="lp-chips"><span class="lp-chip">' + esc(j.mode) + "</span>" +
        (j.category ? '<span class="lp-chip">' + esc(j.category) + "</span>" : "") + "</div>" +
        '<div class="lp-client"><span class="lp-ava">' + esc((j.client || "?").slice(0, 2)) + "</span>" +
        "<span><span class=\"c-name\">" + esc(j.client) + "</span></span></div>" +
        '<a class="lp-btn lp-btn-outline lp-btn-sm lp-btn-block" href="job-detail.html?id=' + esc(j.id) + '">View job</a></article>'
      ).join("");
    }
    const wgrid = $(".lp-worker-grid");
    if (wgrid && data.workers) {
      wgrid.innerHTML = data.workers.map((w) =>
        '<article class="lp-card">' +
        '<div class="lp-w-head"><span class="lp-w-ava ' + esc(w.tone) + '">' + esc(w.init) + "</span><div>" +
        '<div class="lp-w-name">' + esc(w.name) + (w.verified ? ' <span class="lp-verified">Verified</span>' : "") + "</div>" +
        '<div class="lp-w-skill">' + esc(w.skill) + " · " + esc(w.city) + "</div></div></div>" +
        '<div class="lp-w-rate"><b>' + w.rating + "</b> (" + w.reviews + " reviews)</div>" +
        '<p class="lp-w-bio">' + esc(w.headline) + "</p>" +
        '<a class="lp-btn lp-btn-outline lp-btn-sm lp-btn-block" href="worker-profile.html?id=' + esc(w.id) + '">View profile</a></article>'
      ).join("");
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    const run = async () => {
      try {
        if (page === "jobs") {
          await hydrateJobs();
          $("#modeSel") && $("#modeSel").addEventListener("change", () => hydrateJobs().catch((e) => toast(e.message, "error")));
          $$("#catChips .chip").forEach((c) => c.addEventListener("click", () => {
            $$("#catChips .chip").forEach((x) => x.classList.remove("active"));
            c.classList.add("active");
            hydrateJobs().catch((e) => toast(e.message, "error"));
          }));
        } else if (page === "search") {
          await hydrateSearch();
          const qNow = (params.get("q") || "").trim().toLowerCase();
          $$("#chips .chip").forEach((c) => {
            c.classList.toggle("active", qNow !== "" && c.textContent.trim().toLowerCase() === qNow);
          });
          $$("#filters input, #filters select").forEach((el) => el.addEventListener("change", () => hydrateSearch().catch((e) => toast(e.message, "error"))));
          $("#qInput") && $("#qInput").addEventListener("keydown", (e) => {
            if (e.key === "Enter") { e.preventDefault(); location.search = "?q=" + encodeURIComponent($("#qInput").value.trim()); }
          });
        } else if (page === "category") {
          await hydrateCategory();
        } else if (page === "job-detail") {
          await hydrateJobDetail();
        } else if (page === "worker-profile" || page === "profile") {
          await hydrateProfile();
        } else if (page === "service") {
          await hydrateService();
        } else if (page === "saved") {
          await hydrateSaved();
        } else if (page === "landing") {
          await hydrateLanding();
        }
      } catch (err) {
        console.error(err);
        toast(err.message || "Could not load live data.", "error");
      }
    };
    run();
  });
})();
