/* Country → state → city from /api/geo (dr5hn world list). */
(function () {
  "use strict";
  const api = () => window.SkApi && window.SkApi.api;

  async function fillSelect(sel, rows, placeholder, current) {
    if (!sel) return;
    const want = current || sel.getAttribute("data-current") || sel.value || "";
    sel.innerHTML = '<option value="">' + placeholder + "</option>";
    (rows || []).forEach((r) => {
      const opt = document.createElement("option");
      opt.value = r.name;
      opt.textContent = r.emoji ? r.emoji + " " + r.name : r.name;
      if (r.iso2) opt.setAttribute("data-iso2", r.iso2);
      if (r.id) opt.setAttribute("data-id", String(r.id));
      if (r.phonecode) opt.setAttribute("data-phonecode", String(r.phonecode));
      sel.appendChild(opt);
    });
    if (want) {
      const hit = Array.from(sel.options).find((o) => o.value === want || o.getAttribute("data-iso2") === want);
      if (hit) sel.value = hit.value;
    }
  }

  async function bind(spec) {
    const fetch = api();
    if (!fetch) return;
    const country = document.querySelector(spec.country);
    const state = document.querySelector(spec.state);
    const city = document.querySelector(spec.city);
    if (!country || !state) return;
    const phone = spec.phone ? document.querySelector(spec.phone) : null;
    let countries = [];
    try {
      countries = await fetch("/api/geo/countries");
    } catch (e) {
      return;
    }
    const preset = spec.values || {};
    const initialCountry = (preset.country != null && preset.country !== "")
      ? preset.country
      : (spec.defaultCountry !== undefined ? spec.defaultCountry : "Nigeria");
    await fillSelect(country, countries, spec.countryPh || "Select country", initialCountry || country.getAttribute("data-current") || "");
    async function loadStates() {
      const opt = country.options[country.selectedIndex];
      const id = opt && opt.getAttribute("data-id");
      state.disabled = !id;
      city && (city.disabled = true);
      if (phone && opt) {
        const code = opt.getAttribute("data-phonecode");
        if (code && !phone.value) phone.placeholder = "+" + code + " …";
      }
      if (!id) {
        await fillSelect(state, [], "Select state / region", "");
        if (city) await fillSelect(city, [], "Select city", "");
        return;
      }
      const rows = await fetch("/api/geo/states?country_id=" + encodeURIComponent(id));
      await fillSelect(state, rows, spec.statePh || "Select state / region", preset.state || state.getAttribute("data-current"));
      preset.state = "";
      await loadCities();
    }
    async function loadCities() {
      if (!city) return;
      const opt = state.options[state.selectedIndex];
      const id = opt && opt.getAttribute("data-id");
      city.disabled = !id;
      if (!id) {
        await fillSelect(city, [], "Select city", "");
        return;
      }
      try {
        const rows = await fetch("/api/geo/cities?state_id=" + encodeURIComponent(id));
        await fillSelect(city, rows, rows.length ? "Select city" : "Type a city", preset.city || city.getAttribute("data-current"));
        if (!rows.length && city.tagName === "SELECT") {
          /* keep select; user can still pick empty and we allow typed name via data */
        }
      } catch (e) {
        await fillSelect(city, [], "Select city", preset.city);
      }
      preset.city = "";
    }
    country.addEventListener("change", loadStates);
    state.addEventListener("change", loadCities);
    await loadStates();
  }

  window.SkGeo = { bind, fillSelect };
})();
