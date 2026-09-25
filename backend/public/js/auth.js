(function () {
  "use strict";
  if (!window.SkApi) {
    console.error("Skilvi API helper missing — open http://127.0.0.1:8080/login.html (PHP server), not the HTML file.");
    return;
  }
  const { api, busy, toast, bindOtpBoxes } = window.SkApi;

  function safeDest(serverRedirect) {
    const home = serverRedirect || "/client-dashboard.html";
    const next = new URLSearchParams(location.search).get("next") || "";
    if (!next.startsWith("/") || next.startsWith("//") || next.includes("://") || next.includes("\\")) {
      return home;
    }
    if (next.indexOf("/admin") === 0 && String(home).indexOf("/admin") !== 0) {
      return home;
    }
    return next;
  }

  function showOtp(data, purpose) {
    const main = document.getElementById("authStepMain");
    const otp = document.getElementById("authStepOtp");
    if (main) main.style.display = "none";
    if (otp) otp.style.display = "";
    const mask = document.getElementById("otpMask");
    if (mask) mask.textContent = data.phone_mask || "your number";
    const purposeEl = document.getElementById("otpPurpose");
    if (purposeEl) purposeEl.value = purpose;
    const dev = document.getElementById("devCode");
    if (dev && data.dev_code) {
      dev.style.display = "";
      dev.textContent = "Dev code: " + data.dev_code;
    }
    const first = document.querySelector(".otp-box");
    if (first) first.focus();
    if (data.dev_code) {
      toast("Dev code " + data.dev_code + " — type it in the six boxes, then Verify.", "success");
    }
    if (data.mail && data.mail.ok === false) {
      toast("Email did not send: " + (data.mail.error || data.mail.driver), "error");
    } else if (data.channel === "email" && data.mail && data.mail.ok) {
      toast("Code emailed to " + (data.phone_mask || "you"), "success");
    }
  }

  function hashFor(mode) {
    return mode === "register" ? "#regForm" : "#loginForm";
  }
  function modeFromHash() {
    return location.hash === "#regForm" ? "register" : "login";
  }
  function applyTab(mode) {
    document.querySelectorAll(".auth-tab").forEach((t) => t.classList.toggle("active", t.getAttribute("data-mode") === mode));
    document.querySelectorAll("#authStepMain .tab-panel").forEach((p) =>
      p.classList.toggle("active", p.getAttribute("data-panel") === mode)
    );
  }
  function switchTab(mode) {
    applyTab(mode);
    const hash = hashFor(mode);
    // Must assign location.hash (not replaceState) so CSS :target updates.
    // Sign Up lands on #regForm; without this, :target keeps the register form stuck.
    if (location.hash !== hash) location.hash = hash;
  }

  document.querySelectorAll(".auth-tab").forEach((tab) => {
    tab.addEventListener("click", (e) => {
      e.preventDefault();
      switchTab(tab.getAttribute("data-mode"));
    });
  });
  applyTab(modeFromHash());
  window.addEventListener("hashchange", () => applyTab(modeFromHash()));

  const readOtp = bindOtpBoxes(document.getElementById("otpBoxes"));

  const loginForm = document.getElementById("loginForm");
  if (loginForm) {
    loginForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const btn = loginForm.querySelector('[type="submit"]');
      busy(btn, true);
      try {
        const fd = new FormData(loginForm);
        const data = await api("/api/auth/login", {
          body: { identifier: fd.get("identifier"), password: fd.get("password") },
        });
        showOtp(data, "login");
        toast("Code sent to " + data.phone_mask + (data.channel === "email" ? " (email)" : ""), "success");
      } catch (err) {
        if (window.SkApi && window.SkApi.showFieldErrors) window.SkApi.showFieldErrors(err, loginForm);
        toast(err.message, "error");
      } finally {
        busy(btn, false);
      }
    });
  }

  const regForm = document.getElementById("regForm");
  if (regForm) {
    regForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const btn = regForm.querySelector('[type="submit"]');
      busy(btn, true);
      try {
        const fd = new FormData(regForm);
        const data = await api("/api/auth/register", {
          body: {
            full_name: fd.get("full_name"),
            phone: fd.get("phone"),
            email: fd.get("email"),
            password: fd.get("password"),
            join_as: fd.get("join_as"),
            country: fd.get("country"),
            state: fd.get("state"),
            city: fd.get("city"),
            dob: fd.get("dob"),
            gender: fd.get("gender"),
          },
        });
        showOtp(data, "register");
        toast("Code sent to " + data.phone_mask, "success");
      } catch (err) {
        toast(err.message, "error");
      } finally {
        busy(btn, false);
      }
    });
  }

  const otpForm = document.getElementById("otpForm");
  if (otpForm) {
    let verifying = false;
    otpForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      e.stopImmediatePropagation();
      if (verifying) return;
      const btn = otpForm.querySelector('[type="submit"]');
      const status = document.getElementById("otpStatus");
      const code = readOtp();
      if (code.length !== 6) {
        toast("Enter all 6 digits, then click Verify.", "error");
        if (status) status.textContent = "Enter all 6 digits.";
        return;
      }
      verifying = true;
      busy(btn, true);
      if (status) status.textContent = "Checking code…";
      try {
        const purposeEl = document.getElementById("otpPurpose");
        const purpose = (purposeEl && purposeEl.value) || "login";
        const data = await api("/api/auth/verify", { body: { code, purpose } });
        if (data && data.token && window.SkApi && window.SkApi.setTabToken) {
          window.SkApi.setTabToken(data.token);
        }
        if (status) status.textContent = "You're in — opening your dashboard…";
        window.location.replace(safeDest(data && data.redirect));
      } catch (err) {
        verifying = false;
        busy(btn, false);
        if (status) status.textContent = err.message || "Could not verify.";
        toast(err.message, "error");
      }
    });
  }

  const resend = document.getElementById("otpResend");
  if (resend) {
    resend.addEventListener("click", async () => {
      try {
        const data = await api("/api/auth/resend", { body: {} });
        const dev = document.getElementById("devCode");
        if (dev && data.dev_code) {
          dev.style.display = "";
          dev.textContent = "Dev code: " + data.dev_code;
        }
        toast("A new code has been sent to your phone", "success");
      } catch (err) {
        toast(err.message, "error");
      }
    });
  }

  const fpSend = document.getElementById("fpSend");
  if (fpSend) {
    fpSend.addEventListener("submit", async (e) => {
      e.preventDefault();
      const btn = fpSend.querySelector('[type="submit"]');
      busy(btn, true);
      try {
        const fd = new FormData(fpSend);
        const data = await api("/api/auth/password/forgot", { body: { identifier: fd.get("identifier") } });
        document.getElementById("fpForm").style.display = "none";
        document.getElementById("fpSent").style.display = "";
        document.getElementById("fpEcho").textContent = data.echo || data.phone_mask || "your number";
        const dev = document.getElementById("devCode");
        if (dev && data.dev_code) {
          dev.style.display = "";
          dev.textContent = "Dev code: " + data.dev_code;
        }
      } catch (err) {
        toast(err.message, "error");
      } finally {
        busy(btn, false);
      }
    });
  }

  const fpReset = document.getElementById("fpReset");
  if (fpReset) {
    fpReset.addEventListener("submit", async (e) => {
      e.preventDefault();
      const btn = fpReset.querySelector('[type="submit"]');
      busy(btn, true);
      try {
        const fd = new FormData(fpReset);
        const data = await api("/api/auth/password/reset", {
          body: { code: fd.get("code"), password: fd.get("password") },
        });
        toast("Password updated. You're in.", "success");
        if (data && data.token && window.SkApi && window.SkApi.setTabToken) {
          window.SkApi.setTabToken(data.token);
        }
        location.href = data.redirect || "/login.html";
      } catch (err) {
        toast(err.message, "error");
      } finally {
        busy(btn, false);
      }
    });
  }

  const fpResend = document.getElementById("fpResend");
  if (fpResend) {
    fpResend.addEventListener("click", async () => {
      try {
        const data = await api("/api/auth/resend", { body: {} });
        const dev = document.getElementById("devCode");
        if (dev && data.dev_code) {
          dev.style.display = "";
          dev.textContent = "Dev code: " + data.dev_code;
        }
        toast("A new code has been sent to your phone", "success");
      } catch (err) {
        toast(err.message, "error");
      }
    });
  }
})();
