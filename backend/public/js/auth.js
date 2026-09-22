(function () {
  "use strict";
  const { api, busy, toast, bindOtpBoxes } = window.SkApi;

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
  }

  function switchTab(mode) {
    document.querySelectorAll(".auth-tab").forEach((t) => t.classList.toggle("active", t.getAttribute("data-mode") === mode));
    document.querySelectorAll("#authStepMain .tab-panel").forEach((p) =>
      p.classList.toggle("active", p.getAttribute("data-panel") === mode)
    );
  }

  document.querySelectorAll(".auth-tab").forEach((tab) => {
    tab.addEventListener("click", (e) => {
      e.preventDefault();
      switchTab(tab.getAttribute("data-mode"));
    });
  });
  if (location.hash === "#regForm") switchTab("register");

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
        toast("Code sent to " + data.phone_mask, "success");
      } catch (err) {
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
    otpForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const btn = otpForm.querySelector('[type="submit"]');
      const code = readOtp();
      if (code.length !== 6) {
        toast("Enter the 6-digit code.", "error");
        return;
      }
      busy(btn, true);
      try {
        const purpose = document.getElementById("otpPurpose").value;
        const data = await api("/api/auth/verify", { body: { code, purpose } });
        toast("You're in.", "success");
        const next = new URLSearchParams(location.search).get("next");
        location.href = next || data.redirect || "/client-dashboard.html";
      } catch (err) {
        toast(err.message, "error");
      } finally {
        busy(btn, false);
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
