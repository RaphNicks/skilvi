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
  function credFields(form) {
    return form ? form.querySelectorAll('input[type="email"], input[type="password"]') : [];
  }
  function applyTab(mode) {
    document.querySelectorAll(".auth-tab").forEach((t) => t.classList.toggle("active", t.getAttribute("data-mode") === mode));
    document.querySelectorAll("#authStepMain .tab-panel").forEach((p) =>
      p.classList.toggle("active", p.getAttribute("data-panel") === mode)
    );
    const login = document.getElementById("loginForm");
    const reg = document.getElementById("regForm");
    credFields(login).forEach((el) => { el.disabled = mode !== "login"; });
    credFields(reg).forEach((el) => { el.disabled = mode !== "register"; });
  }
  function stayTop() {
    window.scrollTo(0, 0);
    requestAnimationFrame(function () { window.scrollTo(0, 0); });
  }
  function switchTab(mode) {
    applyTab(mode);
    const hash = hashFor(mode);
    // Must assign location.hash (not replaceState) so CSS :target updates.
    // Sign Up lands on #regForm; without this, :target keeps the register form stuck.
    if (location.hash !== hash) location.hash = hash;
    stayTop();
  }

  if ("scrollRestoration" in history) history.scrollRestoration = "manual";
  document.querySelectorAll(".auth-tab").forEach((tab) => {
    tab.addEventListener("click", (e) => {
      e.preventDefault();
      switchTab(tab.getAttribute("data-mode"));
    });
  });
  applyTab(modeFromHash());
  stayTop();
  window.addEventListener("hashchange", () => {
    applyTab(modeFromHash());
    stayTop();
  });
  window.addEventListener("load", stayTop);

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
    const regPass = document.getElementById("regPass");
    if (regPass) {
      const unlock = () => regPass.removeAttribute("readonly");
      regPass.addEventListener("focus", unlock);
      regPass.addEventListener("pointerdown", unlock);
    }
    const joinRadios = regForm.querySelectorAll('input[name="join_as"]');
    const dobEl = document.getElementById("regDob");
    function joinAs() {
      const el = regForm.querySelector('input[name="join_as"]:checked');
      return (el && el.value) || "";
    }
    function syncDob() {
      const worker = joinAs() !== "client" && joinAs() !== "";
      if (dobEl) {
        dobEl.required = worker;
        if (!worker) dobEl.value = "";
      }
    }
    function setRegStep(id) {
      const el = document.getElementById(id);
      if (el) el.checked = true;
    }
    function regStep() {
      const el = regForm.querySelector('input[name="reg_step"]:checked');
      return (el && el.value) || "method";
    }
    function validEmailPass() {
      const email = (document.getElementById("regEmail") || {}).value || "";
      const pass = (document.getElementById("regPass") || {}).value || "";
      if (!email.trim() || !email.includes("@")) {
        toast("Enter your email.", "error");
        const em = document.getElementById("regEmail");
        if (em) em.focus();
        return false;
      }
      if (pass.length < 8) {
        toast("Password must be at least 8 characters.", "error");
        const pw = document.getElementById("regPass");
        if (pw) { pw.removeAttribute("readonly"); pw.focus(); }
        return false;
      }
      return true;
    }
    joinRadios.forEach((r) => r.addEventListener("change", syncDob));
    syncDob();
    regForm.addEventListener("click", (e) => {
      const next = e.target && e.target.closest && e.target.closest("[data-reg-next]");
      const back = e.target && e.target.closest && e.target.closest("[data-reg-back]");
      if (next) {
        const to = next.getAttribute("data-reg-next");
        if (to === "join") {
          e.preventDefault();
          if (!validEmailPass()) return;
          const open = document.getElementById("regEmailOpen");
          if (open) open.checked = true;
          setRegStep("regStepJoin");
          stayTop();
          return;
        }
        if (to === "details") {
          e.preventDefault();
          if (!joinAs()) {
            toast("Choose Worker, Client, or Both.", "error");
            return;
          }
          setRegStep("regStepDetails");
          stayTop();
        }
      }
      if (back) stayTop();
    });
    regForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const step = regStep();
      if (step === "method") {
        if (validEmailPass()) {
          setRegStep("regStepJoin");
          stayTop();
        }
        return;
      }
      if (step === "join") {
        if (!joinAs()) {
          toast("Choose Worker, Client, or Both.", "error");
          return;
        }
        setRegStep("regStepDetails");
        stayTop();
        return;
      }
      const btn = regForm.querySelector('[type="submit"]');
      busy(btn, true);
      try {
        const fd = new FormData(regForm);
        const as = joinAs();
        const data = await api("/api/auth/register", {
          body: {
            full_name: fd.get("full_name"),
            phone: fd.get("phone"),
            email: fd.get("email"),
            password: fd.get("new_password") || fd.get("password"),
            join_as: as,
            country: fd.get("country"),
            state: fd.get("state"),
            city: fd.get("city"),
            dob: as === "client" ? "" : fd.get("dob"),
            gender: fd.get("gender"),
            heard_about: fd.get("heard_about"),
          },
        });
        showOtp(data, "register");
        toast("Code sent to " + data.phone_mask, "success");
      } catch (err) {
        if (err.code === "blocked" && window.SkApi && window.SkApi.dialog) {
          window.SkApi.dialog.alert({
            title: "This email cannot be used",
            body: "This email cannot be used to open an account. Contact support for further assistance:",
            href: "mailto:support@skilvi.ng",
            hrefLabel: "support@skilvi.ng",
          });
        } else {
          toast(err.message, "error");
        }
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

  const q = new URLSearchParams(location.search);
  if (q.get("google") === "error") {
    const gmsg = q.get("msg") || "Google sign-in failed. Try again.";
    if (/cannot be used/i.test(gmsg) && window.SkApi && window.SkApi.dialog) {
      window.SkApi.dialog.alert({
        title: "This email cannot be used",
        body: "This email cannot be used to open an account. Contact support for further assistance:",
        href: "mailto:support@skilvi.ng",
        hrefLabel: "support@skilvi.ng",
      });
    } else {
      toast(gmsg, "error");
    }
  }

  const completeForm = document.getElementById("completeForm");
  if (completeForm) {
    const dobEl = document.getElementById("cpDob");
    function joinAs() {
      const el = completeForm.querySelector('input[name="join_as"]:checked');
      return (el && el.value) || "";
    }
    function syncDob() {
      const worker = joinAs() !== "client" && joinAs() !== "";
      if (dobEl) {
        dobEl.required = worker;
        if (!worker) dobEl.value = "";
      }
    }
    function cpStep() {
      const el = completeForm.querySelector('input[name="cp_step"]:checked');
      return (el && el.value) || "join";
    }
    completeForm.querySelectorAll('input[name="join_as"]').forEach((r) => r.addEventListener("change", syncDob));
    syncDob();
    completeForm.addEventListener("click", (e) => {
      const next = e.target && e.target.closest && e.target.closest("[data-cp-next]");
      if (next) {
        e.preventDefault();
        if (!joinAs()) {
          toast("Choose Worker, Client, or Both.", "error");
          return;
        }
        const det = document.getElementById("cpStepDetails");
        if (det) det.checked = true;
        stayTop();
      }
    });
    completeForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (cpStep() !== "details") {
        if (!joinAs()) {
          toast("Choose Worker, Client, or Both.", "error");
          return;
        }
        const det = document.getElementById("cpStepDetails");
        if (det) det.checked = true;
        stayTop();
        return;
      }
      if (!joinAs()) {
        toast("Choose Worker, Client, or Both.", "error");
        return;
      }
      const btn = completeForm.querySelector('[type="submit"]');
      busy(btn, true);
      try {
        const fd = new FormData(completeForm);
        const as = joinAs();
        const data = await api("/api/auth/complete-profile", {
          body: {
            full_name: fd.get("full_name"),
            join_as: as,
            phone: fd.get("phone"),
            country: fd.get("country"),
            state: fd.get("state"),
            city: fd.get("city"),
            dob: as === "client" ? "" : fd.get("dob"),
            gender: fd.get("gender"),
            heard_about: fd.get("heard_about"),
            terms: completeForm.querySelector('[name="terms"]') && completeForm.querySelector('[name="terms"]').checked,
          },
        });
        if (data && data.token && window.SkApi && window.SkApi.setTabToken) {
          window.SkApi.setTabToken(data.token);
        }
        window.location.replace(safeDest(data && data.redirect));
      } catch (err) {
        if (window.SkApi && window.SkApi.showFieldErrors) window.SkApi.showFieldErrors(err, completeForm);
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
