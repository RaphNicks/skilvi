(function () {
  "use strict";
  const { api, busy, toast, dialog } = window.SkApi || {};
  if (!api) return;

  function $(s, r) { return (r || document).querySelector(s); }

  function fill(me) {
    if ($("#meName")) $("#meName").value = me.full_name || "";
    if ($("#meHeadline")) $("#meHeadline").value = me.headline || "";
    if ($("#meBio")) $("#meBio").value = me.bio || "";
    if (window.SkGeo && !window.__meGeo) {
      window.__meGeo = true;
      window.SkGeo.bind({
        country: "#meCountry",
        state: "#meState",
        city: "#meCity",
        values: { country: me.country, state: me.state, city: me.city },
      });
    }
    if ($("#meEmail")) $("#meEmail").value = me.email || "";
    if ($("#mePhone")) {
      const ph = String(me.phone || "");
      $("#mePhone").value = ph.startsWith("e:") ? "" : (me.phone_fmt || ph);
    }
    if ($("#prefSms")) $("#prefSms").checked = !!me.notify_sms;
    if ($("#prefJobs")) $("#prefJobs").checked = !!me.notify_jobs;
    if ($("#prefMkt")) $("#prefMkt").checked = !!me.notify_marketing;
    if ($("#meAvatar")) $("#meAvatar").textContent = me.initials || "?";
    const roles = (me.roles || []).filter((r) => r !== "admin");
    if ($("#stRole")) $("#stRole").textContent = roles.length
      ? roles.map((r) => r.charAt(0).toUpperCase() + r.slice(1)).join(" + ")
      : "Account";
    if ($("#stStatus")) $("#stStatus").textContent = me.status === "active" ? "Active" : (me.status || "—");
    if ($("#stVerified")) {
      $("#stVerified").textContent = me.verified
        ? "Identity checked — not a skill certificate"
        : "Not verified";
    }
    if ($("#stSince") && me.member_since) {
      const d = new Date(me.member_since);
      $("#stSince").textContent = isNaN(d) ? me.member_since : d.toLocaleDateString("en-NG", { month: "short", year: "numeric" });
    }
  }

  document.addEventListener("DOMContentLoaded", () => {
    api("/api/me").then((me) => {
      fill(me);
      window.__me = me;

      $("#saveProfile") && $("#saveProfile").addEventListener("click", async () => {
        const btn = $("#saveProfile");
        busy(btn, true);
        try {
          const updated = await api("/api/me", {
            body: {
              full_name: $("#meName") && $("#meName").value,
              headline: $("#meHeadline") && $("#meHeadline").value,
              bio: $("#meBio") && $("#meBio").value,
              country: $("#meCountry") && $("#meCountry").value,
              state: $("#meState") && $("#meState").value,
              city: $("#meCity") && $("#meCity").value,
            },
          });
          fill(updated);
          toast("Profile saved.", "success");
        } catch (err) {
          toast(err.message, "error");
        } finally {
          busy(btn, false);
        }
      });

      $("#savePrefs") && $("#savePrefs").addEventListener("click", async () => {
        const btn = $("#savePrefs");
        busy(btn, true);
        try {
          await api("/api/me", {
            body: {
              notify_sms: $("#prefSms") && $("#prefSms").checked,
              notify_jobs: $("#prefJobs") && $("#prefJobs").checked,
              notify_marketing: $("#prefMkt") && $("#prefMkt").checked,
            },
          });
          toast("Preferences saved.", "success");
        } catch (err) {
          toast(err.message, "error");
        } finally {
          busy(btn, false);
        }
      });

      $("#saveEmail") && $("#saveEmail").addEventListener("click", async () => {
        const btn = $("#saveEmail");
        busy(btn, true);
        try {
          await api("/api/me/email", { body: { email: $("#meEmail").value } });
          toast("Email updated.", "success");
        } catch (err) {
          toast(err.message, "error");
        } finally {
          busy(btn, false);
        }
      });

      $("#savePassword") && $("#savePassword").addEventListener("click", async () => {
        const next = $("#mePassword") && $("#mePassword").value;
        if (!next || next.length < 8) {
          toast("Use at least 8 characters.", "error");
          return;
        }
        const current = dialog
          ? await dialog.prompt({
              title: "Confirm it's you",
              body: "Enter your current password to save the new one.",
              label: "Current password",
              input: "password",
              placeholder: "Current password",
              ok: "Update password",
            })
          : "";
        if (!current) return;
        const btn = $("#savePassword");
        busy(btn, true);
        try {
          await api("/api/me/password", { body: { current, password: next } });
          toast("Password updated.", "success");
          $("#mePassword").value = "";
        } catch (err) {
          toast(err.message, "error");
        } finally {
          busy(btn, false);
        }
      });

      $("#savePhone") && $("#savePhone").addEventListener("click", async () => {
        const btn = $("#savePhone");
        busy(btn, true);
        try {
          const updated = await api("/api/me/phone", { body: { phone: ($("#mePhone") && $("#mePhone").value) || "" } });
          fill(updated);
          toast("Phone updated.", "success");
        } catch (err) {
          toast(err.message, "error");
        } finally {
          busy(btn, false);
        }
      });

      async function pullExport(format) {
        const tok = (window.SkApi && window.SkApi.tabToken && window.SkApi.tabToken()) || "";
        const headers = {
          Accept: format === "pdf" ? "application/pdf" : "application/json",
          "X-CSRF-Token": (window.SkApi && window.SkApi.csrf && window.SkApi.csrf()) || "",
        };
        if (tok) {
          headers.Authorization = "Bearer " + tok;
          headers["X-Skilvi-Token"] = tok;
        }
        const res = await fetch("/api/me/export" + (format === "pdf" ? "?format=pdf" : ""), {
          credentials: "same-origin",
          headers,
        });
        if (format === "pdf") {
          const ct = (res.headers.get("content-type") || "");
          if (!res.ok || ct.indexOf("pdf") === -1) {
            let msg = "Could not build the PDF.";
            try { const j = await res.json(); msg = (j.error && j.error.message) || msg; } catch (e) {}
            throw new Error(msg);
          }
          const blob = await res.blob();
          const a = document.createElement("a");
          a.href = URL.createObjectURL(blob);
          a.download = "skilvi-data.pdf";
          a.click();
          URL.revokeObjectURL(a.href);
          return;
        }
        const data = await api("/api/me/export");
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
        const a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = "skilvi-data.json";
        a.click();
        URL.revokeObjectURL(a.href);
      }
      const bindExport = (sel, format, label) => {
        const btn = $(sel);
        if (!btn) return;
        btn.addEventListener("click", async () => {
          busy(btn, true);
          try {
            await pullExport(format);
            toast(label, "success");
          } catch (err) {
            toast(err.message, "error");
          } finally {
            busy(btn, false);
          }
        });
      };
      bindExport("#btnExportJson", "json", "JSON download started.");
      bindExport("#btnExportPdf", "pdf", "PDF download started.");

      $("#btnDelete") && $("#btnDelete").addEventListener("click", async () => {
        const okDel = dialog
          ? await dialog.confirm({
              title: "Delete your account?",
              body: "We process this within 30 days, after any open escrow settles.",
              ok: "Request deletion",
              danger: true,
            })
          : false;
        if (!okDel) return;
        const btn = $("#btnDelete");
        busy(btn, true);
        try {
          const r = await api("/api/me/delete-request", { body: {} });
          toast(r.message || "Deletion request logged.", "success");
        } catch (err) {
          toast(err.message, "error");
        } finally {
          busy(btn, false);
        }
      });

      $("#btnConsent") && $("#btnConsent").addEventListener("click", async () => {
        const box = $("#consentBox");
        try {
          const r = await api("/api/me/consent");
          if (box) {
            box.style.display = "";
            box.innerHTML = (r.items || []).map((it) =>
              "<div>" + (it.on ? "On" : "Off") + " — " + it.title + (it.locked ? " (required)" : "") + "</div>"
            ).join("") + (r.updated_at ? "<div>Last updated " + r.updated_at + "</div>" : "");
          }
        } catch (err) {
          toast(err.message, "error");
        }
      });

      $("#btnDeactivate") && $("#btnDeactivate").addEventListener("click", async () => {
        const okOff = dialog
          ? await dialog.confirm({
              title: "Deactivate this account?",
              body: "Your profile hides and you cannot take new orders until you log in and we restore it.",
              ok: "Deactivate",
              danger: true,
            })
          : false;
        if (!okOff) return;
        const btn = $("#btnDeactivate");
        busy(btn, true);
        try {
          await api("/api/me/deactivate", { body: {} });
          toast("Account deactivated.", "success");
          location.href = "/login.html";
        } catch (err) {
          toast(err.message, "error");
        } finally {
          busy(btn, false);
        }
      });

      const file = $("#avatarFile");
      $("#avatarBtn") && $("#avatarBtn").addEventListener("click", () => file && file.click());
      file && file.addEventListener("change", async () => {
        if (!file.files || !file.files[0]) return;
        const fd = new FormData();
        fd.append("avatar", file.files[0]);
        try {
          await api("/api/me/avatar", { body: fd });
          toast("Photo updated.", "success");
        } catch (err) {
          toast(err.message, "error");
        }
      });
    }).catch((err) => {
      if (err.status === 401) location.href = "/login.html?next=/account-settings.html";
      else toast(err.message, "error");
    });
  });
})();
