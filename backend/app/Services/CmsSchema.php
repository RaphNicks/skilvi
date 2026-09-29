<?php
declare(strict_types=1);

namespace App\Services;

/** Structured CMS fields for public marketing pages and site-wide assets. */
final class CmsSchema
{
    /** @return list<array{id:string,label:string,fields:list<array<string,mixed>>}> */
    public static function pages(): array
    {
        return [
            [
                'id' => 'brand',
                'label' => 'Brand & media',
                'fields' => [
                    self::media('brand.logo', 'Wordmark logo', 'image', '/assets/img/skilvi-logo-word.png', ['assets/img/skilvi-logo-word.png']),
                    self::media('brand.favicon', 'Favicon (mark only)', 'image', '/assets/img/skilvi-favicon.png', ['assets/img/skilvi-favicon.png']),
                    self::media('brand.apple_icon', 'Apple touch icon', 'image', '/assets/img/apple-touch-icon.png', ['assets/img/apple-touch-icon.png']),
                    self::media('brand.hero_video', 'Landing hero video', 'video', '/assets/video/hero-office.mp4', ['assets/video/hero-office.mp4']),
                    self::media('brand.hero_poster', 'Landing hero poster', 'image', '/assets/video/hero-office-poster.jpg', ['assets/video/hero-office-poster.jpg']),
                    self::media('brand.loader_webm', 'Page loader (WebM)', 'video', '/assets/video/skilvi-loader.webm', ['assets/video/skilvi-loader.webm']),
                    self::media('brand.loader_mp4', 'Page loader (MP4 fallback)', 'video', '/assets/video/skilvi-loader.mp4', ['assets/video/skilvi-loader.mp4']),
                    self::media('brand.earn_photo', '“Earn” section photo', 'image', '/assets/img/earn-worker.jpg', ['assets/img/earn-worker.jpg']),
                ],
            ],
            [
                'id' => 'banner',
                'label' => 'Site banner',
                'fields' => [
                    self::sel('banner.on', 'Visibility', 'off', [
                        'off' => 'Hidden',
                        'on'  => 'Visible on the site',
                    ]),
                    self::sel('banner.tone', 'Tone', 'info', [
                        'info'   => 'Notice',
                        'promo'  => 'Promo',
                        'warn'   => 'Warning',
                        'urgent' => 'Urgent / downtime',
                    ]),
                    self::f('banner.text', 'Banner text', 'text', ''),
                    self::f('banner.link', 'Link (optional)', 'url', ''),
                    self::f('banner.link_label', 'Link label', 'text', 'Learn more'),
                ],
            ],
            [
                'id' => 'nav',
                'label' => 'Navigation & footer',
                'fields' => [
                    self::f('nav.talent', 'Nav: Find Talent', 'text', 'Find Talent'),
                    self::f('nav.categories', 'Nav: Categories', 'text', 'Categories'),
                    self::f('nav.work', 'Nav: Find Work', 'text', 'Find Work'),
                    self::f('nav.signin', 'Nav: Sign In', 'text', 'Sign In'),
                    self::f('nav.signup', 'Nav: Sign Up', 'text', 'Sign Up'),
                    self::f('nav.signup_long', 'Mobile Sign Up', 'text', "Sign Up — it's free"),
                    self::f('footer.tag', 'Footer tagline', 'text', 'DISCOVER. LEARN. EARN.'),
                    self::f('footer.desc', 'Footer description', 'textarea', 'The Nigeria-first marketplace for skills and work — digital and hands-on. Every order protected by escrow.'),
                    self::f('footer.desc_alt', 'Compact footer description', 'textarea', 'Discover. Learn. Earn. A marketplace built for Nigeria — digital skills and hands-on trades, paid securely in Naira.'),
                    self::f('footer.copyright', 'Copyright line', 'text', '© 2026 Skilvi Technologies Ltd. All rights reserved.'),
                    self::f('footer.made', 'Footer secondary line', 'text', 'Made in Nigeria · Payments held securely in escrow'),
                    self::f('footer.made_alt', 'Compact footer secondary', 'text', 'Built in Nigeria · Payments held securely in escrow'),
                ],
            ],
            [
                'id' => 'landing',
                'label' => 'Landing page',
                'fields' => [
                    self::f('landing.meta_title', 'Browser title', 'text', 'Skilvi — Hire trusted talent for any job in Nigeria'),
                    self::f('landing.meta_desc', 'Meta description', 'textarea', "Skilvi is Nigeria's marketplace for hiring trusted professionals — digital skills and hands-on trades. Post a job, compare verified talent, and pay safely through escrow."),
                    self::f('landing.hero.eyebrow', 'Hero eyebrow', 'text', "Nigeria's #1 marketplace to hire skilled work"),
                    self::f('landing.hero.h1', 'Hero headline', 'html', 'Hire trusted talent.<br>Get it done right.'),
                    self::f('landing.hero.sub', 'Hero body', 'textarea', 'Post a job and get matched with verified professionals — from web developers to master plumbers. You pay Skilvi first; your money is only released when the work is done to your standard.'),
                    self::f('landing.hero.placeholder', 'Search placeholder', 'text', 'What do you need done? e.g. website, plumber, logo…'),
                    self::f('landing.hero.search_btn', 'Search button', 'text', 'Find talent'),
                    self::f('landing.hero.popular_label', 'Popular label', 'text', 'Popular:'),
                    self::f('landing.hero.alt', 'Earn link under search', 'text', 'I have a skill — start earning on Skilvi'),
                    self::f('landing.trusted', 'Trusted-by line', 'text', 'Trusted by teams, businesses and homes across Nigeria'),
                    self::f('landing.jobs.h2', 'Jobs heading', 'text', 'Jobs get posted. Talent shows up.'),
                    self::f('landing.jobs.sub', 'Jobs subhead', 'textarea', 'Real work posted by clients like you — matched with trusted professionals in days, not weeks.'),
                    self::f('landing.stat1.n', 'Stat 1 number', 'text', '12,000+'),
                    self::f('landing.stat1.l', 'Stat 1 label', 'text', 'Verified professionals'),
                    self::f('landing.stat2.n', 'Stat 2 number', 'text', '₦4.2B'),
                    self::f('landing.stat2.l', 'Stat 2 label', 'text', 'Paid out safely in escrow'),
                    self::f('landing.stat3.n', 'Stat 3 number', 'text', '8,500+'),
                    self::f('landing.stat3.l', 'Stat 3 label', 'text', 'Jobs completed for clients'),
                    self::f('landing.cats.h2', 'Categories heading', 'text', 'Browse by what you need done'),
                    self::f('landing.cats.btn', 'All categories button', 'text', 'All categories'),
                    self::f('landing.skills.h2', 'Skills heading', 'text', 'What do you need done?'),
                    self::f('landing.skills.sub', 'Skills subhead', 'textarea', "From code to carpentry — browse by trade and see who's available near you."),
                    self::f('landing.skills.btn', 'Browse all button', 'text', 'Browse All Categories'),
                    self::f('landing.workers.h2', 'Workers heading', 'text', 'Find skilled people'),
                    self::f('landing.workers.sub', 'Workers subhead', 'textarea', 'Verified profiles, real reviews, and a payment method that protects both sides.'),
                    self::f('landing.how.h2', 'How-it-works heading', 'text', 'Hire with confidence'),
                    self::f('landing.how.sub', 'How-it-works intro', 'textarea', 'Built so clients can trust the process: every professional is reachable, every payment is escrow-protected, and disputes are handled by real people.'),
                    self::f('landing.how1.t', 'Step 1 title', 'text', 'Post your job'),
                    self::f('landing.how1.p', 'Step 1 body', 'textarea', 'Describe the work, set your budget and timeline. Posting is free — verified professionals see it the moment it goes live.'),
                    self::f('landing.how1.btn', 'Step 1 button', 'text', 'Post a Job'),
                    self::f('landing.how2.t', 'Step 2 title', 'text', 'Compare & choose'),
                    self::f('landing.how2.p', 'Step 2 body', 'textarea', 'Review verified profiles, ratings and portfolios, and chat before you commit. You hire the right person — not a gamble.'),
                    self::f('landing.how2.btn', 'Step 2 button', 'text', 'Browse Talent'),
                    self::f('landing.how3.t', 'Step 3 title', 'text', 'Money released on completion'),
                    self::f('landing.how3.p', 'Step 3 body', 'textarea', 'You pay Skilvi first and funds stay in escrow until the work meets your standard. Disputes are handled by real people.'),
                    self::f('landing.how3.btn', 'Step 3 button', 'text', 'How escrow works'),
                    self::f('landing.earn.h2', 'Earn heading', 'html', 'Earn from what<br>you already know.'),
                    self::f('landing.earn.sub', 'Earn body', 'textarea', "You don't need to be a “tech person” to earn here. Plumbers, electricians, designers and developers are all paid the same way — safely, through escrow, straight to a Nigerian bank account."),
                    self::f('landing.earn.c1', 'Earn check 1', 'text', 'Create your free profile in minutes'),
                    self::f('landing.earn.c2', 'Earn check 2', 'text', 'Get paid safely through escrow'),
                    self::f('landing.earn.c3', 'Earn check 3', 'text', 'Withdraw to your bank in 1 business day'),
                    self::f('landing.earn.cta', 'Earn button', 'text', 'Become a worker'),
                    self::f('landing.earn.how', 'Earn secondary button', 'text', 'How it works'),
                    self::f('landing.news.h2', 'Newsletter heading', 'html', 'Get opportunities<br>in your inbox'),
                    self::f('landing.news.sub', 'Newsletter body', 'textarea', 'New jobs, fresh talent and marketplace updates. No spam — unsubscribe anytime.'),
                    self::f('landing.news.placeholder', 'Newsletter placeholder', 'text', 'Enter your email address'),
                    self::f('landing.news.btn', 'Newsletter button', 'text', 'Subscribe'),
                ],
            ],
            [
                'id' => 'about',
                'label' => 'About',
                'fields' => [
                    self::f('about.meta_title', 'Browser title', 'text', 'Why Skilvi exists — Skilvi'),
                    self::f('about.eyebrow', 'Eyebrow', 'text', 'Port Harcourt, Nigeria · est. 2026'),
                    self::f('about.h1', 'Headline', 'text', 'Built for Nigeria. Designed to go further.'),
                    self::f('about.sub', 'Intro', 'textarea', 'Skilvi connects skilled Nigerians — developers, designers, tilers, electricians, accountants — with clients who need them. With escrow payments, verified identities and a real dispute process. Discover. Learn. Earn.'),
                    self::f('about.cta1', 'Primary button', 'text', 'Find a worker'),
                    self::f('about.cta2', 'Secondary button', 'text', 'Join as a worker — free'),
                    self::f('about.why.h2', 'Why heading', 'text', 'Why we built this'),
                    self::f('about.why1.t', 'Why 1 title', 'text', 'Global platforms work against locals'),
                    self::f('about.why1.p', 'Why 1 body', 'textarea', 'USD payouts, delayed transfers, arbitrary account freezes — and competing against the whole world on price. A great PH developer or a great Lagos electrician shouldn\'t have to fight that fight.'),
                    self::f('about.why2.t', 'Why 2 title', 'text', 'Local work runs on WhatsApp'),
                    self::f('about.why2.p', 'Why 2 body', 'textarea', 'No profiles, no reviews, no payment protection. If the tiler disappears after your deposit, there is no one to call. Informal trust is real — but it doesn\'t scale.'),
                    self::f('about.why3.t', 'Why 3 title', 'text', 'Clients can\'t tell who\'s real'),
                    self::f('about.why3.p', 'Why 3 body', 'textarea', 'Whether it\'s a logo or a bathroom, the risk feels the same: you\'ve never met this person. So deals stall, or money moves off-platform, unprotected.'),
                    self::f('about.safety.h2', 'Safety heading', 'text', 'How we keep both sides safe'),
                    self::f('about.safety1.t', 'Safety 1 title', 'text', 'Escrow on every order'),
                    self::f('about.safety1.p', 'Safety 1 body', 'textarea', 'Clients pay Skilvi, not the worker. Money is released only when the work is done and approved.'),
                    self::f('about.safety2.t', 'Safety 2 title', 'text', 'Verified identities'),
                    self::f('about.safety2.p', 'Safety 2 body', 'textarea', 'A one-time check of government ID + photo. The badge says who they are — never what we certified.'),
                    self::f('about.safety3.t', 'Safety 3 title', 'text', 'Real dispute mediation'),
                    self::f('about.safety3.p', 'Safety 3 body', 'textarea', 'Frozen funds, evidence, message history, and a 5-business-day resolution target.'),
                    self::f('about.safety4.t', 'Safety 4 title', 'text', 'Audited, accountable staff'),
                    self::f('about.safety4.p', 'Safety 4 body', 'textarea', 'Every admin action on money or accounts is written to an immutable audit log.'),
                    self::f('about.model.t', 'Money rules title', 'text', 'Simple money rules'),
                    self::f('about.model.p', 'Money rules body', 'textarea', 'Workers never pay to exist on Skilvi. We make money when you both succeed.'),
                    self::f('about.road.h2', 'Roadmap heading', 'text', "Where we're going"),
                    self::f('about.road1.t', 'Phase 1 title', 'text', 'Phase 1 · Nigeria (now)'),
                    self::f('about.road1.p', 'Phase 1 body', 'textarea', 'One city at a time: Port Harcourt, Lagos, Abuja. Digital + trades supply, escrow, verification, promotions.'),
                    self::f('about.road2.t', 'Phase 2 title', 'text', 'Phase 2 · West Africa'),
                    self::f('about.road2.p', 'Phase 2 body', 'textarea', 'Ghana, Nigeria, Kenya pilots: local languages, local rails, same trust model. Workers already serving cross-border clients.'),
                    self::f('about.road3.t', 'Phase 3 title', 'text', 'Phase 3 · The world'),
                    self::f('about.road3.p', 'Phase 3 body', 'textarea', 'International clients hiring African skills with confidence — identity verified, payments protected, reputation portable. Plus the Learn engine: courses that turn interest into billable skill.'),
                    self::f('about.team.h2', 'Team heading', 'text', 'The team'),
                    self::f('about.team1.name', 'Founder name', 'text', 'Raphael Zidougha'),
                    self::f('about.team1.role', 'Founder role (card)', 'text', 'Founder'),
                    self::f('about.team1.craft', 'Founder craft', 'text', 'Fullstack Web Developer'),
                    self::media('about.team1.photo', 'Founder photo', 'image', '/assets/img/raphael-zidougha.jpg', ['assets/img/raphael-zidougha.jpg']),
                    self::f('about.team1.blurb', 'Founder card line', 'textarea', 'Building Skilvi to help skills become real opportunities.'),
                    self::f('about.team1.role_long', 'Founder role (panel)', 'text', 'Founder, Skilvi'),
                    self::f('about.team1.p1', 'Founder note 1', 'textarea', 'I started Skilvi because I kept seeing the same problem: people can be genuinely good at something and still struggle to find people willing to trust them enough to pay for it.'),
                    self::f('about.team1.p2', 'Founder note 2', 'textarea', 'I wanted to build something that could change that.'),
                    self::f('about.team1.p3', 'Founder note 3', 'textarea', 'Skilvi is my attempt to make skills more useful in the real world — whether someone is a developer, designer, plumber, electrician, tailor, painter, or just someone who knows how to do something valuable. You shouldn\'t need to know the “right people” before your skill can become an opportunity.'),
                    self::f('about.team1.p4', 'Founder note 4', 'textarea', 'I\'m building Skilvi from Nigeria, with the hope that it eventually becomes something much bigger than a marketplace.'),
                    self::f('about.team1.p5', 'Founder note 5', 'textarea', 'There\'s still a lot to figure out, and we\'re definitely not pretending we\'ve figured it all out. But the goal is simple: help people discover skills, learn, and earn from what they can do.'),
                    self::f('about.team1.p6', 'Founder note 6', 'textarea', 'That\'s what Skilvi means to me.'),
                    self::f('about.team1.aside', 'Founder personal line', 'textarea', 'When I\'m not working on Skilvi or coding or debugging, I\'m usually gaming or watching movies 😁'),
                    self::f('about.cta.h2', 'Closing heading', 'text', "Have a skill? It's worth money."),
                    self::f('about.cta.p', 'Closing body', 'textarea', 'Join free, set up your profile in minutes, and get your first order. No degree, no experience on other platforms, no gatekeepers.'),
                    self::f('about.cta.btn', 'Closing button', 'text', 'Create your free profile'),
                ],
            ],
            [
                'id' => 'help',
                'label' => 'Help center',
                'fields' => [
                    self::f('help.h1', 'Headline', 'text', 'How Skilvi works'),
                    self::f('help.sub', 'Intro', 'textarea', 'Plain answers about escrow, fees, verification, withdrawals and disputes — the things people actually ask about.'),
                    self::f('help.search_ph', 'Search placeholder', 'text', 'Search help — try “refund” or “verification”…'),
                    self::f('help.faq.h2', 'FAQ heading', 'text', 'Frequently asked'),
                    self::faqs('help.faqs', 'Questions', self::defaultFaqs()),
                    self::f('help.stuck.t', 'Still stuck heading', 'text', 'Still stuck?'),
                    self::f('help.stuck.p', 'Still stuck body', 'textarea', 'Our support team replies in English, Mon–Sat, 8am–8pm WAT.'),
                ],
            ],
            [
                'id' => 'legal',
                'label' => 'Terms & privacy',
                'fields' => [
                    self::f('terms.updated', 'Terms “last updated”', 'text', 'Last updated: 17 September 2026'),
                    self::f('terms.h1', 'Terms title', 'text', 'Terms of Service'),
                    self::f('terms.intro', 'Terms intro', 'textarea', 'These terms govern your use of the Skilvi marketplace (the "Platform") operated by Skilvi Technologies Ltd. By creating an account or using the Platform, you agree to these terms. This is a prototype draft for the product build — final wording to be reviewed by legal counsel.'),
                    self::f('terms.s1.t', 'Terms §1 title', 'text', '1. The service'),
                    self::f('terms.s1.p', 'Terms §1 body', 'textarea', 'Skilvi connects clients with independent workers for digital and on-site services. Workers are independent contractors; Skilvi does not employ them and does not guarantee the quality of any service. Skilvi\'s role includes account verification, escrow holding, messaging, dispute mediation, and the payment systems described on the Platform.'),
                    self::f('terms.s2.t', 'Terms §2 title', 'text', '2. Accounts'),
                    self::f('terms.s2.p', 'Terms §2 body', 'textarea', 'You must provide accurate information and keep your credentials secure. Accounts are phone-verified. We may suspend or close accounts that breach these terms, attempt fraud, or create duplicate accounts. You are responsible for all activity on your account.'),
                    self::f('terms.s3.t', 'Terms §3 title', 'text', '3. Orders & escrow'),
                    self::f('terms.s3.p', 'Terms §3 body', 'textarea', 'When a client hires, payment is made to Skilvi and held in escrow via our payment provider. Funds are released to the worker when the order reaches the agreed completion stage — client approval, or automatically 5 business days after delivery where the client does not respond. Pre-start cancellations are refunded in full. Skilvi may hold funds where fraud or an unresolved dispute is indicated.'),
                    self::f('terms.s4.t', 'Terms §4 title', 'text', '4. Fees'),
                    self::f('terms.s4.p', 'Terms §4 body', 'textarea', 'Creating a profile and posting jobs is free. Skilvi charges a 10% commission on each settled order, deducted from the worker\'s share. Optional paid features (verification, promotion) have their own published prices and are never required to receive or place work.'),
                    self::f('terms.s5.t', 'Terms §5 title', 'text', '5. Disputes'),
                    self::f('terms.s5.p', 'Terms §5 body', 'textarea', 'Either party may open a dispute on an active order. Escrow is frozen during review. Skilvi will attempt to mediate and may resolve in favour of either party, in part or in full, based on the evidence and message history. Skilvi\'s resolution is final on the Platform except where law provides otherwise.'),
                    self::f('terms.s6.t', 'Terms §6 title', 'text', '6. Prohibited conduct'),
                    self::f('terms.s6.p', 'Terms §6 body', 'textarea', 'Do not: bypass escrow or move transactions off-Platform; impersonate anyone; post false reviews or manipulate ratings; solicit off-Platform payment; post illegal content; or abuse the verification, promotion, or dispute systems.'),
                    self::f('terms.s7.t', 'Terms §7 title', 'text', '7. Reviews & data'),
                    self::f('terms.s7.p', 'Terms §7 body', 'textarea', 'Reviews may only be written by parties to a completed order and must reflect a genuine experience. We process personal data in line with our Privacy Policy and the Nigeria Data Protection Regulation (NDPR).'),
                    self::f('terms.s8.t', 'Terms §8 title', 'text', '8. Limitation of liability'),
                    self::f('terms.s8.p', 'Terms §8 body', 'textarea', 'Skilvi provides the Platform on a best-efforts basis. To the maximum extent permitted by law, our aggregate liability in any period is limited to the fees paid by you to Skilvi in the 3 months before the claim. We are not liable for the acts or omissions of independent workers or clients.'),
                    self::f('terms.s9.t', 'Terms §9 title', 'text', '9. Changes & termination'),
                    self::f('terms.s9.p', 'Terms §9 body', 'textarea', 'We may update these terms with 14 days\' notice in-app. You may close your account at any time from Settings, subject to settlement of active orders. Closing an account does not affect completed reviews or settled transactions.'),
                    self::f('privacy.h1', 'Privacy title', 'text', 'Privacy Policy'),
                    self::f('privacy.s1.t', 'Privacy §1 title', 'text', '1. What we collect'),
                    self::f('privacy.s2.t', 'Privacy §2 title', 'text', '2. Why we collect it'),
                    self::f('privacy.s3.t', 'Privacy §3 title', 'text', '3. How we store it'),
                    self::f('privacy.s4.t', 'Privacy §4 title', 'text', '4. Who sees what'),
                    self::f('privacy.s5.t', 'Privacy §5 title', 'text', '5. Your rights (NDPR)'),
                    self::f('privacy.s6.t', 'Privacy §6 title', 'text', '6. Cookies & analytics'),
                    self::f('privacy.s7.t', 'Privacy §7 title', 'text', '7. Security'),
                    self::f('privacy.s8.t', 'Privacy §8 title', 'text', '8. Contact'),
                ],
            ],
            [
                'id' => 'marketplace',
                'label' => 'Find talent, jobs, 404',
                'fields' => [
                    self::f('search.h1', 'Search heading', 'text', 'Search results'),
                    self::f('jobs.h1', 'Jobs heading', 'text', 'Open jobs'),
                    self::f('jobs.sub', 'Jobs subhead', 'textarea', 'Clients with real work. Submit a proposal — you only compete when you want to.'),
                    self::f('jobs.post', 'Post a job button', 'text', 'Post a job'),
                    self::f('notfound.h1', '404 number', 'text', '404'),
                    self::f('notfound.h3', '404 heading', 'text', "This page doesn't exist — or it moved."),
                    self::f('notfound.p', '404 body', 'textarea', 'The link may be old, or the address was typed wrong. Your orders, wallet and messages are safe — start from one of these instead.'),
                    self::f('login.tag', 'Login tagline', 'text', 'Email login, Naira-ready, escrow-protected.'),
                    self::f('verify.h1', 'Verification heading', 'text', 'Get verified'),
                    self::f('promo.h1', 'Promotion heading', 'text', 'Promote your profile'),
                ],
            ],
        ];
    }

    /** @return array<string, array<string,mixed>> */
    public static function byKey(): array
    {
        $out = [];
        foreach (self::pages() as $page) {
            foreach ($page['fields'] as $f) {
                $out[$f['key']] = $f;
            }
        }
        return $out;
    }

    /** @return array<string,mixed> */
    private static function f(string $key, string $label, string $type, string $default): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'default' => $default];
    }

    /** @param array<string,string> $options */
    private static function sel(string $key, string $label, string $default, array $options): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'select', 'default' => $default, 'options' => $options];
    }

    /** @param list<array<string,string>> $items */
    private static function faqs(string $key, string $label, array $items): array
    {
        $json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return ['key' => $key, 'label' => $label, 'type' => 'faqs', 'default' => is_string($json) ? $json : '[]'];
    }

    /** @return list<array{id:string,q:string,a:string,card?:string,teaser?:string}> */
    public static function defaultFaqs(): array
    {
        return [
            [
                'id' => 'escrow',
                'q' => 'How does escrow protect me?',
                'a' => 'When you hire, you pay Skilvi — not the worker. The money is held and does not move until the work reaches the agreed completion stage. If the client approves (or 5 business days pass without response), the payment is released to the worker. If something goes wrong, either side can open a dispute and Skilvi mediates before any money moves. No one can “run” with your money or your work.',
                'card' => 'How escrow works',
                'teaser' => 'Money held until the work is done.',
            ],
            [
                'id' => 'fees',
                'q' => "What are Skilvi's fees?",
                'a' => "Posting jobs and creating a worker profile are free. On each completed order, Skilvi keeps a 10% commission from the worker's side — the client pays exactly the agreed price. Withdrawals are currently free. Verification (₦5,000, one-time, 2 years) and promotion packages are optional and never affect your search ranking.",
                'card' => 'Fees & pricing',
                'teaser' => '10% commission, free posting.',
            ],
            [
                'id' => 'verification',
                'q' => 'What does the “Verified” badge actually mean?',
                'a' => 'It means Skilvi checked that the person\'s identity and account are real — government ID plus a matching photo. It is <b>not</b> a skill certification: Skilvi does not test or certify your trade. Judge skills by reviews, portfolio and past orders. Verification is a one-time paid feature and is separate from promotion (which only buys visibility and is always labelled “Promoted”).',
                'card' => 'Getting verified',
                'teaser' => 'Identity check, one-time ₦5,000.',
            ],
            [
                'id' => 'withdrawals',
                'q' => 'How do I get my money out?',
                'a' => 'When an order is completed, the worker\'s share (90% after commission) lands in their wallet. Withdrawals go to a Nigerian bank account via instant transfer, usually within 1 business day. The minimum is ₦5,000. New bank accounts are verified before the first payout for your safety.',
                'card' => 'Withdrawals',
                'teaser' => 'Naira to your bank, next business day.',
            ],
            [
                'id' => 'disputes',
                'q' => "The worker hasn't started / the client isn't responding. What now?",
                'a' => 'Message them in the order thread first — keep everything on Skilvi so there\'s a record. If there\'s no response, open a dispute from the order page. Escrow funds are frozen immediately, and Skilvi reviews the full message history. We aim to acknowledge within 1 business day and resolve within 5.',
                'card' => 'Disputes',
                'teaser' => 'How we mediate, SLAs, outcomes.',
            ],
            [
                'id' => 'onsite',
                'q' => 'Can I do on-site work (plumbing, tiling, painting…)?',
                'a' => 'Yes. On-site services require you to declare your service area (states/cities) and state in the package whether travel is included in the price. Clients see both before hiring. For on-site delivery, the client confirms completion in-app after the work is done — keep photos of before/after as evidence; they help in any dispute.',
            ],
            [
                'id' => 'payments',
                'q' => 'Can I pay by bank transfer or USSD?',
                'a' => 'Yes — card, bank transfer (instant) and USSD are all supported in Naira. You\'ll get a confirmation on your phone and in-app as soon as the payment is verified. We confirm payments through the payment provider directly, so no fake screenshots needed.',
            ],
            [
                'id' => 'promotion',
                'q' => 'What is promotion, and how is it different from verification?',
                'a' => 'They do completely different jobs. <b>Verification</b> is a one-time trust signal: we confirm your identity and account are real, and you get the badge. It says nothing about your skill level. <b>Promotion</b> is paid visibility: your service appears in labelled “Promoted” positions in search results or on category pages for a set period (e.g. 7 days). Promoted items are always labelled, capped (max 3 per results page), and never reordered by Skilvi as “better” — promotion is an ad slot, not a quality ranking.',
                'card' => 'Promotion',
                'teaser' => 'Pay for visibility — always labelled.',
            ],
            [
                'id' => 'reporting',
                'q' => 'How do I report a user, review, or message?',
                'a' => 'Every public profile, review, message and job has a “Report” option (the flag icon). Pick a reason — scam, fake identity, harassment, off-platform payment solicitation, spam, offensive content — and add details. Reports are anonymous to the reported user. 3+ reports on one account in 30 days automatically raise a risk flag for our team. Confirmed violations follow a published escalation path: warning → suspension → ban, with your withdrawal history reviewed where fraud is involved.',
                'card' => 'Reporting a user',
                'teaser' => 'Scams, abuse, off-platform payment.',
            ],
            [
                'id' => 'starting',
                'q' => "I'm a worker. What's the fastest path to my first order?",
                'a' => 'Four steps, all free: (1) Complete your profile — photo, bio, skills, at least one portfolio piece. (2) Create one service with 2–3 packages and honest prices (packages with a “most popular” middle tier convert best). (3) Turn on job alerts and apply to 3–5 matching jobs this week with short, specific proposals. (4) Get verified (₦5,000, one-time) — verified profiles get far more hires, especially in trades. Keep your reply time under a few hours; response speed is shown publicly and clients pick fast responders.',
                'card' => 'Starting as a worker',
                'teaser' => 'Profile → services → first order.',
            ],
        ];
    }

    /** @param list<string> $paths */
    private static function media(string $key, string $label, string $type, string $default, array $paths): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'default' => $default, 'paths' => $paths];
    }
}
