/* ============================================================
   SKILVI — mock data (frontend prototype only)
   In production, every structure here maps to a PHP JSON
   endpoint. Keep field names aligned with the PRD data model.
   ============================================================ */
window.MOCK = {
  workers: [
    { id: "w01", name: "Chinedu Okafor", init: "CO", tone: "a1", headline: "Frontend developer & WordPress specialist", skill: "Web Development", city: "Port Harcourt", state: "Rivers", mode: "remote", rating: 4.9, reviews: 132, jobs: 187, resp: "1 hr", verified: true, from: 45000, promo: false },
    { id: "w02", name: "Aisha Bello", init: "AB", tone: "a2", headline: "UI/UX designer for web & mobile", skill: "UI/UX Design", city: "Abuja", state: "FCT", mode: "remote", rating: 4.8, reviews: 98, jobs: 120, resp: "2 hrs", verified: true, from: 30000, promo: false },
    { id: "w03", name: "Emeka Obi", init: "EO", tone: "a3", headline: "Plumber — 12 years experience", skill: "Plumbing", city: "Lagos", state: "Lagos", mode: "on-site", rating: 4.9, reviews: 210, jobs: 342, resp: "3 hrs", verified: true, from: 8000, promo: true },
    { id: "w04", name: "Ngozi Eze", init: "NE", tone: "a4", headline: "Logo, brand & social media design", skill: "Graphic Design", city: "Lagos", state: "Lagos", mode: "remote", rating: 4.7, reviews: 156, jobs: 265, resp: "4 hrs", verified: true, from: 15000, promo: false },
    { id: "w05", name: "Tunde Adeyemi", init: "TA", tone: "a5", headline: "Electrician — wiring, sockets, panels", skill: "Electrical", city: "Ibadan", state: "Oyo", mode: "on-site", rating: 4.8, reviews: 88, jobs: 140, resp: "1 day", verified: false, from: 12000, promo: false },
    { id: "w06", name: "Ifeanyi Okoro", init: "IO", tone: "a1", headline: "Tiler & mason — clean finishing", skill: "Tiling & Masonry", city: "Port Harcourt", state: "Rivers", mode: "on-site", rating: 4.9, reviews: 64, jobs: 91, resp: "2 hrs", verified: true, from: 25000, promo: false },
    { id: "w07", name: "Amara Nwosu", init: "AN", tone: "a2", headline: "Video editor & motion graphics", skill: "Video & Motion", city: "Enugu", state: "Enugu", mode: "remote", rating: 4.6, reviews: 71, jobs: 104, resp: "6 hrs", verified: false, from: 20000, promo: false },
    { id: "w08", name: "Femi Balogun", init: "FB", tone: "a3", headline: "Generator, inverter & AC installation", skill: "Generator & Power", city: "Lagos", state: "Lagos", mode: "on-site", rating: 4.8, reviews: 133, jobs: 205, resp: "1 hr", verified: true, from: 35000, promo: false },
    { id: "w09", name: "Kemi Adeleke", init: "KA", tone: "a4", headline: "Bookkeeper & small-business accountant", skill: "Accounting & Bookkeeping", city: "Lagos", state: "Lagos", mode: "remote", rating: 4.9, reviews: 54, jobs: 87, resp: "3 hrs", verified: true, from: 10000, promo: false },
    { id: "w10", name: "Yusuf Garba", init: "YG", tone: "a5", headline: "House & office painter", skill: "Painting", city: "Kano", state: "Kano", mode: "on-site", rating: 4.7, reviews: 45, jobs: 78, resp: "1 day", verified: false, from: 18000, promo: false },
    { id: "w11", name: "Blessing Umoh", init: "BU", tone: "a1", headline: "Copywriter & content editor", skill: "Writing & Content", city: "Abuja", state: "FCT", mode: "remote", rating: 4.8, reviews: 66, jobs: 93, resp: "5 hrs", verified: true, from: 12000, promo: false },
    { id: "w12", name: "Halima Sani", init: "HS", tone: "a2", headline: "Tailor & fashion stylist", skill: "Fashion & Tailoring", city: "Kano", state: "Kano", mode: "on-site", rating: 4.8, reviews: 59, jobs: 84, resp: "4 hrs", verified: true, from: 15000, promo: false }
  ],

  services: [
    { workerId: "w01", title: "Website development — landing pages to full sites", from: 45000 },
    { workerId: "w02", title: "UI/UX design — web app & mobile screens", from: 30000 },
    { workerId: "w03", title: "Plumbing — installations, repairs & leak fixing", from: 8000 },
    { workerId: "w04", title: "Brand identity — logo, brand kit & socials", from: 15000 },
    { workerId: "w05", title: "Electrical work — wiring, sockets, panel changes", from: 12000 },
    { workerId: "w06", title: "Tiling & masonry — bathrooms, kitchens, floors", from: 25000 },
    { workerId: "w07", title: "Video editing — YouTube, ads & brand films", from: 20000 },
    { workerId: "w08", title: "Generator & inverter installation + AC service", from: 35000 },
    { workerId: "w09", title: "Bookkeeping & monthly financial reports", from: 10000 },
    { workerId: "w10", title: "Painting — interior, exterior & touch-ups", from: 18000 },
    { workerId: "w11", title: "Copywriting — websites, ads & brand voice", from: 12000 },
    { workerId: "w12", title: "Tailoring — custom fits, alterations, event wear", from: 15000 }
  ],

  jobs: [
    { id: "j01", title: "Bathroom tiling — 2 rooms, Lekki Phase 1", cat: "Tiling & Masonry", mode: "on-site", loc: "Lekki, Lagos", budget: 300000, budgetType: "fixed", time: "2 days ago", proposals: 5, client: "Adaeze Boutique", clientRating: 4.8, verified: true, deadline: "Oct 10, 2026" },
    { id: "j02", title: "Landing page for a restaurant (Figma to web)", cat: "Web Development", mode: "remote", loc: "Remote", budget: 85000, budgetType: "fixed", time: "5 hrs ago", proposals: 3, client: "Naija Foods Ltd", clientRating: 4.9, verified: true, deadline: "Oct 2, 2026" },
    { id: "j03", title: "Bookkeeping & monthly reports for a fashion brand", cat: "Accounting & Bookkeeping", mode: "remote", loc: "Remote", budget: 60000, budgetType: "fixed", time: "1 day ago", proposals: 2, client: "Zara Wears", clientRating: 4.6, verified: false, deadline: "—" },
    { id: "j04", title: "Full electrical wiring — 4-bed bungalow, GRA Port Harcourt", cat: "Electrical", mode: "on-site", loc: "GRA, Port Harcourt", budget: 450000, budgetType: "negotiable", time: "3 days ago", proposals: 4, client: "M. Okonkwo", clientRating: 5.0, verified: true, deadline: "Oct 25, 2026" },
    { id: "j05", title: "Logo + brand kit for a specialty cafe", cat: "Graphic Design", mode: "remote", loc: "Remote", budget: 45000, budgetType: "fixed", time: "8 hrs ago", proposals: 7, client: "Brew & Co", clientRating: 4.7, verified: true, deadline: "Sep 30, 2026" },
    { id: "j06", title: "Install 10kVA generator + inverter, Ikoyi", cat: "Generator & Power", mode: "on-site", loc: "Ikoyi, Lagos", budget: 180000, budgetType: "fixed", time: "6 days ago", proposals: 2, client: "T. Balogun", clientRating: 4.5, verified: false, deadline: "Oct 5, 2026" }
  ],

  orders: [
    { id: "OR-1042", title: "Landing page — Naija Foods", party: "Chinedu Okafor", tone: "a1", amount: 85000, state: "delivered", stateLabel: "Awaiting approval", date: "Sep 14, 2026" },
    { id: "OR-1036", title: "Logo design — Adaeze Boutique", party: "Ngozi Eze", tone: "a4", amount: 45000, state: "in_progress", stateLabel: "In progress", date: "Sep 11, 2026" },
    { id: "OR-1028", title: "Bathroom tiling — Lekki", party: "Ifeanyi Okoro", tone: "a1", amount: 300000, state: "completed", stateLabel: "Completed", date: "Sep 2, 2026" },
    { id: "OR-1019", title: "Wiring — 4-bed bungalow, GRA", party: "Tunde Adeyemi", tone: "a5", amount: 450000, state: "paid", stateLabel: "Paid · worker to start", date: "Aug 28, 2026" },
    { id: "OR-1007", title: "Bookkeeping — March close", party: "Kemi Adeleke", tone: "a4", amount: 60000, state: "settled", stateLabel: "Settled", date: "Aug 12, 2026" }
  ],

  wallet: {
    available: 412500,
    pending: 85000,
    lifetime: 3240000,
    ledger: [
      { date: "Sep 15, 2026", type: "settlement", label: "Settlement — OR-1031 (WordPress site)", ref: "OR-1031", amount: 76500, bal: 412500 },
      { date: "Sep 15, 2026", type: "commission", label: "Commission — 10%", ref: "OR-1031", amount: -8500, bal: 336000 },
      { date: "Sep 12, 2026", type: "withdrawal", label: "Withdrawal — GTBank ····0234", ref: "WD-2214", amount: -150000, bal: 258000 },
      { date: "Sep 8, 2026", type: "settlement", label: "Settlement — OR-1024 (Landing page)", ref: "OR-1024", amount: 67500, bal: 408000 },
      { date: "Sep 8, 2026", type: "commission", label: "Commission — 10%", ref: "OR-1024", amount: -7500, bal: 340500 },
      { date: "Sep 1, 2026", type: "settlement", label: "Settlement — OR-1011 (Shopify fixes)", ref: "OR-1011", amount: 45000, bal: 375000 }
    ],
    withdrawals: [
      { id: "WD-2214", date: "Sep 12, 2026", amount: 150000, bank: "GTBank ····0234", state: "paid", stateLabel: "Paid" },
      { id: "WD-2190", date: "Sep 5, 2026", amount: 200000, bank: "GTBank ····0234", state: "paid", stateLabel: "Paid" },
      { id: "WD-2156", date: "Aug 26, 2026", amount: 90000, bank: "Access ····8871", state: "failed", stateLabel: "Failed · wrong account" },
      { id: "WD-2140", date: "Aug 20, 2026", amount: 120000, bank: "GTBank ····0234", state: "paid", stateLabel: "Paid" }
    ]
  },

  proposals: [
    { jobId: "j01", job: "Bathroom tiling — 2 rooms, Lekki Phase 1", price: 280000, days: 12, status: "pending", statusLabel: "Pending", date: "Sep 15, 2026" },
    { jobId: "j02", job: "Landing page for a restaurant", price: 85000, days: 10, status: "accepted", statusLabel: "Accepted", date: "Sep 13, 2026" },
    { jobId: "j05", job: "Logo + brand kit for a cafe", price: 40000, days: 7, status: "pending", statusLabel: "Pending", date: "Sep 16, 2026" },
    { jobId: "j04", job: "Full electrical wiring — 4-bed bungalow", price: 420000, days: 30, status: "rejected", statusLabel: "Rejected", date: "Sep 10, 2026" },
    { jobId: "j06", job: "Install 10kVA generator + inverter", price: 175000, days: 5, status: "pending", statusLabel: "Pending", date: "Sep 16, 2026" }
  ],

  reviews: [
    { name: "Aisha B.", init: "AB", tone: "a2", stars: 5, date: "Sep 14, 2026", tags: ["Delivered on time", "Great communication"], text: "Chinedu rebuilt our landing page in 8 days — two of the included revisions. The page loads fast and our enquiries went up. Paid through Skilvi escrow, zero stress." },
    { name: "M. Okonkwo", init: "MO", tone: "a3", stars: 5, date: "Aug 30, 2026", tags: ["Professional", "Clean work"], text: "Very organised. He shared a file list of everything that was delivered and explained how to manage it ourselves. Worth every naira." },
    { name: "Zara Wears", init: "ZW", tone: "a4", stars: 4, date: "Aug 18, 2026", tags: ["Good value"], text: "Great work overall. One revision round took a bit longer than expected but the final result is exactly what we asked for." }
  ],

  disputes: [
    { id: "DSP-2031", order: "OR-1019", title: "Wiring — 4-bed bungalow, GRA", openedBy: "Worker", reason: "Client unresponsive", state: "under_review", stateLabel: "Under review", opened: "Sep 13, 2026", sla: "2 days left", amount: 450000 },
    { id: "DSP-1988", order: "OR-0994", title: "Logo revision — Brew & Co", openedBy: "Client", reason: "Work not as described", state: "closed", stateLabel: "Closed · 60/40 split", opened: "Aug 22, 2026", resolved: "Sep 1, 2026", amount: 45000 }
  ],

  admin: {
    users: [
      { id: "u1", name: "Chinedu Okafor", init: "CO", tone: "a1", phone: "+234 803 111 2233", role: "Worker", state: "active", stateLabel: "Active", orders: 187, flags: 0, joined: "Jan 2026" },
      { id: "u2", name: "Adaeze Obi", init: "AO", tone: "a2", phone: "+234 805 222 3344", role: "Client", state: "active", stateLabel: "Active", orders: 12, flags: 0, joined: "Feb 2026" },
      { id: "u3", name: "Peter Anya", init: "PA", tone: "a3", phone: "+234 806 333 4455", role: "Worker", state: "suspended", stateLabel: "Suspended", orders: 8, flags: 3, joined: "Mar 2026" },
      { id: "u4", name: "Ngozi Eze", init: "NE", tone: "a4", phone: "+234 809 444 5566", role: "Worker", state: "active", stateLabel: "Active", orders: 265, flags: 1, joined: "Dec 2025" },
      { id: "u5", name: "Felix Danladi", init: "FD", tone: "a5", phone: "+234 810 555 6677", role: "Client", state: "banned", stateLabel: "Banned", orders: 2, flags: 6, joined: "Apr 2026" },
      { id: "u6", name: "Ifeanyi Okoro", init: "IO", tone: "a1", phone: "+234 812 666 7788", role: "Worker", state: "active", stateLabel: "Active", orders: 91, flags: 0, joined: "Jan 2026" }
    ],
    orders: [
      { id: "OR-1042", client: "Adaeze Boutique", worker: "Chinedu Okafor", title: "Landing page — Naija Foods", amount: 85000, state: "delivered", stateLabel: "Delivered", date: "Sep 14, 2026" },
      { id: "OR-1036", client: "Adaeze Boutique", worker: "Ngozi Eze", title: "Logo design — Adaeze Boutique", amount: 45000, state: "in_progress", stateLabel: "In progress", date: "Sep 11, 2026" },
      { id: "OR-1031", client: "Naija Foods Ltd", worker: "Chinedu Okafor", title: "WordPress site — Naija Foods", amount: 85000, state: "settled", stateLabel: "Settled", date: "Sep 15, 2026" },
      { id: "OR-1028", client: "Adaeze Boutique", worker: "Ifeanyi Okoro", title: "Bathroom tiling — Lekki", amount: 300000, state: "completed", stateLabel: "Completed", date: "Sep 2, 2026" },
      { id: "OR-1019", client: "M. Okonkwo", worker: "Tunde Adeyemi", title: "Wiring — 4-bed bungalow, GRA", amount: 450000, state: "disputed", stateLabel: "In dispute", date: "Aug 28, 2026" },
      { id: "OR-1011", client: "Zara Wears", worker: "Chinedu Okafor", title: "Shopify fixes — Zara Wears", amount: 50000, state: "settled", stateLabel: "Settled", date: "Sep 1, 2026" },
      { id: "OR-1007", client: "Zara Wears", worker: "Kemi Adeleke", title: "Bookkeeping — March close", amount: 60000, state: "settled", stateLabel: "Settled", date: "Aug 12, 2026" },
      { id: "OR-0999", client: "T. Balogun", worker: "Femi Balogun", title: "AC service — Ikoyi", amount: 35000, state: "cancelled", stateLabel: "Cancelled", date: "Aug 8, 2026" }
    ],
    payments: [
      { id: "PAY-5521", order: "OR-1042", client: "Adaeze Boutique", method: "Bank transfer", amount: 85000, state: "paid", stateLabel: "Paid", date: "Sep 14, 2026" },
      { id: "PAY-5518", order: "OR-1036", client: "Adaeze Boutique", method: "Card", amount: 45000, state: "paid", stateLabel: "Paid", date: "Sep 11, 2026" },
      { id: "PAY-5509", order: "OR-1031", client: "Naija Foods Ltd", method: "Bank transfer", amount: 85000, state: "paid", stateLabel: "Paid", date: "Sep 15, 2026" },
      { id: "PAY-5497", order: "OR-1028", client: "Adaeze Boutique", method: "USSD", amount: 300000, state: "paid", stateLabel: "Paid", date: "Sep 2, 2026" },
      { id: "PAY-5480", order: "OR-1019", client: "M. Okonkwo", method: "Bank transfer", amount: 450000, state: "paid", stateLabel: "Paid · frozen", date: "Aug 28, 2026" },
      { id: "PAY-5471", order: "OR-1007", client: "Zara Wears", method: "Card", amount: 60000, state: "refunded", stateLabel: "Refunded", date: "Aug 12, 2026" },
      { id: "PAY-5468", order: "OR-1002", client: "T. Balogun", method: "Card", amount: 35000, state: "failed", stateLabel: "Failed", date: "Aug 8, 2026" }
    ],
    withdrawals: [
      { id: "WD-2217", worker: "Chinedu Okafor", tone: "a1", amount: 350000, bank: "GTBank ····0234", date: "Sep 16, 2026", state: "review", stateLabel: "Needs review · above threshold" },
      { id: "WD-2216", worker: "Ifeanyi Okoro", tone: "a1", amount: 120000, bank: "Zenith ····4410", date: "Sep 16, 2026", state: "review", stateLabel: "Needs review · flagged account" },
      { id: "WD-2214", worker: "Chinedu Okafor", tone: "a1", amount: 150000, bank: "GTBank ····0234", date: "Sep 12, 2026", state: "paid", stateLabel: "Paid" },
      { id: "WD-2209", worker: "Ngozi Eze", tone: "a4", amount: 96000, bank: "Access ····8871", date: "Sep 10, 2026", state: "paid", stateLabel: "Paid" },
      { id: "WD-2156", worker: "Tunde Adeyemi", tone: "a5", amount: 90000, bank: "Access ····8871", date: "Aug 26, 2026", state: "failed", stateLabel: "Failed · wrong account" }
    ],
    disputes: [
      { id: "DSP-2031", order: "OR-1019", title: "Wiring — 4-bed bungalow, GRA", amount: 450000, opened: "Sep 13, 2026", by: "Worker", reason: "Client unresponsive", sla: "2 days left", slaClass: "warn", state: "Under review" },
      { id: "DSP-2033", order: "OR-1024", title: "Landing page — Naija Foods", amount: 85000, opened: "Sep 15, 2026", by: "Client", reason: "Work not as described", sla: "5 days left", slaClass: "", state: "Opened" },
      { id: "DSP-2028", order: "OR-0999", title: "AC service — Ikoyi", amount: 35000, opened: "Sep 9, 2026", by: "Client", reason: "Cancelled after payment", sla: "1 day overdue", slaClass: "over", state: "Under review" }
    ],
    reports: [
      { id: "RPT-881", target: "Profile — Peter Anya (u3)", reason: "Scam / fraud", reports: 4, last: "Sep 16, 2026", state: "Open" },
      { id: "RPT-879", target: "Review on OR-0987", reason: "Offensive content", reports: 1, last: "Sep 15, 2026", state: "Open" },
      { id: "RPT-874", target: "Messages — Felix Danladi (u5)", reason: "Off-platform payment solicitation", reports: 6, last: "Sep 14, 2026", state: "Open" },
      { id: "RPT-869", target: "Profile — “QuickFix PH”", reason: "Fake identity", reports: 2, last: "Sep 12, 2026", state: "In triage" },
      { id: "RPT-861", target: "Job #j04 (posting)", reason: "Spam", reports: 1, last: "Sep 10, 2026", state: "Open" }
    ],
    verificationQueue: [
      { worker: "Tunde Adeyemi", tone: "a5", docs: "NIN + selfie", fee: "Paid ₦5,000", submitted: "Sep 14, 2026", sla: "1 day left" },
      { worker: "Yusuf Garba", tone: "a5", docs: "Driver's licence + selfie", fee: "Paid ₦5,000", submitted: "Sep 15, 2026", sla: "1 day left" },
      { worker: "Halima Sani", tone: "a2", docs: "Passport + selfie", fee: "Paid ₦5,000", submitted: "Sep 10, 2026", sla: "Approved · badge live" }
    ],
    audit: [
      { time: "Sep 16, 10:42", admin: "A. Okafor (super)", action: "Withdrawal WD-2214 → approved", target: "WD-2214", reason: "Within threshold, account clean", ip: "105.112.4.88" },
      { time: "Sep 16, 09:15", admin: "B. Eze (support)", action: "Dispute DSP-2033 → assigned", target: "DSP-2033", reason: "New dispute", ip: "105.112.4.91" },
      { time: "Sep 15, 16:30", admin: "A. Okafor (super)", action: "User u3 → suspended", target: "Peter Anya", reason: "4 fraud reports, 3 failed payments", ip: "105.112.4.88" },
      { time: "Sep 15, 14:02", admin: "F. Bello (finance)", action: "Refund PAY-5471 → issued", target: "OR-1007", reason: "Dispute outcome: full refund", ip: "105.112.4.90" },
      { time: "Sep 14, 11:21", admin: "B. Eze (support)", action: "Report RPT-869 → triage", target: "QuickFix PH", reason: "Awaiting docs check", ip: "105.112.4.91" },
      { time: "Sep 14, 08:57", admin: "A. Okafor (super)", action: "Verification w12 → approved", target: "Halima Sani", reason: "ID + selfie matched", ip: "105.112.4.88" },
      { time: "Sep 12, 15:44", admin: "F. Bello (finance)", action: "Withdrawal WD-2214 → paid", target: "WD-2214", reason: "Transfer confirmed", ip: "105.112.4.90" }
    ],
    gmv: [
      { day: "Mon", v: 420 }, { day: "Tue", v: 510 }, { day: "Wed", v: 380 }, { day: "Thu", v: 640 },
      { day: "Fri", v: 720 }, { day: "Sat", v: 560 }, { day: "Sun", v: 300 }
    ]
  },

  categories: [
    { name: "Digital & Tech", subs: ["Web Development", "App Development", "UI/UX Design", "Software & Scripts", "Data & Analytics", "IT Support & Setup"], icon: "code", workers: 412, mode: "Remote" },
    { name: "Creative & Media", subs: ["Graphic Design & Branding", "Video & Motion", "Photography", "Writing & Content", "Social Media Management"], icon: "pen", workers: 356, mode: "Remote" },
    { name: "Trades & Home Services", subs: ["Plumbing", "Electrical", "Tiling & Masonry", "Painting", "Carpentry & Furniture", "Welding & Fabrication", "AC Installation & Repair", "Generator & Power", "Appliance Repair", "Locksmith", "Roofing & Gutters", "Cleaning & Maintenance"], icon: "wrench", workers: 289, mode: "On-site" },
    { name: "Business & Professional", subs: ["Accounting & Bookkeeping", "Virtual Assistance", "Admin Support", "Consulting", "Marketing & Ads", "Event Support"], icon: "briefcase", workers: 204, mode: "Remote" },
    { name: "Education & Personal", subs: ["Tutoring & Exam Prep", "Fashion & Tailoring", "Hair & Beauty", "Fitness & Wellness"], icon: "heart", workers: 148, mode: "Both" }
  ]
};
