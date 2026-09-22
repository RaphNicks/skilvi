(function () {
  "use strict";
  const { api, busy, toast } = window.SkApi;

  function fill() {
    return api("/api/me").then((me) => {
      const name = document.querySelector('.field input[value], .card input.input');
      const inputs = document.querySelectorAll(".card .input, .card .textarea, .card .select");
      // Profile card is first.
      const cards = document.querySelectorAll("main .card.card-pad");
      const profile = cards[0];
      if (profile) {
        const fields = profile.querySelectorAll(".input, .textarea, .select");
        // full name, headline, bio, state, city
        if (fields[0]) fields[0].value = me.full_name || "";
        if (fields[1]) fields[1].value = me.headline || "";
        if (fields[2]) fields[2].value = me.bio || "";
        if (fields[3] && me.state) {
          const opt = Array.from(fields[3].options).find((o) => o.text === me.state || o.value === me.state);
          if (opt) fields[3].value = opt.value;
        }
        if (fields[4]) fields[4].value = me.city || "";
        const av = profile.querySelector(".avatar");
        if (av) av.textContent = me.initials;
      }
      const security = cards[1];
      if (security) {
        const fields = security.querySelectorAll(".input");
        if (fields[0]) fields[0].value = me.phone_fmt || "";
        if (fields[1]) fields[1].value = me.email || "";
      }
      const prefs = cards[2];
      if (prefs) {
        const boxes = prefs.querySelectorAll('input[type="checkbox"]');
        // 0 order email (disabled), 1 sms, 2 jobs, 3 marketing
        if (boxes[1]) boxes[1].checked = !!me.notify_sms;
        if (boxes[2]) boxes[2].checked = !!me.notify_jobs;
        if (boxes[3]) boxes[3].checked = !!me.notify_marketing;
      }
      document.querySelectorAll(".su-name").forEach((el, i) => {
        el.textContent = i === 0 ? me.full_name : me.full_name.split(" ")[0];
      });
      document.querySelectorAll(".avatar.sm, .avatar.lg").forEach((el) => {
        el.textContent = me.initials;
      });
      window.__me = me;
      return me;
    }).catch((err) => {
      if (err.status === 401) location.href = "/login.html?next=/account-settings.html";
      else toast(err.message, "error");
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    fill().then((me) => {
      if (!me) return;
      const cards = document.querySelectorAll("main .card.card-pad");
      const saveBtns = document.querySelectorAll("[data-save]");
      if (saveBtns[0]) {
        saveBtns[0].addEventListener("click", async () => {
          const profile = cards[0];
          const fields = profile.querySelectorAll(".input, .textarea, .select");
          const btn = saveBtns[0];
          busy(btn, true);
          try {
            await api("/api/me", {
              body: {
                full_name: fields[0].value,
                headline: fields[1].value,
                bio: fields[2].value,
                state: fields[3].options[fields[3].selectedIndex].text,
                city: fields[4].value,
              },
            });
            toast("Profile saved.", "success");
          } catch (err) {
            toast(err.message, "error");
          } finally {
            busy(btn, false);
          }
        });
      }
      if (saveBtns[1]) {
        saveBtns[1].addEventListener("click", async () => {
          const prefs = cards[2];
          const boxes = prefs.querySelectorAll('input[type="checkbox"]');
          const btn = saveBtns[1];
          busy(btn, true);
          try {
            await api("/api/me", {
              body: {
                notify_sms: boxes[1] && boxes[1].checked,
                notify_jobs: boxes[2] && boxes[2].checked,
                notify_marketing: boxes[3] && boxes[3].checked,
              },
            });
            toast("Preferences saved.", "success");
          } catch (err) {
            toast(err.message, "error");
          } finally {
            busy(btn, false);
          }
        });
      }
      const security = cards[1];
      if (security) {
        const btns = security.querySelectorAll("button");
        const fields = security.querySelectorAll(".input");
        // phone change is Phase 1 follow-up (OTP to new number)
        if (btns[1] && fields[1]) {
          btns[1].addEventListener("click", async () => {
            busy(btns[1], true);
            try {
              await api("/api/me/email", { body: { email: fields[1].value } });
              toast("Email updated.", "success");
            } catch (err) {
              toast(err.message, "error");
            } finally {
              busy(btns[1], false);
            }
          });
        }
        if (btns[2] && fields[2]) {
          btns[2].addEventListener("click", async () => {
            const current = window.prompt("Current password?");
            if (!current) return;
            busy(btns[2], true);
            try {
              await api("/api/me/password", { body: { current, password: fields[2].value } });
              toast("Password updated.", "success");
              fields[2].value = "";
            } catch (err) {
              toast(err.message, "error");
            } finally {
              busy(btns[2], false);
            }
          });
        }
      }
    });
  });
})();
