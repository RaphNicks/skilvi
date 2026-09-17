/* Migrates the static public header to the CSS-checkbox nav toggle
   (works without JS). Preserves per-page active state.
   Run: node tools/migrate-header.js */
"use strict";
const fs = require("fs");
const path = require("path");
const root = path.join(__dirname, "..");

const mark = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v6c0 4.4 3 7.4 7 9 4-1.6 7-4.6 7-9V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg>';
const menu = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>';

function header(active) {
  const nav = [
    ["Explore", "search.html", "explore"],
    ["Jobs", "jobs.html", "jobs"],
    ["How it works", "help.html", "help"]
  ];
  const links = nav.map((n) => '      <a href="' + n[1] + '"' + (n[2] === active ? ' class="active"' : "") + ">" + n[0] + "</a>").join("\n");
  const mobile = nav.map((n) => '    <a href="' + n[1] + '"' + (n[2] === active ? ' class="active"' : "") + ">" + n[0] + "</a>").join("\n");
  return '<header class="topbar">\n' +
    '  <input type="checkbox" id="navCheck" class="nav-check" aria-hidden="true">\n' +
    '  <div class="container topbar-in">\n' +
    '    <a class="brand" href="index.html"><span class="brand-mark">' + mark + "</span>Skilvi</a>\n" +
    '    <nav class="mainnav">\n' + links + "\n    </nav>\n" +
    '    <div class="topact">\n' +
    '      <a class="btn btn-ghost" href="login.html">Log in</a>\n' +
    '      <a class="btn btn-primary" href="login.html?mode=register">Join free</a>\n' +
    '    </div>\n' +
    '    <label class="icon-btn nav-toggle" for="navCheck" aria-label="Menu">' + menu + "</label>\n" +
    "  </div>\n" +
    '  <div class="mobile-nav" id="mobileNav">\n' + mobile + "\n" +
    '    <a href="login.html">Log in</a>\n' +
    '    <a href="login.html?mode=register">Join free</a>\n' +
    "  </div>\n</header>";
}

const files = fs.readdirSync(root).filter((f) => f.endsWith(".html"));
let done = 0;
for (const f of files) {
  const p = path.join(root, f);
  let html = fs.readFileSync(p, "utf8");
  if (!html.includes('<header class="topbar">')) continue;
  const active = (html.match(/data-active="([^"]+)"/) || [])[1] || "";
  html = html.replace(/<header class="topbar">[\s\S]*?<\/header>/, header(active));
  fs.writeFileSync(p, html);
  done++;
}
console.log("Migrated " + done + " public headers.");
