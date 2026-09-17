# Skilvi — Product Requirements Document

| | |
|---|---|
| **Product** | Skilvi — Nigeria-first skills & services marketplace |
| **Mission** | Discover. Learn. Earn. |
| **Document version** | 0.9 (Draft for review) |
| **Date** | 17 September 2026 |
| **Status** | Draft — pending founder sign-off |
| **Primary market** | Nigeria (launch) → Africa (expansion) → International (long term) |
| **Technical stack** | PHP backend (existing) · HTML + Tailwind CSS + Vanilla JS frontend |

> **How to read this document.** Sections 1–4 are strategy. Sections 5–8 are product. Section 9 is the
> detailed functional spec (each requirement has an ID and a priority: **P0 = launch-critical,
> P1 = important, ship soon after, P2 = later phase**). Sections 10–16 cover design, architecture,
> flows, metrics, risks, roadmap. Sections 17–18 list assumptions and open questions —
> **read those last and answer them, because several P0 decisions depend on them.**

---

## 1. Executive summary

Skilvi is a marketplace where people with useful skills — digital (web development, design,
writing) or hands-on (plumbing, electrical, tiling, painting) — can present what they can do,
build trust, find clients, and get paid securely. It is built **for Nigeria first**: local payment
methods (Naira, bank transfer, card, USSD), a local identity (phone-number-first accounts), and a
local feel that makes both sides comfortable.

The two sides of the platform:

- **Clients** (individuals and businesses) find workers through search/browse or by posting a job.
  They hire, pay **Skilvi** (escrow), and only release money when the work is done properly.
- **Workers** (skilled individuals) create a free profile, list services and packages, get verified
  (paid, one-time trust badge), optionally promote their profile (paid visibility), deliver work,
  and withdraw Naira to their bank account.

The core trust mechanism is **escrow**: the client's money sits with Skilvi, not with the worker.
It is released when the work reaches the required completion stage. This protects clients from
disappearing workers and protects workers from non-paying clients.

The immediate product goal is to **rebuild the Skilvi frontend** (HTML, Tailwind, Vanilla JS on top
of the existing PHP backend) so the experience looks and feels significantly better — polished but
human-designed, calm, practical, modern, and trustworthy — while preserving everything the product
is actually supposed to do.

**Business model:** platform commission on completed orders + one-time paid verification + paid
profile promotion. Worker accounts are free.

---

## 2. Problem statement

### 2.1 What Nigerian workers face today

1. **Global platforms are hostile to locals.** Upwork/Fiverr-style platforms pay in USD, delay
   payouts, charge withdrawal fees, freeze accounts arbitrarily, and compete Nigerian workers
   against the world on price. A good Port Harcourt developer or a good Lagos electrician is
   squeezed by competition they can't win on price.
2. **Local work is informal.** Trades and local services run on WhatsApp, Instagram, referrals, and
   word of mouth. There is no profile, no history, no reviews, no payment safety — for anyone.
3. **Trust is the bottleneck.** A client who has never met a worker cannot know if the worker is
   real, reliable, or skilled. That uncertainty kills deals, especially when a client (domestic or
   future international) doesn't know the Nigerian market at all.

### 2.2 What Nigerian clients face today

1. Finding a reliable plumber, tiler, designer, or developer means asking around — slow,
   geography-bound, and blind.
2. No payment protection: pay upfront and hope, or get scammed. No recourse.
3. No reputation history: no ratings, no verifiable identity, no way to compare.

### 2.3 The insight

> A useful skill should give a person a way to find customers and earn from it — with the safety
> rails that informal local work lacks. And clients should be able to hire locally with the comfort
> of escrow, reviews, and verified identities.

---

## 3. Mission, vision, values

- **Mission:** Discover. Learn. Earn. — help young adults, job seekers, freelancers, and skilled
  workers discover opportunities, develop useful skills, and turn those skills into sustainable
  income.
- **Vision:** A marketplace where a person doesn't need a university degree or a conventional job to
  make a living. If they have something useful they can do, Skilvi gives them a place to present it,
  build trust, find people who need it, get paid securely, and build a reputation.
- **North-star for product decisions:** **Trust per completed order.** Every feature either builds
  trust (verification, escrow, reviews, disputes) or removes friction between a client and a worker
  (discovery, local payments, simple flows).

**Design values (product, not just visual):**
1. Safety over speed — never release money on unconfirmed completion.
2. Simple over clever — a first-time user in any Nigerian city should understand how to hire.
3. Local over global — Naira, Nigerian banks, Nigerian states, plain language.
4. Free to join — workers must never pay to exist on the platform. Paid features (verification,
   promotion) are optional accelerators, never gates.

---

## 4. Goals and non-goals

### 4.1 Goals (this product version)

- G1: A client can find a worker (search/browse or job posting), hire them, pay via local payment
  methods, and the money is protected until the work is done.
- G2: A worker can create a free profile, list services/packages, receive orders, deliver, get paid
  into a wallet, and withdraw to a Nigerian bank account.
- G3: Reputation is visible and cumulative: ratings, reviews, verification badge, order history.
- G4: Trust is purchasable in two *separate* ways: verification (trust signal) and promotion
  (visibility). They must never be conflated in the UI.
- G5: Administrators can operate the platform safely: verify workers, approve withdrawals, mediate
  disputes, moderate users, with a full audit trail.
- G6: The frontend is rebuilt on HTML + Tailwind + Vanilla JS with a deliberate, human-designed
  visual language (royal blue, calm corners, no AI-template tells).

### 4.2 Non-goals (explicitly out of scope for this version)

- NG1: No native mobile apps (mobile-first responsive web only).
- NG2: No international payments/multi-currency in v1 (Naira only).
- NG3: No "Learn" content engine (courses, tuition) in v1 — see Open Question OQ-3. The mission
  word stays in brand voice; the feature is a later phase.
- NG4: No skill certification. Verification is identity/account verification only. Skilvi does not
  guarantee skill level and must say so in copy.
- NG5: No group buying, subscriptions, or team/agency accounts in v1 (single human workers only).
- NG6: No live video calling or chatbots.

---

## 5. Users & personas

### P1 — The digital freelancer ("Chidi", 24, Port Harcourt)
Frontend developer, has worked Upwork/Fiverr. Frustrated by USD payouts, payout delays, account
freezes, and competing with everyone on price. Wants local clients who pay Naira to his bank,
fast. **Needs:** a polished profile, portfolio, escrow so clients don't dodge payment, reviews that
accumulate locally. **Willing to pay:** verification (it differentiates him), promotion when
launching a new service.

### P2 — The trade professional ("Mama Ngozi" / "Emeka", 38, Lagos)
Plumber/electrician/tiler with 10+ years of experience, known only through referrals. Not a native
web user. **Needs:** a profile he can set up in under 10 minutes (ideally with help), a clear
"how do I get paid" story (money goes to his bank), and a way to show he is real (verification
badge is very powerful for trades). **Willing to pay:** verification; promotion less likely.

### P3 — The small business client ("Aisha", 29, Abuja)
Runs a boutique. Needs a logo, a simple website, and occasionally repairs/maintenance. Searches on
her phone. **Needs:** fast search, visible ratings, verified badges, a budget she can trust
(escrow), payment by bank transfer (she doesn't keep card limits high), and a human dispute channel
if something goes wrong.

### P4 — The one-off individual client ("Tunde", 35)
Needs one specific job (tiling a bathroom, a flyer, a data-entry job). Low tolerance for
complexity. **Needs:** a plain-language flow, clear total price (including what the worker says
about travel), and to know exactly when his money is released.

### P5 — (Future) International client
Wants to hire Nigerian talent but has no way to verify identity or protect payment. This persona
justifies making **trust features first-class from day one** — they are the expansion moat.
Out of scope for v1 flows, but the architecture must not fight it.

---

## 6. Market context (Nigeria)

Design and copy decisions that follow from the market:

| Context | Product consequence |
|---|---|
| Mobile-first internet, mixed network quality | Responsive, fast, low-weight pages; images compressed/lazy-loaded; core flows work on 4G/mid-range Android |
| Phone number is the primary identity (many users treat email as secondary) | Phone + OTP is the primary auth method; email is optional-but-recommended |
| Naira, bank transfer (NIP/instant transfer), cards, USSD are how people actually pay | All payments in ₦; support card + bank transfer + USSD via payment provider; Naira formatting everywhere (`₦250,000`) |
| States/LGAs are how people think about location | Location pickers use Nigerian states → city; on-site services declare a service area |
| Informal trust culture (referrals, "your neighbour") | Verification badge + reviews + visible order history are the digital equivalent of a referral |
| Scams are a top-of-mind fear on both sides | Escrow, verification, disputes, reports, and plain-language safety explanations throughout |
| NDPR (Nigeria Data Protection Regulation) applies | Data handling, consent, deletion rights, and audit logging requirements (see NFR) |

---

## 7. Product overview & core loop

The loop:

```
A person has a useful skill
   → joins Skilvi for free
   → creates profile + services (and packages)
   → (optional) pays for verification badge
   → (optional) pays to promote profile/services
   → clients discover them (search/browse) OR clients post jobs
   → worker is hired (direct purchase OR accepted proposal)
   → client pays Skilvi (escrow)
   → worker performs the work
   → work delivered → client approves (or requests revisions / opens dispute)
   → order completed
   → Skilvi releases payment to worker wallet (minus commission)
   → client leaves a review
   → worker's reputation grows → more clients
```

### 7.1 Two hiring paths (both are first-class)

| Path | How it works | Best for |
|---|---|---|
| **A. Direct hire** | Worker publishes services with fixed-price packages. Client clicks "Hire" on a package, fills a short brief, pays. | Buyers who know exactly what they want (a logo, a 1-page site, bathroom tiling). |
| **B. Job posting** | Client posts a job (description + budget). Workers submit proposals (price, timeline, message, portfolio link). Client reviews proposals and accepts one → order created at the accepted price. | Buyers with a bespoke problem; workers who want to pitch. |

### 7.2 Work modes

- **Remote** — delivered online (web dev, design, writing, data).
- **On-site** — worker travels (plumbing, tiling, painting, electrical, installation).
- **Hybrid** — e.g., design a floor plan remotely, supervise on-site.

On-site services require a declared **service area** (states/cities) and may include travel costs —
workers state in the package whether travel is included (see SVC-05).

---

## 8. Scope

### 8.1 In scope — launch (P0)

Auth & accounts · Worker profiles & portfolios · Service catalog with packages · Categories &
search/browse with core filters · Direct hire with escrow · Job posting with proposals · Messaging
(per order) · Orders & escrow lifecycle · Payments (card, bank transfer, USSD) · Worker wallet &
bank withdrawals · Disputes (in-app, mediated by admin) · Reviews & ratings · Reports & blocks ·
Admin console (users, orders, payments, withdrawals, disputes, reports, audit log) · Notifications
(in-app + email) · Safety & compliance baseline.

### 8.2 In scope — phase 2 (P1, shortly after launch)

Paid verification (full flow + admin queue) · Paid promotion (search/category spotlights) · SMS
notifications · Client-side account verification (lightweight) · Mutual reviews (workers rate
clients) · Advanced analytics · Portfolio media management upgrades.

> **Note:** Verification and promotion are the revenue features and are **P1, not P0** — the
> platform must first prove the trust loop (escrow + reviews) works before monetizing trust. If
> the business needs them at launch, flip to P0 — but the flows below are fully specified so it is
> a scheduling decision, not a design one. *(Adjust per OQ-2.)*

### 8.3 Out of scope (P2 / later phases)

"Learn" content (courses, guides, skill-up programs) · International clients & multi-currency ·
Native apps · Agency/team accounts · Live location tracking · Auto-release policy tuning tools ·
Marketplace expansion to other countries.

---

## 9. Functional requirements

Priorities: **P0** launch-critical · **P1** phase 2 · **P2** later.

### 9.1 Authentication & accounts (AUTH)

**Purpose.** Safe, phone-first accounts with distinct roles.

| ID | Requirement | Priority |
|---|---|---|
| AUTH-01 | Register with **phone number + OTP** (SMS) as the primary method. Email is optional at signup, recommended, and collectible later. | P0 |
| AUTH-02 | Choose (or are detected into) a role: **Worker**, **Client**, or **Both**. Users may hold both roles; dashboards are separate per role. | P0 |
| AUTH-03 | Password login (email/phone) supported for users who set one; OTP re-verification on new devices (configurable). | P0 |
| AUTH-04 | Account state machine: `active`, `suspended` (by admin, with reason shown), `banned`, `closed`. Suspended users can log in to a read-only "account status" page, not the marketplace. | P0 |
| AUTH-05 | Two-factor authentication (TOTP) required for all admin accounts. | P0 |
| AUTH-06 | Session security: server-side sessions with secure flags; CSRF tokens on all mutating requests; rate limiting on login/OTP endpoints. | P0 |
| AUTH-07 | Account settings: update phone/email (with re-verification), password, notification preferences, deactivate/close account (NDPR deletion request path). | P0 |
| AUTH-08 | Phone numbers normalized to `+234…`; duplicate-phone detection blocks duplicate accounts. | P0 |

**Edge cases:** failed/expired OTP (max 3 attempts, then cooldown); suspended user tries to pay
(order blocked with clear message); worker with active escrow tries to close account (blocked until
orders settle).

---

### 9.2 Worker profile (WBK)

**Purpose.** A worker's public, trust-building identity.

| ID | Requirement | Priority |
|---|---|---|
| WBK-01 | Profile fields: full name, headline (one line), bio, avatar photo, state + city, work mode (remote / on-site / hybrid), service area (for on-site), skills (multi-select from taxonomy), availability. | P0 |
| WBK-02 | Profile completeness meter (e.g., "80% complete — add your bio") that nudges workers toward the minimum viable profile. | P0 |
| WBK-03 | Portfolio: up to N items (suggested 10), each with title, description, image (or link). Images compressed server-side. | P0 |
| WBK-04 | Public profile page shows: avatar, headline, bio, verified badge (if held), rating summary (average + count), skills, work mode + area, portfolio, live services with packages, order-completion count, member-since year. | P0 |
| WBK-05 | **Response-time and completion stats** computed from order history ("Replies within ~4 hours", "97% of 41 orders completed"). | P0 |
| WBK-06 | Profile states: `draft` (not public), `active` (public, searchable), `hidden` (worker can hide while inactive). | P0 |
| WBK-07 | Workers cannot post contact details in free-text fields that target off-platform diversion (basic keyword/phone-number detection on profile text → flag for admin review). See OQ-4 for final policy. | P1 |
| WBK-08 | Onboarding assistant: a 3-step guided setup (basics → skills & services → portfolio) shown on first login for new workers, each step skippable but tracked. | P1 |

---

### 9.3 Services & packages (SVC)

**Purpose.** What a worker sells, in concrete, purchasable form.

| ID | Requirement | Priority |
|---|---|---|
| SVC-01 | A **service** groups a worker's offering: title, description, category (from taxonomy), work mode, delivery time range, and 1–5 **packages**. | P0 |
| SVC-02 | A **package** is a fixed-price tier: name (e.g., Starter / Standard / Premium), price in ₦, delivery time (days), number of revision rounds included, and a list of included items (checklist). | P0 |
| SVC-03 | Each service has a **custom option**: client can request a custom quote; worker replies with a price + timeline; accepting converts it into an order at that price. | P0 |
| SVC-04 | Service states: `active` (searchable), `paused` (worker toggles), `archived` (admin). | P0 |
| SVC-05 | For on-site packages the worker must mark **travel cost**: "included in price" or "travel quoted separately in chat" — shown on the package card. | P0 |
| SVC-06 | **Service brief**: worker defines 1–6 short question fields (e.g., "Website type?", "Number of pages?") that the client fills before purchase. | P0 |
| SVC-07 | Price and package editing: changes apply to new orders only; existing orders are immutable. | P0 |
| SVC-08 | Category taxonomy is admin-managed (see ADM-12). Initial taxonomy in §9.17. | P0 |

**Pricing guardrails (assumption A-5):** packages enforce a platform minimum (e.g., ₦500) and
sane maximum (e.g., ₦20,000,000) to catch input errors; out-of-range prices are rejected with a
message, not silently clamped.

---

### 9.4 Categories & marketplace discovery (DISC)

**Purpose.** Clients find the right worker quickly.

| ID | Requirement | Priority |
|---|---|---|
| DISC-01 | **Home/browse page**: featured category tiles (top-level), recently reviewed workers, (phase 2) promotion spotlight slots. | P0 |
| DISC-02 | **Search**: single search box over keywords (skill, service title, worker name, category). Search works from the global header on every page. | P0 |
| DISC-03 | **Search results page** shows workers and services with: avatar, name, headline, verified badge, rating (avg + count), location, starting price, work mode, "Promoted" label where applicable. | P0 |
| DISC-04 | **Browse by category**: two-level tree (e.g., Trades & Home → Plumbing). Category pages list workers/services in that category. | P0 |
| DISC-05 | **Filters** (P0 set): work mode (remote / on-site / hybrid), state (and city within state for on-site), price range, verified only, minimum rating, availability. | P0 |
| DISC-06 | **Sort** options: Relevance (default), Top rated, Lowest price, Highest price, Newest. | P0 |
| DISC-07 | **Promoted placements** (phase 2): promoted workers/services appear in clearly labelled "Promoted" positions — top of search results and category pages. Cap: max 3 promoted items per results page; every promoted item carries the label; non-promoted items are never demoted below page 1 by promotions. | P1 |
| DISC-08 | Empty states: every zero-result page explains what happened and offers the two next best actions (relax filters, or post a job instead). | P0 |
| DISC-09 | Pagination (or infinite scroll — pick one, keep consistent; recommended: numbered pagination for server-rendered simplicity). | P0 |
| DISC-10 | Search is case-insensitive and tolerant of common Nigerian phrasing (e.g., "web designer" finds "web design"; "wiring" finds "electrical work" via category synonyms). | P1 |

---

### 9.5 Direct hire (DH)

**Purpose.** The shortest path from "I want this" to "I'm paying".

| ID | Requirement | Priority |
|---|---|---|
| DH-01 | Service detail page: description, package comparison cards, revision policy, delivery time, travel note (if on-site), worker summary card (avatar, rating, verified badge, response time, "message" CTA). | P0 |
| DH-02 | **Hire flow**: pick package (or custom) → fill service brief → order summary screen (itemized: package price, Skilvi protection note) → pay. | P0 |
| DH-03 | The summary screen **must** explain escrow in one plain sentence: *"You pay Skilvi, not the worker directly. Your money is held safely and released only when the work is done."* | P0 |
| DH-04 | Custom quote: client submits request + budget → worker gets notified with "Accept / Counter / Decline" buttons in their dashboard. | P0 |
| DH-05 | Client can cancel a paid order **before the worker starts** → automatic full refund (see PAY-07). | P0 |
| DH-06 | A worker cannot hire themselves / their own alternate account (same phone, same device heuristics — flag, don't auto-block, for admin review). | P0 |

---

### 9.6 Job posting & proposals (JOB)

**Purpose.** The "describe your problem, get pitched" path.

| ID | Requirement | Priority |
|---|---|---|
| JOB-01 | **Post a job**: title, category, description, budget (fixed ₦ amount or "negotiable"), work mode, location (state/city for on-site), deadline (optional), number of proposals desired (optional). | P0 |
| JOB-02 | Job states: `open` → `in review` (client reviewing proposals) → `awarded` (order created) → `closed`; plus `cancelled` (by client, before award) and `expired` (auto-close after 30 days, admin-configurable). | P0 |
| JOB-03 | **Proposal**: worker submits price (₦), timeline (days), message (required, min length), portfolio link (optional). One proposal per job per worker. | P0 |
| JOB-04 | Client views proposals in a list: worker name, verified badge, rating, price, timeline, message preview, portfolio link, "View profile", "Shortlist" action. | P0 |
| JOB-05 | **Awarding**: client picks a proposal → confirmation screen (price, escrow explanation) → client pays → order created → all other proposals auto-closed with a polite notification. | P0 |
| JOB-06 | Job feed: "New jobs in your categories" on the worker dashboard, filterable by work mode/location. | P0 |
| JOB-07 | Clients can cancel open jobs at any time before award (no money involved). | P0 |
| JOB-08 | Proposal notifications: new proposal → client; proposal accepted/rejected → worker. | P0 |

---

### 9.7 Messaging (MSG)

**Purpose.** Keep the working relationship on-platform so disputes have evidence.

| ID | Requirement | Priority |
|---|---|---|
| MSG-01 | Messaging exists **per order** (thread created automatically when an order is created). A general "pre-hire" message thread is available from any worker profile (for clients) — with a gentle, non-blocking notice encouraging the client to move logistics into the order. | P0 |
| MSG-02 | Text messages with timestamps, read receipts, and "last active" indicator. | P0 |
| MSG-03 | Attachments: images and documents up to 10 MB each (PDF, JPG/PNG, DOCX). | P0 |
| MSG-04 | Real-time delivery via lightweight polling (every 15–30 s) or Server-Sent Events if the PHP backend supports streaming — **no WebSocket infrastructure assumed** in v1. | P0 |
| MSG-05 | Message content is exported/attached as evidence in disputes (admin view). | P0 |
| MSG-06 | Basic safety: keyword detection for phone numbers/external links → inline soft warning ("Keep payment on Skilvi — money paid off-platform isn't protected") rather than hard block. Final policy per OQ-4. | P1 |
| MSG-07 | Unread counts in header and dashboard; notification on new message (in-app + email/SMS per user preference). | P0 |
| MSG-08 | Blocked users cannot message each other (enforced server-side). | P0 |

---

### 9.8 Orders & escrow lifecycle (ORD)

**Purpose.** The backbone of trust. Every order follows one state machine.

**Order states:**

```
DRAFT (order created, unpaid)
   → PAID (escrow holds the full amount)
      → IN_PROGRESS (worker starts)
         → DELIVERED (worker submits work for approval)
            → REVISION (client requests changes, within package rounds) → IN_PROGRESS
            → COMPLETED (client approves, or auto-release timer expires)
               → SETTLED (funds moved to worker wallet minus commission)
   CANCELLED (before work starts → refund)
   DISPUTED (opened by either side while PAID/IN_PROGRESS/DELIVERED/REVISION → funds frozen)
```

| ID | Requirement | Priority |
|---|---|---|
| ORD-01 | Every state transition is recorded in an **order event log** (who, what, when, note) — visible to both parties as a simple timeline on the order page. | P0 |
| ORD-02 | **Starting work**: worker marks the order "In progress" (for custom jobs) or it auto-starts on payment (for package orders where work begins immediately — configurable per order type). | P0 |
| ORD-03 | **Deliverable submission**: worker submits the deliverable(s) with a summary note (files via attachments). Order moves to DELIVERED. | P0 |
| ORD-04 | **Client approval**: in DELIVERED, the client sees "Approve & release payment" (primary) and "Request revision" (secondary, requires a note). | P0 |
| ORD-05 | **Revisions**: limited to the package's included rounds. Each request moves the order back to IN_PROGRESS and increments a visible counter ("Revision 1 of 2"). When rounds are exhausted, the client's options are: approve, open a dispute, or (P2) purchase an additional revision round at the worker's listed rate. | P0 |
| ORD-06 | **Auto-release**: if the client does not respond within **5 business days** (configurable) after a DELIVERED submission, funds release automatically and the client is notified beforehand ("Your payment will release on [date] unless you respond"). | P0 |
| ORD-07 | **Cancellation**: pre-start → instant full refund. Post-start → cancellation is not self-serve; either party may request it via dispute. | P0 |
| ORD-08 | Order list views: client dashboard ("My orders") and worker dashboard ("My work"), filterable by state; each row shows worker/client name, service, amount, state chip, and next action. | P0 |
| ORD-09 | Order detail page: event timeline, deliverables, revisions, messages shortcut, payment status, and (admin) escrow controls. | P0 |
| ORD-10 | Amounts on an order are **immutable** after payment (price changes require a new order). | P0 |

---

### 9.9 Payments (PAY)

**Purpose.** Money in, held, and moved — with a local feel and no gaps.

| ID | Requirement | Priority |
|---|---|---|
| PAY-01 | **Payment methods (Naira):** card (debit/credit), bank transfer (instant/NIP transfer), USSD. Delivered via payment provider (Paystack or Flutterwave — see OQ-1). | P0 |
| PAY-02 | The client **always pays Skilvi** (merchant of record for the order). Direct client→worker payments are not a supported flow. | P0 |
| PAY-03 | Payment page: order summary, amount in ₦, method tabs (Card / Bank transfer / USSD), plain-language escrow reassurance block, "I understand my money is held by Skilvi until the work is done" is implicit in copy, not a checkbox. | P0 |
| PAY-04 | **Payment verification is webhook-driven, not redirect-driven**: the order only moves to PAID on a verified provider webhook. Page redirects are UX convenience only. | P0 |
| PAY-05 | Failed/declined payments: order stays DRAFT, client sees the reason and can retry. No ghost orders older than 24 h in DRAFT (auto-close + notify). | P0 |
| PAY-06 | **Refunds**: (a) pre-start cancellation → full refund to original method; (b) dispute outcome → full or partial refund per admin decision. Refunds are tracked (id, amount, reason, status, date) and visible to the user. | P0 |
| PAY-07 | **Commission**: Skilvi's fee (proposed 10% — see A-6) is deducted when funds settle to the worker; the client pays 100% of the agreed price (worker-absorbed fee model, simplest and most common in marketplaces). Fee percentage is a config value, not hardcoded. | P0 |
| PAY-08 | **Payment ledger**: every money movement (order payment, hold, release, commission, refund, withdrawal) is an immutable ledger entry with type, amount, related order, and timestamp. | P0 |
| PAY-09 | No card data is stored by Skilvi; PCI compliance is delegated to the payment provider (tokenization/redirect flows). | P0 |
| PAY-10 | Reversals/chargebacks: webhook-triggered flag on the order → admin queue; if chargeback wins on a settled order, Skilvi debits the worker wallet (with notification and dispute right) or pursues recovery. | P1 |
| PAY-11 | Currency display: `₦` with thousands separators, no decimals for whole Naira (`₦125,000`), consistent in every UI surface. | P0 |

---

### 9.10 Wallet, earnings & withdrawals (WAL)

**Purpose.** Workers see their money clearly and get it into their bank.

| ID | Requirement | Priority |
|---|---|---|
| WAL-01 | **Worker wallet** shows: Available balance, Pending (in escrow on active orders), Lifetime earnings, and a ledger list (each entry: date, type — settlement / commission / refund-debit / withdrawal, related order, amount, running balance). | P0 |
| WAL-02 | **Withdrawals** to a Nigerian bank account (bank name + account number, or virtual-account flow if the provider offers it). Minimum withdrawal amount (proposed ₦5,000 — A-7), max 3 pending withdrawals at once. | P0 |
| WAL-03 | Withdrawal lifecycle: `requested` → (auto-approve below threshold, e.g., ₦100,000) or `review` (above threshold, or fraud-flagged) → `approved` → `paid` / `failed`. | P0 |
| WAL-04 | New bank accounts added by a worker require confirmation (e.g., test-transfer verification or provider-supported check) before first payout. | P1 |
| WAL-05 | Failed payouts return funds to the wallet with a clear reason and retry option; 3 consecutive failures lock withdrawals pending admin review. | P0 |
| WAL-06 | Earnings summary for workers: this month, total orders, average order value (simple charts, no analytics overkill). | P1 |

---

### 9.11 Disputes (DSP)

**Purpose.** When something goes wrong, there is a process — not just two people arguing in chat.

| ID | Requirement | Priority |
|---|---|---|
| DSP-01 | Either party can open a dispute on an order in states PAID / IN_PROGRESS / DELIVERED / REVISION. Opening it **freezes the escrow funds** immediately. | P0 |
| DSP-02 | Dispute intake: reason (structured list: work not delivered, work not as described, partial delivery, communication breakdown, client non-responsive, other) + description + evidence (files, screenshots; message history is attached automatically). | P0 |
| DSP-03 | Dispute timeline: `opened` → `under review` (admin assigned) → `resolution proposed` → `closed (worker_favoured / client_favoured / partial / other)`. | P0 |
| DSP-04 | **Outcomes** (admin-decided, both parties notified with reasoning): full release to worker; full refund to client; partial split (e.g., 60/40 — admin specifies percentages); close with no release and refund. | P0 |
| DSP-05 | **SLA**: acknowledge within 1 business day, resolve within 5 business days (target). SLA timers visible in admin; breaching escalates to a supervisor queue. | P0 |
| DSP-06 | While disputed, messaging remains open (with a visible banner on the thread: "This order is under dispute — Skilvi support is reviewing"). | P0 |
| DSP-07 | Dispute history counts toward a user's **trust score** used by admins for risk flags (repeated disputed orders → verification hold, manual payout review, or suspension per policy). | P1 |
| DSP-08 | Both parties can mark a dispute "resolved / I accept" on a proposed resolution (prevents one-sided closure without acknowledgement where possible). | P1 |

---

### 9.12 Reviews & reputation (REV)

**Purpose.** Reputation that compounds, honestly.

| ID | Requirement | Priority |
|---|---|---|
| REV-01 | Reviews are written **only after an order is COMPLETED/SETTLED**, by the client, about the worker. 1–5 stars + text (min 20 chars) + optional tags (e.g., "Delivered on time", "Great communication", "Professional"). | P0 |
| REV-02 | Worker can **reply** once per review (public, editable for 48 h). | P0 |
| REV-03 | Profile shows: average rating, count, distribution bars, latest 5 reviews with date + order category (not full order details). | P0 |
| REV-04 | Ratings are immutable after posting except: admin removal for policy violation, or mutual retraction within 72 h by both parties. | P0 |
| REV-05 | Reviews are reportable (spam, off-topic, offensive) → admin queue. | P0 |
| REV-06 | Anti-gaming: only one review per completed order; reviews from accounts flagged for fraud are excluded from displayed ratings but retained. | P0 |
| REV-07 | Mutual reviews (worker rates client) — for worker-side client reputation. | P2 |
| REV-08 | Review nudge: completion triggers a "Rate your experience" prompt to the client (in-app + email), for 7 days. | P0 |

---

### 9.13 Verification (VER) — *trust, not certification*

**Purpose.** A paid, one-time trust signal that a worker's identity and account are real.

| ID | Requirement | Priority |
|---|---|---|
| VER-01 | **Verification is a one-time paid feature** (proposed ₦5,000 — A-8), separate from promotion. It is purchased after account creation. | P1 |
| VER-02 | **What it checks (proposed):** government ID (NIN, driver's licence, or international passport) + self-photo (liveness-style), phone already OTP-verified, and a short profile accuracy review. | P1 |
| VER-03 | **What it does NOT claim:** the badge never says "certified" or "skilled". All copy reads: "Identity verified" / "Verified by Skilvi" with a tooltip: *"Skilvi verified this person's identity and account. It is not a guarantee of skill level."* | P1 |
| VER-04 | Badge appears next to the worker's name everywhere: profile, search results, order pages, chat header. | P1 |
| VER-05 | Admin queue: application (docs + fee receipt) → `pending` (SLA 2 business days) → `approved` (badge on) / `rejected` (reason + one reapply after fixes). | P1 |
| VER-06 | Verification is revocable (identity fraud found, account taken over, policy violation). Revocation removes the badge and is logged. | P1 |
| VER-07 | Verification expires after **2 years** (admin-configurable) — re-verification required to keep the badge. | P1 |
| VER-08 | Document uploads are stored encrypted, access-restricted to the verification queue, and deleted per retention policy (proposed 12 months after decision). | P1 |

---

### 9.14 Promotion (PROM) — *visibility, not trust*

**Purpose.** Workers can pay to be seen more; the UI must make obvious this is an ad, not a ranking of quality.

| ID | Requirement | Priority |
|---|---|---|
| PROM-01 | Promotion is purchased separately from verification: options (proposed — A-9): **Search boost** (e.g., ₦2,500 / 7 days: worker appears in labelled "Promoted" positions in relevant search results) and **Category spotlight** (e.g., ₦5,000 / 7 days: featured on a category page + home browse tile). | P1 |
| PROM-02 | Every promoted placement carries a visible **"Promoted"** label. No placement may visually imply Skilvi-endorsed quality (no stars, no "top rated" language on promoted slots). | P1 |
| PROM-03 | Promotion applies per service (or worker-level for category spotlight), has a start/end window, and expires automatically; expired promotions stop appearing with no admin action. | P1 |
| PROM-04 | Caps: max 3 promoted items per search results page; a worker's own promotion never occupies more than 1 slot per page (no self-flooding). | P1 |
| PROM-05 | Admin controls: pause any promotion (policy violation), view active promotions, revenue view. | P1 |
| PROM-06 | Promoted workers are **not** reordered by rating or any quality metric — promotion affects placement slots only, and only within the labelled positions. | P1 |

---

### 9.15 Reporting & blocking (RPT)

**Purpose.** Community self-policing feeding the admin moderation queue.

| ID | Requirement | Priority |
|---|---|---|
| RPT-01 | Any user can report any public profile, review, message, or job from any of its pages. Structured reasons: scam/fraud, fake identity, harassment/abuse, spam, off-platform payment solicitation, offensive content, other (+ free text). | P0 |
| RPT-02 | Reports are anonymous to the reported user ("Your report was received" only). A user cannot see that X reported Y. | P0 |
| RPT-03 | **Block**: two-way, instant, effective immediately (no messages, profile link unsearchable to the blocker, active orders unaffected but chat shows a notice). Unblock is self-serve. | P0 |
| RPT-04 | Report thresholds: N reports on one account within 30 days (proposed N=3) auto-raise a **risk flag** visible in the admin user view (no automatic action). | P0 |
| RPT-05 | Repeat confirmed violations → admin action: warning (recorded), suspension, or ban per the moderation policy document. | P0 |

---

### 9.16 Notifications (NTF)

| ID | Requirement | Priority |
|---|---|---|
| NTF-01 | Channels: in-app (bell + unread count) always; **email** for most events; **SMS** only for money-critical events (payment received, withdrawal paid/failed, auto-release countdown) — SMS is P1 (cost), until then email + in-app. | P0 |
| NTF-02 | Event matrix (all P0): order created / paid / started / delivered / revision requested / completed / settled; proposal received / accepted / rejected; dispute opened / resolved; review received; withdrawal requested / paid / failed; account suspended; verification decision; promotion started / ended. | P0 |
| NTF-03 | User preferences: per-event email opt-out (except money-critical, always on). | P0 |
| NTF-04 | Notification settings page lists everything, per channel. | P0 |

---

### 9.17 Initial category taxonomy (admin-managed, launch set)

| Top level | Sub-categories |
|---|---|
| **Digital & Tech** | Web Development, App Development, UI/UX Design, Software & Scripts, Data & Analytics, IT Support & Setup |
| **Creative & Media** | Graphic Design & Branding, Video & Motion, Photography, Writing & Content, Social Media Management |
| **Trades & Home Services** | Plumbing, Electrical, Tiling & Masonry, Painting, Carpentry & Furniture, Welding & Fabrication, AC Installation & Repair, Generator & Power, Appliance Repair, Locksmith, Roofing & Gutters, Cleaning & Maintenance |
| **Business & Professional** | Accounting & Bookkeeping, Virtual Assistance, Admin Support, Consulting, Marketing & Ads, Event Support |
| **Education & Personal** | Tutoring & Exam Prep, Fashion & Tailoring, Hair & Beauty, Fitness & Wellness |

- Two-level tree only (top → sub) for v1. Admin can add/rename/archive sub-categories; archived
  categories keep existing services searchable under a legacy tag.
- Each sub-category has: name, slug, icon, description, work-mode default (remote / on-site / both).

---

### 9.18 Admin console (ADM)

**Purpose.** Skilvi can operate, moderate, and audit the platform without touching code.

**Access:** separate admin role, TOTP 2FA (AUTH-05), role-based (admin / support / finance /
super-admin). Every admin action is audited (ADM-16).

| ID | Requirement | Priority |
|---|---|---|
| ADM-01 | **Dashboard**: today/7-day KPIs — GMV, orders created/completed, disputes open/aging, pending withdrawals, new signups (worker/client split), reports pending, payment failures. | P0 |
| ADM-02 | **User management**: search by name/phone/email; view full profile + order history + ledger; actions: suspend (with reason), unsuspend, ban, close account; impersonate/view-as for support (logged). | P0 |
| ADM-03 | **Order explorer**: filter by state/amount/date/user; full event timeline + messages view; actions: force auto-release (requires reason + second admin approval if > ₦250,000 — A-10). | P0 |
| ADM-04 | **Payments explorer**: all payments, refunds, chargebacks; provider reconciliation view (Skilvi ledger vs provider settlement file). | P0 |
| ADM-05 | **Withdrawal queue**: requested/review/paid/failed; approve/reject with reason; threshold-based auto-approve from WAL-03. | P0 |
| ADM-06 | **Dispute queue**: full DSP tooling — evidence viewer, message transcript, resolution form (outcome + split % + reasoning), SLA timers. | P0 |
| ADM-07 | **Reports queue**: triage, link to user, action (dismiss / warn / suspend / ban), resolution note. | P0 |
| ADM-08 | **Verification queue** (phase 2): VER-05 tooling. | P1 |
| ADM-09 | **Promotion manager** (phase 2): active promotions, pause, revenue. | P1 |
| ADM-10 | **Fraud tools**: risk flags dashboard (repeat dispute openers, repeated payment failures, duplicate accounts, device/phone clusters), manual flag add/remove with reason. | P1 |
| ADM-11 | **Content**: category CRUD (taxonomy from §9.17), homepage tiles management, global config (fees, minimums, auto-release days, SLAs, caps). | P0 |
| ADM-12 | **Analytics**: GMV over time, conversion funnel (visit → profile view → order started → paid → completed), dispute rate, top categories, worker/client retention. (Phase 2 for depth; dashboard KPIs P0.) | P1 |
| ADM-13 | **Audit log (ADM-16)**: immutable list of every sensitive admin action — actor, role, timestamp, IP, action, target, before/after values, reason. Filterable and exportable (CSV). | P0 |
| ADM-14 | **Support inbox**: consolidated user contact channel (contact form/email forwarding) with user identity + order context. | P0 |
| ADM-15 | Admin sessions: shorter expiry, idle timeout, concurrent-session limit. | P0 |

---

## 10. Payment model & economics (proposed — confirm per OQ)

| Item | Proposal | Notes |
|---|---|---|
| Worker account | Free, always | Non-negotiable brand principle |
| Platform commission | 10% of settled order value, deducted on settlement | Client pays face value; worker receives 90%. Configurable per category later. |
| Verification | ₦5,000, one-time, 2-year validity | Paid feature, never a gate to receive work |
| Promotion | Search boost ₦2,500 / 7 days · Category spotlight ₦5,000 / 7 days | Always labelled "Promoted" |
| Withdrawal fee | ₦0 in v1 | Revisit when volume justifies it |
| Refund handling | Full/partial per dispute outcome | Commission: if refunded, Skilvi keeps 0% commission (fee reverses with the order) |

**Assumption A-1:** Escrow funds are held **with the payment provider** (payable wallet/merchant
balance), not in Skilvi's own bank account — this keeps the company outside the "holding customer
funds" regulatory perimeter. **Must be confirmed with legal/payments (OQ-9).**

---

## 11. Non-functional requirements (NFR)

| ID | Requirement |
|---|---|
| NFR-01 | **Mobile-first responsive.** Primary target: 360px-wide phone through desktop. Every P0 flow must be completable on a phone without horizontal scroll. |
| NFR-02 | **Performance.** LCP < 2.5 s on mid-range Android over 4G; server-rendered pages; compressed WebP images; lazy-load below-fold media; cache static assets. No JS framework; total page JS < 100 KB gzipped (vanilla modules). |
| NFR-03 | **Security.** OWASP Top-10 baseline: PDO prepared statements everywhere, output escaping (XSS), CSRF tokens, session hardening, rate limiting (login, OTP, payments, messaging), security headers (CSP, HSTS, X-Frame-Options). File uploads: type/size validation, stored outside web root, random names. Admin behind 2FA. |
| NFR-04 | **Data & privacy (NDPR).** Privacy policy + terms of service pages; consent at signup; data access/deletion request path; retention schedules (KYC docs 12 months post-decision — VER-08; audit logs ≥ 2 years); no selling user data; card data never touches Skilvi servers (PAY-09). |
| NFR-05 | **Availability & durability.** 99.5% target; daily DB backups (7-day retention) + off-site copy; payment webhooks idempotent and replay-safe; graceful degradation when SMS/email providers fail (queue + retry). |
| NFR-06 | **Auditability.** Immutable ledger for money (PAY-08); immutable audit log for admin actions (ADM-16); order event log (ORD-01). Append-only: no UPDATE/DELETE on these tables. |
| NFR-07 | **Observability.** Structured error logs, payment webhook logs, simple uptime/health endpoint; error tracking for frontend JS. |
| NFR-08 | **Accessibility.** Semantic HTML, keyboard-navigable, focus states, AA contrast for text (especially royal blue on white — use the 600+ shade for text, see design section), alt text on all user-visible images. |
| NFR-09 | **Internationalization-ready.** All user-facing strings externalized (PHP language files or JSON) — English at launch, structure ready for Pidgin/French later. Naira formatter is the single source for currency display. |
| NFR-10 | **Low-data consideration.** Sensible image sizes, no auto-playing video, optional "data saver" note on image-heavy portfolio pages. |

---

## 12. Design requirements — visual language

> **The bar:** polished but **human-designed** — like an actual designer deliberately built this.
> Calm corners. No AI-SaaS-template tells. Trustworthy, practical, modern, and unmistakably a
> product a Nigerian team would be proud of.

### 12.1 Design principles

1. **Calm over loud.** Quiet surfaces, clear hierarchy, generous whitespace. The product should
   feel like a bank you trust, not a carnival.
2. **One job per screen.** One primary action per view (the blue button). Secondary actions are
   secondary in every way (outline/ghost/text styles).
3. **Trust is visible.** Verified badges, ratings, "protected by Skilvi escrow" notes, and money
   amounts are never hidden or cluttered.
4. **Local is a feature.** Naira formatting, Nigerian states, plain English with a warm, direct
   voice. No translation-ese, no corporate fluff.
5. **Honest UI.** No dark patterns: no pre-ticked boxes, no burying cancellation, no fake urgency.

### 12.2 Brand & tokens

**Primary brand colour: Royal Blue.** Proposed scale (final hex per OQ-5):

| Token | Hex (proposed) | Use |
|---|---|---|
| `royal-600` | `#2B4ADB` | **Primary** — buttons, links, active states |
| `royal-700` | `#233DB2` | Button hover / pressed |
| `royal-500` | `#3D63F0` | Accents, focus rings, chart highlights |
| `royal-100` | `#DCE6FF` | Selected filter chips, subtle fills |
| `royal-50` | `#EEF3FF` | Section tinting, badge backgrounds |
| `navy-900` | `#0B1B4D` | Footer, dark surfaces, large display text |

**Neutrals (slate):** text `#0F172A`, secondary `#475569`, borders `#E2E8F0`, surfaces white /
`#F8FAFC`.

**Semantic:** success `#15803D` · warning `#B45309` · danger `#B91C1C` · info = royal.

**Shape — the "calm corners" rule:**

| Element | Radius |
|---|---|
| Buttons, inputs, small cards | **8 px** |
| Large cards, modals, banners | **12 px** |
| Tags, badges, chips, avatars | full round (only) |
| Pages/containers | 0 |

→ **Never** 16 px+ on cards, **never** pill-shaped buttons. That is the single most reliable
"not an AI template" signal.

**Elevation:** 1 px border + `shadow-sm` by default; hover → `shadow-md`. No glassmorphism, no
soft-blur shadows, no stacked floating cards.

**Type:**
- Display/headings: **Sora** (600/700) — geometric but warm. *(Alternative: Space Grotesk.)*
- Body/UI: **Inter** (400/500/600).
- Money: always `tabular-nums`, 600 weight when it's an amount the user is paying.
- Scale: 13 / 14 / 16 / 18 / 22 / 28 / 34 (base 16). No 900-weight display type.

**Iconography:** single stroke-weight (1.5 px) line icons, Lucide-style; 20 px UI / 24 px
features; no filled/duotone mix.

### 12.3 Anti-pattern list (explicit "do not")

The following are banned by PRD. If a design review sees them, it fails review:

- ❌ Gradient headings, gradient buttons, gradient "aurora" backgrounds
- ❌ Pill-shaped buttons or 999 px radius anywhere except tags/chips/avatars
- ❌ Excessively rounded cards (radius > 12 px)
- ❌ Glassmorphism / frosted blur panels
- ❌ Random purple/pink accents or multi-colour "creative" palettes (royal blue + neutrals +
  semantic only)
- ❌ Emoji in UI chrome (buttons, nav, headings) — emoji allowed only in user-generated content
- ❌ Generic AI stock photo collages in hero sections — use real work samples, category icon
  tiles, and product screenshots instead
- ❌ Auto-rotating carousels on the homepage
- ❌ More than one primary (filled royal) button per view
- ❌ Inter in a single weight for everything
- ❌ Microcopy that sounds like a template ("Unlock your potential today!")

### 12.4 Do-patterns

- ✅ Hero on homepage: plain headline + search box + category tiles. One real product screenshot or
  a worker profile card rendered live — not an illustration of a person looking at a laptop.
- ✅ Trust strip: "Protected by escrow · Verified workers · Naira payments · Support in English"
  as a quiet 4-item row with small icons.
- ✅ Escrow explained in one sentence with a small shield icon, on every checkout.
- ✅ Verified badge: compact shield-check icon + "Verified" label in `royal-600` on `royal-50`
  chip, 12 px radius.
- ✅ Money amounts: right-aligned in tables, `tabular-nums`, bold when actionable.
- ✅ Empty states: simple line illustration or icon + one line of plain language + one CTA.
- ✅ Loading: skeletons (grey shimmer) — never spinners for full-page content.
- ✅ States that need attention use left-border accent bars (4 px, semantic colour), not full red
  backgrounds.
- ✅ Nigerian details done tastefully: state pickers, `+234` phone formatting, ₦ everywhere,
  "bank transfer (instant)" as a named, familiar payment method.

### 12.5 Frontend rebuild constraints

- **Stack (fixed):** HTML + Tailwind CSS + Vanilla JS. No React/Vue/Svelte, no component
  frameworks, no SPA framework.
- Server-rendered PHP pages (existing backend) + Tailwind built at build time (CLI build, not the
  CDN play CDN in production).
- Vanilla JS as small, namespaced modules per page behaviour (search, filters, chat polling,
  package selection, withdraw form). Fetch API + JSON for dynamic parts; progressive enhancement
  (core flows work without JS where feasible).
- **Design tokens live in `tailwind.config.js`** (colours, radii, type, spacing) so the token
  tables above are the single source of truth.
- Reusable PHP partials for: header, footer, search box, worker card, service/package card,
  order status chip, wallet ledger row, empty state, notification banner.
- Page inventory for the rebuild (all P0 unless noted): Home · Search results · Category page ·
  Worker profile · Service detail · Job list · Job detail · Post a job · Auth (login/register/OTP)
  · Worker dashboard (overview, orders, wallet, services, proposals, jobs-for-me) · Client
  dashboard (orders, wallet/payment history, saved searches P1) · Messages · Dispute pages
  (open/detail) · Review prompt · Account settings · Safety/Help pages (escrow explainer, fees,
  terms, privacy) · Admin console (full §9.18).

---

## 13. Architecture & technical approach

> **Context:** the Skilvi backend already exists in PHP. This PRD does not redesign the backend;
> it defines the **expected API surface and data model** so the frontend rebuild and any backend
> gaps can be reconciled. **Action: map these against the existing endpoints/entities (OQ-7).**

### 13.1 System shape

```
[Browser: HTML + Tailwind + Vanilla JS]
        │  (server-rendered pages + fetch/JSON for dynamic parts)
[PHP backend (existing)]  ──  [MySQL/MariaDB]
        │
        ├── [Payment provider: Paystack or Flutterwave]  (payments, transfers, webhooks)
        ├── [SMS provider] (OTP, money-critical alerts)   [P1]
        ├── [Email provider] (transactional email)
        └── [Object/local storage] (portfolio images, KYC docs — encrypted, outside web root)
```

- Server-rendered pages for all non-interactive surfaces (SEO + speed).
- JSON API for: search/filter (live update), chat polling, order state changes, wallet actions,
  admin queue actions.
- Webhooks (idempotent, signature-verified) for: payment success/failure, refund, chargeback,
  transfer (withdrawal) status.
- Background jobs (simple queue or cron): auto-release timers (ORD-06), job expiry (JOB-02),
  notification retries, report-threshold checks (RPT-04).

### 13.2 Expected API surface (reconcile with existing — OQ-7)

| Area | Endpoints (illustrative) |
|---|---|
| Auth | `POST /api/auth/otp/request` · `POST /api/auth/otp/verify` · `POST /api/auth/login` · `POST /api/auth/logout` · `PATCH /api/account` |
| Discovery | `GET /api/search?q=&category=&mode=&state=&verified=&rating=&price_min=&price_max=&sort=&page=` · `GET /api/categories` |
| Workers/Services | `GET /api/workers/{id}` · `GET /api/workers/{id}/services` · `GET /api/services/{id}` · `POST /api/services` · `PATCH /api/services/{id}` (worker) |
| Jobs | `GET /api/jobs` · `POST /api/jobs` · `GET /api/jobs/{id}` · `POST /api/jobs/{id}/proposals` · `POST /api/proposals/{id}/award` |
| Orders | `POST /api/orders` (from package or accepted proposal) · `GET /api/orders` · `GET /api/orders/{id}` · `POST /api/orders/{id}/start` · `POST /api/orders/{id}/deliver` · `POST /api/orders/{id}/approve` · `POST /api/orders/{id}/revise` · `POST /api/orders/{id}/cancel` |
| Payments | `POST /api/payments/order/{id}` → provider checkout/transfer flow · `POST /webhooks/payment` |
| Wallet | `GET /api/wallet` · `GET /api/wallet/ledger` · `POST /api/wallet/withdraw` · `GET /api/wallet/banks` · `POST /api/wallet/banks` |
| Messaging | `GET /api/threads` · `GET /api/threads/{id}/messages` · `POST /api/threads/{id}/messages` · `POST /api/threads/{id}/attachments` |
| Reviews | `POST /api/orders/{id}/review` · `POST /api/reviews/{id}/reply` |
| Trust | `POST /api/reports` · `POST /api/blocks` · `DELETE /api/blocks/{id}` · `POST /api/verification/apply` |
| Disputes | `POST /api/orders/{id}/dispute` · `GET /api/disputes/{id}` · `POST /api/disputes/{id}/evidence` |
| Admin | `/api/admin/*` mirroring §9.18 queues (users, orders, payments, withdrawals, disputes, reports, verification, promotions, categories, config, audit-log read, analytics) |

### 13.3 Data model (core entities)

| Entity | Key fields (summary) |
|---|---|
| `users` | id, phone, email, password_hash, role(s) `worker/client/admin`, state, created_at |
| `profiles` | user_id, name, headline, bio, avatar, state_id, city, work_mode, service_area, availability, completeness |
| `skills` / `worker_skills` | taxonomy links |
| `categories` | id, parent_id, name, slug, icon, mode_default, state |
| `services` | worker_id, title, description, category_id, work_mode, state |
| `packages` | service_id, name, price_ngn, delivery_days, revision_rounds, travel_included, includes (json) |
| `portfolios` | worker_id, title, description, media, link |
| `jobs` | client_id, title, description, category_id, budget_ngn, budget_type, work_mode, location, deadline, state |
| `proposals` | job_id, worker_id, price_ngn, timeline_days, message, portfolio_link, state |
| `orders` | id, client_id, worker_id, source (`package|proposal|custom`), service_id?, job_id?, package_id?, amount_ngn, commission_pct, state, created/started/delivered/completed/settled_at |
| `order_events` | order_id, type, actor, note, created_at (append-only) |
| `payments` | order_id, provider, provider_ref, method, amount_ngn, state, created_at |
| `payment_ledger` | id, type, amount_ngn, order_id?, wallet_id?, ref, created_at (append-only) |
| `wallets` | worker_id, available_ngn, pending_ngn (denormalized, reconciled to ledger) |
| `withdrawals` | wallet_id, bank, account_ref, amount_ngn, state, threshold_review, processed_at |
| `reviews` | order_id, reviewer_id, worker_id, stars, text, tags (json), state |
| `review_replies` | review_id, text |
| `disputes` | order_id, opened_by, reason, state, resolution, split_pct, resolved_by, timestamps |
| `dispute_evidence` | dispute_id, type, file, note |
| `reports` | reporter_id, target_type, target_id, reason, note, state |
| `blocks` | blocker_id, blocked_id, created_at |
| `verifications` | worker_id, docs (encrypted refs), fee_payment_ref, state, decided_by, expires_at |
| `promotions` | worker_id/service_id, type, window_start, window_end, amount_ngn, state |
| `notifications` | user_id, type, channel, payload, read_at |
| `audit_logs` | admin_id, action, target_type, target_id, before (json), after (json), reason, ip, created_at (append-only) |

### 13.4 Security architecture notes

- Role checks enforced in one middleware (page-level + API-level); never trust the client role flag.
- All money mutations go through the ledger service (single writer) — no direct balance updates.
- Webhook endpoint validates provider signature before trusting any status.
- Admin: 2FA, short sessions, IP allowlist optional per deployment, all actions → `audit_logs`.
- File uploads: MIME validation, AV scan hook (P1), served via signed URLs for private docs.

---

## 14. Key user flows (step-by-step)

### F1 — Worker joins and is found
1. Worker lands on skilvi.ng → sees how it works (3 steps: Create profile → Get verified → Get hired) + "Join free".
2. Registers with phone → OTP → chooses "I have skills to offer".
3. Onboarding (WBK-08): basics → picks skills from taxonomy → adds first service with one package.
4. Completeness meter nudes portfolio/bio → profile goes `active`.
5. (Optional) Buys verification → badge in 2 business days. (Optional) Buys a search boost.
6. Client searches "web developer Port Harcourt" → finds them (verified badge, rating, price from).

### F2 — Client direct hire with escrow (happy path)
1. Client searches, filters (remote + verified), opens a worker's service page.
2. Chooses "Standard — ₦85,000 · 10 days · 2 revisions" → fills the brief → order summary.
3. Summary shows: price, escrow sentence, "Hire & pay ₦85,000".
4. Pays by bank transfer (instant) → webhook confirms → order `PAID`, escrow holds ₦85,000.
5. Worker notified; order `IN_PROGRESS`. Both communicate in the order thread.
6. Worker delivers (files + note) → `DELIVERED`. Client reviews work.
7. Client: "Approve & release" → `COMPLETED` → settles: worker wallet +₦76,500 (90%),
   Skilvi +₦8,500.
8. Client prompted to review (REV-08) → 5 stars. Worker's profile improves.

### F3 — Revision path
Steps 1–6 as above, but at step 6 the client requests a revision (round 1 of 2) with notes →
`IN_PROGRESS` → worker re-delivers → client approves → settled. If round 2 is used and work is
still unsatisfactory → client's options: approve, or open dispute (DSP).

### F4 — Job posting → proposal → award
1. Client posts "Bathroom tiling — Lekki, on-site — budget ₦300,000".
2. 5 tiling workers in Lagos see it in "New jobs for you" → submit proposals (price, days, message,
   photo of past tiling).
3. Client compares (verified badges + ratings visible), chats two of them, awards one at ₦280,000.
4. Client pays → escrow → work → on-site delivery → client approves on-site (client confirms
   completion in-app; worker's delivery note includes before/after photos) → settled.

### F5 — Withdrawal
1. Worker wallet shows ₦146,500 available → "Withdraw ₦100,000".
2. Selects saved bank account → confirm → `requested` (auto-approve, under threshold).
3. Provider transfer → `paid` in ~24 h → SMS/email confirmation. Ledger updated.

### F6 — Dispute
1. Mid-job, client stops responding; worker opens dispute ("Client unresponsive after 10 days").
2. Funds freeze. Admin acknowledges in 1 business day, reviews messages, asks both sides for
   updates.
3. Resolution: 50% released to worker for work evidenced, 50% refunded. Both parties notified with
   reasoning. Order `CLOSED`. Dispute counted on both trust scores.

---

## 15. Metrics & success criteria

**North star:** GMV from completed orders (weekly).

| Area | Metric | Launch target (6 months) |
|---|---|---|
| Supply | Active workers (≥1 live service) | 1,000 |
| Supply | % new workers with live service within 7 days | ≥ 50% |
| Demand | Active clients (first order placed) | 500 |
| Demand | Client search → first hire conversion | ≥ 8% |
| Core loop | Orders completed / month | ≥ 200 |
| Core loop | Repeat-client rate (2+ orders) | ≥ 30% |
| Trust | Escrow coverage (% of GMV through Skilvi payments) | 100% |
| Trust | Dispute rate (% of completed orders disputed) | < 3% |
| Trust | Review rate (% completed orders with review) | ≥ 60% |
| Quality | Avg. rating across platform | ≥ 4.3 |
| Money | Withdrawal success rate | ≥ 98% |
| Revenue | Commission revenue / month; verification conversion (% of verified workers); promotion attach rate | Track from month 1; review at month 6 |
| Safety | Report→action time | < 2 business days |

**Instrumentation:** event tracking from day one (page views, search, CTA clicks, order state
transitions, payment funnel steps) — the funnel in ADM-12 depends on it.

---

## 16. Risks & mitigations

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R1 | Chicken-and-egg: no supply → clients leave; no demand → workers churn | High | High | Launch one city + 3–4 categories with hand-recruited supply; seed with workers who already have local demand; invite-only client beta before public launch |
| R2 | Off-platform diversion (both parties agree to pay outside Skilvi) | High | High | Escrow benefit is the pull; chat soft-warnings (MSG-06); verification badge only earned on-platform; (P2) nudge after repeated off-platform contact detected |
| R3 | Fraud: fake workers, stolen IDs, refund abuse, collusive disputes | Medium | High | KYC at verification; payment-failure & duplicate-account flags (ADM-10); trust scores (DSP-07); withdrawal thresholds + new-account confirmation (WAL-04); audit log |
| R4 | Payment provider failure / webhook loss | Medium | High | Idempotent webhooks + reconciliation job (PAY-04, ADM-04); secondary provider option in contract |
| R5 | Escrow funds sit with provider — regulatory ambiguity | Medium | High | Legal review before launch (A-1, OQ-9); keep client funds inside provider-held balances; no Skilvi-held customer money |
| R6 | Support overload at launch (disputes, payment issues) | Medium | Medium | Plain-language self-serve help pages (escrow, fees, states); dispute SLA discipline; escalation queue |
| R7 | Design drift toward "AI template" look during build | Medium | Medium | §12.3 anti-pattern list is a review gate; token-first (tailwind.config) so deviations are visible; design sign-off per page |
| R8 | Vanilla JS complexity creep (chat, live search) | Medium | Low | Small namespaced modules; no framework; polling over websockets; strict JS budget (NFR-02) |
| R9 | Trade-worker onboarding friction (low web literacy) | Medium | Medium | 3-step wizard (WBK-08), phone-first everything, optional assisted onboarding (partner/agent helps a worker set up profile — P2) |

---

## 17. Roadmap (phases, relative timing)

**Phase 0 — Foundation (weeks 1–4)**
Design system tokens + component partials (Tailwind config, cards, chips, forms, empty states) ·
static pages (home, auth, profile skeletons) · reconcile API surface with existing backend (OQ-7) ·
event tracking instrumentation.

**Phase 1 — Core marketplace & escrow (weeks 5–12) — LAUNCH**
All P0: auth, profiles, services/packages, search/browse/filters, direct hire + escrow, job
posting + proposals, messaging, orders, payments, wallet + withdrawals, disputes (in-app),
reviews, reports/blocks, admin console (queues + audit), notifications (in-app + email),
safety pages. Private beta with hand-recruited supply → public launch in target city/cities.

**Phase 2 — Trust & monetization (weeks 13–20)**
Verification flow + admin queue · promotion (search boost + category spotlight) · SMS
notifications · chargeback handling (PAY-10) · bank-account verification (WAL-04) · analytics
depth · mutual-review groundwork · search synonym/quality tuning.

**Phase 3 — Growth (post-quarter 1, scope to be specced separately)**
"Learn" content (guides → structured courses) · additional cities → national · international
clients (multi-currency, international payment rails) · native apps · agency accounts ·
assisted onboarding for trades.

---

## 18. Open questions (answer these — they gate P0/P1 decisions)

| ID | Question | Default assumption used in this PRD |
|---|---|---|
| OQ-1 | **Payment provider:** Paystack, Flutterwave, or both? (Affects transfer/USSD capability and payout APIs.) | Flutterwave assumed (broader transfer+USSD), Paystack as alternative |
| OQ-2 | **Verification & promotion at launch or phase 2?** | Phase 2 (P1), per §8.2 |
| OQ-3 | **"Learn":** is any learning content planned for v1, or is it brand voice for now? | Brand voice only; feature is Phase 3 |
| OQ-4 | **Off-platform contact policy:** allow phone sharing in chat/profile, or block it? (Affects R2 strategy.) | Allow with soft warnings (current Nigerian market norm) |
| OQ-5 | **Exact royal blue hex** + logo/brand assets available? | Scale in §12.2 is proposed |
| OQ-6 | **Launch geography:** which city/cities first (Port Harcourt? Lagos? Abuja?)? | Single-city launch, undecided |
| OQ-7 | **Existing backend inventory:** which endpoints/entities already exist? (Needed to scope the rebuild.) | §13.2/13.3 treated as target state |
| OQ-8 | **Support channel:** email-only at launch, or WhatsApp line? | In-app contact form + email (ADM-14) |
| OQ-9 | **Escrow funds location:** confirmed with legal that funds stay in payment-provider-held balances? | Assumed yes (A-1) |
| OQ-10 | **Worker on-site scheduling:** do on-site jobs need appointment/date booking in v1, or just "arrange in chat"? | Arrange in chat (v1); booking = P2 |

---

## 19. Assumptions register

| ID | Assumption |
|---|---|
| A-1 | Escrow funds are held in payment-provider balances, never in a Skilvi bank account. |
| A-2 | Phone (OTP) is the primary identity; email is secondary. |
| A-3 | Launch market is English-speaking Nigeria; all UI in English with NDPR compliance. |
| A-4 | No existing Skilvi brand guideline document exists beyond "royal blue, human, calm" — §12 is the starting design spec. |
| A-5 | Price guardrails: service packages ₦500–₦20,000,000 (config). |
| A-6 | Commission: 10%, worker-absorbed, deducted at settlement. |
| A-7 | Minimum withdrawal ₦5,000; auto-approve threshold ₦100,000. |
| A-8 | Verification price ₦5,000 one-time, 2-year validity, docs retained 12 months post-decision. |
| A-9 | Promotion prices: search boost ₦2,500/7d, category spotlight ₦5,000/7d. |
| A-10 | Admin force-release of escrow above ₦250,000 requires a second admin's approval. |
| A-11 | Auto-release timer: 5 business days after delivery, configurable. |
| A-12 | Job postings auto-expire after 30 days. |
| A-13 | Chat delivery via 15–30 s polling (no websocket infra in v1). |
| A-14 | The existing PHP backend can be extended (endpoints added) — a full backend rewrite is not planned. |

---

## 20. Glossary

- **Escrow** — payment held by Skilvi (via the payment provider) until the order reaches its
  completion stage.
- **GMV** — gross merchandise value: total value of completed orders.
- **Take rate / commission** — Skilvi's cut of a completed order (A-6).
- **LGA** — Local Government Area (Nigerian administrative unit).
- **NIP** — NIBSS Instant Payment (same-day bank transfers in Nigeria).
- **KYC** — Know Your Customer; identity verification (used in VER-02).
- **NDPR** — Nigeria Data Protection Regulation.
- **Package** — a fixed-price tier of a service (SVC-02).
- **Promoted** — paid visibility placement, always labelled (PROM-01).
- **Verified** — identity-verified badge (VER). Not a skill certification.
- **Worker** — a user who offers skills/services. **Client** — a user who hires. A user can be both.

---

*End of PRD. Version 0.9 — send corrections against the Open Questions list (OQ-1…OQ-10) and the
Assumptions register (A-1…A-14); the rest of the document is written to survive those answers
without restructuring.*
