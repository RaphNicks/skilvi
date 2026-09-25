// Adds a favicon (inline SVG data URI) and a per-page meta description to every HTML file.
const fs = require("fs");
const path = require("path");
const root = path.resolve(__dirname, "..");

const FAVICON = '<link rel="icon" href="assets/img/skilvi-favicon.png?v=2" type="image/png">';

const DESC = {
  "index.html": "Discover. Learn. Earn. Skilvi is Nigeria's marketplace for digital skills and hands-on trades — browse verified workers, hire with escrow protection, and get paid for your skills.",
  "search.html": "Search verified workers and services on Skilvi — from web development to plumbing and bookkeeping. Compare ratings, packages, and delivery times.",
  "category.html": "Browse Skilvi workers by category: web development, design, digital marketing, plumbing, house painting, bookkeeping, and more.",
  "worker-profile.html": "Worker profile on Skilvi — verified identity, ratings and reviews, packages, and secure in-platform payment via escrow.",
  "service-detail.html": "Service details on Skilvi: transparent packages, revision limits, delivery time, and escrow-protected payment.",
  "jobs.html": "Browse open jobs on Skilvi or post your own. Set a budget, receive proposals, and pay only when the job is done.",
  "job-detail.html": "Job detail on Skilvi: budget, timeline, and proposals — with secure escrow from award to payout.",
  "post-job.html": "Post a job on Skilvi in minutes: set your budget, choose a category, and get proposals from vetted workers.",
  "login.html": "Log in or create a free Skilvi account with your phone number. No email needed.",
  "help.html": "Skilvi help centre: how escrow works, fees and pricing, verification, withdrawals, disputes, on-site work, promotion, and reporting.",
  "terms.html": "Skilvi Terms of Service: accounts, orders, escrow, disputes, fees, and the rules that keep both sides safe.",
  "privacy.html": "Skilvi privacy policy: what we collect, how we process payments, and your rights under Nigeria's NDPR.",
  "order-detail.html": "Order detail on Skilvi: escrow status, milestones, messages, delivery, and dispute protection.",
  "dispute-detail.html": "Dispute detail on Skilvi: evidence, timeline, and how Skilvi mediates with funds frozen in escrow.",
  "checkout.html": "Secure checkout on Skilvi: pay by card, bank transfer, or mobile money. Funds are held in escrow until the job is done.",
  "404.html": "This page couldn't be found — but the Skilvi marketplace is right this way.",
  "about.html": "About Skilvi: why we built a marketplace for Nigeria — escrow that protects both sides, honest verification, and local payments in Naira.",
  "forgot-password.html": "Reset your Skilvi access: we'll send a one-time code to your phone number.",
  "payment-success.html": "Payment received — your Skilvi order is secured in escrow. View the receipt and track the order.",
  "review.html": "Rate your Skilvi worker: review the finished order, add tags and a comment, and help other buyers choose well.",
  "saved.html": "Your saved Skilvi workers — compare shortlisted professionals before you hire.",
  "verification.html": "Get verified on Skilvi: a one-time ₦5,000 identity check that shows clients you're real. Verification is not a skill certification.",
  "promotion.html": "Promote your Skilvi profile: search boost or category spotlight, always labelled as promoted, from ₦2,500 per week.",
  "notifications.html": "Skilvi notifications: payments, withdrawals, reviews, and order updates in one place.",
  "worker-dashboard.html": "Worker dashboard on Skilvi: earnings, active orders, new proposals, and your wallet at a glance.",
  "worker-orders.html": "Manage your Skilvi orders: deliver work, submit for approval, and get paid from escrow.",
  "worker-services.html": "Your Skilvi services: manage packages, pricing, and search visibility.",
  "worker-service-form.html": "Create or edit a Skilvi service with up to three packages, delivery times, and client questions.",
  "worker-jobs.html": "Browse open Skilvi jobs, send proposals, and track the ones you've applied to.",
  "worker-wallet.html": "Your Skilvi wallet: balance, escrow holds, and withdrawals to your Nigerian bank account.",
  "messages.html": "Messages on Skilvi — talk with clients and workers inside the platform, where escrow stays safe.",
  "disputes.html": "Open and track disputes on Skilvi. Escrow funds stay frozen until Skilvi resolves the issue.",
  "account-settings.html": "Skilvi account settings: profile, phone, payout details, and security.",
  "client-dashboard.html": "Client dashboard on Skilvi: your orders, payments, saved workers, and messages in one place."
};
const ADMIN_DESC = "Skilvi admin console: orders, escrow, payments, withdrawals, disputes, verification, and platform health.";

function process(file, desc) {
  const p = path.join(root, file);
  let s = fs.readFileSync(p, "utf8");
  let changed = false;
  if (!/rel="icon"/.test(s)) {
    s = s.replace(/(<meta name="viewport"[^>]*>)/, "$1\n  " + FAVICON);
    changed = true;
  }
  if (desc && !/<meta name="description"/.test(s)) {
    s = s.replace(/(<title>[^<]*<\/title>)/, `$1\n  <meta name="description" content="${desc}">`);
    changed = true;
  }
  if (changed) fs.writeFileSync(p, s);
  return changed;
}

let n = 0;
for (const f of fs.readdirSync(root).filter((f) => f.endsWith(".html"))) {
  n += process(f, DESC[f] || "Discover, learn, and earn on Skilvi — Nigeria's skills marketplace with escrow-protected payments.");
}
for (const f of fs.readdirSync(path.join(root, "admin")).filter((f) => f.endsWith(".html"))) {
  n += process("admin/" + f, ADMIN_DESC);
}
console.log("Meta pass done. Files updated:", n);
