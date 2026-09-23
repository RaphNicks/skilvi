(function () {
  "use strict";
  const { api, busy, toast } = window.SkApi || {};
  if (!api) return;

  function $(s, r) { return (r || document).querySelector(s); }

  function fill(me) {
    if ($("#meName")) $("#meName").value = me.full_name || "";
    if ($("#meHeadline")) $("#meHeadline").value = me.headline || "";
    if ($("#meBio")) $("#meBio").value = me.bio || "";
    if ($("#meCity")) $("#meCity").value = me.city || "";
    if ($("#meState") && me.state) {
      const opt = Array.from($("#meState").options).find((o) => o.text === me.state || o.value === me.state);
      if (opt) $("#meState").value = opt.value;
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
        const current = window.prompt("Current password?");
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
