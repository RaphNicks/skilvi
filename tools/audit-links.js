/* Audits every href/src in every HTML file for missing targets.
   Run: node tools/audit-links.js */
"use strict";
const fs = require("fs");
const path = require("path");
const root = path.join(__dirname, "..");
const files = [];
(function walk(d) {
  for (const f of fs.readdirSync(d)) {
    const p = path.join(d, f);
    if (fs.statSync(p).isDirectory()) walk(p);
    else if (f.endsWith(".html")) files.push(p);
  }
})(root);

const broken = [];
const deadHash = [];
for (const f of files) {
  const html = fs.readFileSync(f, "utf8");
  const dir = path.dirname(f);
  for (const m of html.matchAll(/(?:href|src)="([^"]*)"/g)) {
    const url = m[1];
    if (!url || url.startsWith("http") || url.startsWith("mailto:") || url.startsWith("tel:") || url.startsWith("data:")) continue;
    if (url === "#" || url === "") { deadHash.push(path.relative(root, f) + ' -> "#"'); continue; }
    if (url.startsWith("#")) continue;
    const clean = url.split("?")[0].split("#")[0];
    if (!clean) continue;
    const target = path.resolve(dir, clean);
    if (!fs.existsSync(target)) broken.push(path.relative(root, f) + "  ->  " + url);
  }
}
if (broken.length) {
  console.log("BROKEN LINKS (" + broken.length + "):");
  console.log(broken.join("\n"));
} else console.log("ALL FILE LINKS OK");
if (deadHash.length) {
  console.log("\nDEAD '#' LINKS (" + deadHash.length + "):");
  console.log(deadHash.join("\n"));
}
