(function () {
  "use strict";
  const api = (window.SkApi && window.SkApi.api) || (async (p) => (await fetch(p)).json().then((b) => b.data));
  const toast = (window.SkApi && window.SkApi.toast) || (window.Sk && window.Sk.toast) || ((m) => console.log(m));
  const busy = (window.SkApi && window.SkApi.busy) || function () {};
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const params = new URLSearchParams(location.search);
  const page = document.body.getAttribute("data-page") || "";

  function gate(err) {
    if (err && err.status === 401) {
      location.href = "/login.html?next=" + encodeURIComponent(location.pathname + location.search);
      return true;
    }
    return false;
  }

  function methodFromUi() {
    const pills = $$(".radio-pill");
    const active = pills.find((p) => p.classList.contains("active")) || pills[0];
    const t = (active && active.textContent) || "";
    if (/card/i.test(t)) return "card";
    if (/ussd/i.test(t)) return "ussd";
    if (/mobile/i.test(t)) return "mobile_money";
    return "transfer";
  }

  function bindMethodPills(root) {
    const pills = $$((root || document.body).querySelector ? undefined : ".radio-pill");
    $$(".radio-pill").forEach((p) => {
      p.addEventListener("click", (e) => {
        e.preventDefault();
        $$(".radio-pill").forEach((x) => x.classList.remove("active"));
        p.classList.add("active");
      });
    });
  }

  function initials(name) {
    const p = String(name || "").trim().split(/\s+/);
    if (!p[0]) return "?";
    return (p[0][0] + (p[1] ? p[1][0] : "")).toUpperCase();
  }

  function showVa(pay) {
    const box = $("#coPayHint") || document.querySelector(".alert.alert-info");
    if (!box || !pay.virtual_account) return;
    box.hidden = false;
    const va = pay.virtual_account;
    box.innerHTML = "<span><b>Instant transfer instructions:</b> Pay <b>" + esc(va.amount_label) + "</b> to <b>" +
      esc(va.account_name) + "</b> · " + esc(va.bank) + " <span class=\"mono\">" + esc(va.account_number) +
      "</span> · reference <span class=\"mono\">" + esc(va.reference) + "</span>. In this sandbox, tap “I’ve paid” to simulate the Paystack webhook.</span>";
  }

  async function pollUntilDone(code) {
    for (let i = 0; i < 20; i++) {
      const st = await api("/api/payments/" + encodeURIComponent(code) + "/status");
      if (st.status === "succeeded") {
        location.href = st.success_url || ("payment-success.html?id=" + encodeURIComponent(code));
        return;
      }
      if (st.status === "failed") {
        toast("Payment failed. Try another method.", "error");
        return;
      }
      await new Promise((r) => setTimeout(r, i < 4 ? 2000 : 4000));
    }
  }

  async function checkout() {
    let orderId = params.get("order") || params.get("id") || "";
    const service = params.get("service");
    const pkg = params.get("pkg") || "";
    if (!orderId && service) {
      const o = await api("/api/orders/from-service", { body: { service_id: service, pkg } });
      orderId = o.id;
      history.replaceState(null, "", "checkout.html?order=" + encodeURIComponent(orderId));
    }
    if (!orderId) {
      toast("No order to pay for.", "error");
      return;
    }
    const o = await api("/api/orders/" + encodeURIComponent(orderId));
    const crumb = $("main .small.muted");
    if (crumb) crumb.innerHTML = '<a href="client-dashboard.html">Orders</a> / Checkout';
    const h1 = $(".ph-title");
    if (h1) h1.textContent = "Confirm & pay";
    const sub = $(".ph-sub");
    if (sub) sub.textContent = "Order " + o.id + " · pay Skilvi — not the worker.";
    const price = $(".sc-price");
    if (price) price.textContent = o.amount_label;
    $$(".sc-price, #payBtn").forEach((el) => {
      if (el.id === "payBtn") el.textContent = "Pay " + o.amount_label;
    });
    const av = $("#coAvatar");
    if (av) {
      av.textContent = initials(o.worker_name);
      av.className = "avatar " + (o.worker_tone || "a1");
    }
    const nameEl = $("#coWorkerName");
    if (nameEl) {
      const badge = $("#coVerified");
      nameEl.childNodes[0].textContent = (o.worker_name || "Worker") + " ";
      if (badge) badge.hidden = !o.worker_verified;
    }
    const meta = $("#coWorkerMeta");
    if (meta) {
      const bits = [];
      if (o.worker_reviews) bits.push((o.worker_rating ? o.worker_rating.toFixed(1) : "—") + " · " + o.worker_reviews + " review" + (o.worker_reviews === 1 ? "" : "s"));
      if (o.worker_jobs) bits.push(o.worker_jobs + " orders");
      if (o.worker_reply) bits.push("replies " + o.worker_reply);
      meta.textContent = bits.join(" · ");
    }
    const prof = $("#coProfile");
    if (prof) {
      prof.href = o.worker_code ? "worker-profile.html?id=" + encodeURIComponent(o.worker_code) : "search.html";
    }
    if ($("#coService")) $("#coService").textContent = o.title || "—";
    if ($("#coNextNotify") && o.worker_name) {
      const first = String(o.worker_name).trim().split(/\s+/)[0];
      $("#coNextNotify").textContent = first + " is notified & starts";
    }
    if ($("#coPkgPrice")) $("#coPkgPrice").textContent = o.amount_label || "—";
    const payBtn = $("#payBtn");
    if (o.status !== "pending_payment") {
      if (payBtn) {
        payBtn.textContent = "Already paid — open order";
        payBtn.addEventListener("click", () => { location.href = "order-detail.html?id=" + encodeURIComponent(o.id); });
      }
      return;
    }
    let currentPay = null;
    async function startPay() {
      busy(payBtn, true);
      try {
        currentPay = await api("/api/payments/initiate", {
          body: { purpose: "order", order_id: o.id, method: "paystack" },
        });
        if (currentPay.dev_simulate) {
          toast("Paystack (dev): confirming payment.", "success");
          await api("/api/payments/" + encodeURIComponent(currentPay.id) + "/simulate", { body: { result: "success" } });
          location.href = currentPay.success_url;
          return;
        }
        if (currentPay.authorization_url) {
          location.href = currentPay.authorization_url;
          return;
        }
        toast("Waiting for Paystack to confirm…", "success");
        await pollUntilDone(currentPay.id);
      } catch (err) {
        if (!gate(err)) toast(err.message, "error");
      } finally {
        busy(payBtn, false);
      }
    }
    if (payBtn) {
      payBtn.addEventListener("click", (e) => { e.preventDefault(); startPay(); });
    }
  }

  async function successPage() {
    const id = params.get("id") || params.get("pay") || "";
    if (!id) return;
    const p = await api("/api/payments/" + encodeURIComponent(id));
    const o = p.order || {};
    if ($("#psOrder")) $("#psOrder").textContent = o.id || "—";
    if ($("#psService")) $("#psService").textContent = o.title || "—";
    if ($("#psWorker")) {
      $("#psWorker").textContent = o.worker_name || "—";
    }
    if ($("#psAmount")) $("#psAmount").textContent = p.amount_label || "—";
    if ($("#psMethod")) $("#psMethod").textContent = p.method_label || "Paystack";
    if ($("#psRef")) $("#psRef").textContent = p.provider_ref || p.id || "—";
    if ($("#psWhen")) $("#psWhen").textContent = p.paid_at || "—";
    if ($("#psEscrowKv")) $("#psEscrowKv").textContent = p.escrow_label || "—";
    const track = $("#psTrack") || document.querySelector('a.btn.btn-primary[href*="order-detail"]');
    if (track && o.id) {
      track.href = "order-detail.html?id=" + encodeURIComponent(o.id);
      track.textContent = "Track order " + o.id;
    }
    const h1 = $("h1");
    if (h1 && p.status === "succeeded") h1.textContent = "Payment verified — your order is live";
    const pdfBtn = $("#psPdf");
    if (pdfBtn) {
      pdfBtn.removeAttribute("data-toast");
      pdfBtn.addEventListener("click", async () => {
        try {
          const tok = window.SkApi && window.SkApi.tabToken && window.SkApi.tabToken();
          const r = await fetch("/api/payments/" + encodeURIComponent(p.id) + "/receipt.pdf", {
            headers: tok ? { Authorization: "Bearer " + tok, "X-Skilvi-Token": tok } : {},
            credentials: "same-origin",
          });
          if (!r.ok) throw new Error("Could not generate the receipt.");
          const blob = await r.blob();
          const a = document.createElement("a");
          a.href = URL.createObjectURL(blob);
          a.download = p.id + "-receipt.pdf";
          document.body.appendChild(a);
          a.click();
          a.remove();
          setTimeout(() => URL.revokeObjectURL(a.href), 2000);
        } catch (err) {
          toast(err.message || "Could not download PDF.", "error");
        }
      });
    }
  }

  async function verifyPage() {
    let me = null;
    try { me = await api("/api/me"); } catch (err) {
      if (gate(err)) return;
      throw err;
    }
    const nameInp = document.querySelector("#vrForm [name=full_name]");
    if (nameInp && me && me.full_name && !nameInp.value) nameInp.value = me.full_name;

    let st = { status: "none" };
    try { st = await api("/api/verification/status"); } catch (err) {
      if (gate(err)) return;
      throw err;
    }
    const form = $("#vrForm");
    if (!form) return;
    const statusEl = $("#vrStatus");
    if (st.status === "approved") {
      if (statusEl) {
        statusEl.style.display = "";
        statusEl.textContent = "This worker profile is already verified. Identity only — not a skill certificate.";
      }
      form.querySelectorAll("input, select, button").forEach((el) => { el.disabled = true; });
      return;
    }
    if (st.status === "pending") {
      if (statusEl) {
        statusEl.style.display = "";
        statusEl.textContent = "Your identity check is with the review team — usually within 2 business days.";
      }
    }
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const btn = form.querySelector("button[type=submit]");
      busy(btn, true);
      try {
        if (st.status !== "pending" && st.status !== "approved") {
          const pay = await api("/api/payments/initiate", { body: { purpose: "verification", method: "transfer" } });
          if (pay.dev_simulate) {
            await api("/api/payments/" + encodeURIComponent(pay.id) + "/simulate", { body: { result: "success" } });
          } else {
            await pollUntilDone(pay.id);
          }
        }
        const body = {
          full_name: (form.querySelector("[name=full_name]") || {}).value,
          id_type: (form.querySelector("[name=id_type]") || {}).value,
          id_number: (form.querySelector("[name=id_number]") || {}).value,
        };
        await api("/api/verification/submit", { body });
        toast("Application in. Identity only — this never certifies skill. Decision within 2 business days.", "success");
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

  async function promoPage() {
    let data;
    try { data = await api("/api/promotions"); } catch (err) {
      if (err.status === 401) return;
      throw err;
    }
    const form = $("#prForm");
    if (!form) return;
    if (data.active) {
      toast("A promotion is already running until " + data.active.ends_at + ".", "success");
    }
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const btn = form.querySelector("button[type=submit]");
      const active = form.querySelector("[data-radio] .radio-pill.active");
      const plan = active && /5,000/.test(active.textContent) ? "category" : "search";
      busy(btn, true);
      try {
        const pay = await api("/api/promotions/" + plan + "/purchase", { body: { method: "transfer", plan } });
        if (pay.dev_simulate) {
          await api("/api/payments/" + encodeURIComponent(pay.id) + "/simulate", { body: { result: "success" } });
        }
        toast("Promotion started — labelled Promoted, never a skill badge.", "success");
        setTimeout(() => location.href = "worker-dashboard.html", 900);
      } catch (err) {
        if (!gate(err)) toast(err.message, "error");
      } finally {
        busy(btn, false);
      }
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    const current = document.body.getAttribute("data-page") || (/checkout/.test(location.pathname) ? "checkout" : "");
    const run = async () => {
      try {
        if (current === "checkout") await checkout();
        else if (current === "pay-success") await successPage();
        else if (current === "verify") await verifyPage();
        else if (current === "promo") await promoPage();
      } catch (err) {
        if (!gate(err)) {
          console.error(err);
          toast(err.message || "Could not load payment.", "error");
        }
      }
    };
    run();
  });
})();
