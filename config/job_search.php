<?php

// Job search: job discovery and email applications. Every limit here is
// a guardrail on AI spend, sending reputation or third-party sites.
return [

    'model' => env('JOB_SEARCH_AI_MODEL', 'gemini-3.1-flash-lite'),

    'user_agent' => env('JOB_SEARCH_USER_AGENT', 'BlackMailJobBot/1.0 (+https://mail.black.co.ke)'),

    // Job boards (the job_boards table, managed with `job-boards:add`).
    'boards' => [
        // Job pages read per board per hourly run, one at a time with a
        // pause between.
        'details_per_run' => 40,
        'delay_ms' => 1500,
        // New job links kept from one listing, newest first.
        'max_links_per_listing' => 300,
        // Sitemaps read from a sitemap index per run (only changed ones).
        'max_sitemaps_per_run' => 30,
        // Job pages the AI reads per day, across all boards without
        // structured job data, and how much of each page it's given.
        'max_ai_pages_per_day' => 300,
        'max_job_page_chars_for_ai' => 12_000,
    ],

    // Applications sent per user per day: the default and the most a user
    // can choose.
    'default_daily_limit' => 5,
    'max_daily_limit' => 10,

    // Preferred locations a user can pick (counties and towns, chosen from a
    // fixed list in the settings form).
    'max_locations' => 10,

    // Matches below this score are never applied to; users can only raise it.
    'min_match_score_floor' => 60,
    'default_min_match_score' => 75,

    // Postings sent to the AI for scoring, per user per day, and per call.
    'max_scored_per_day' => 40,
    'match_batch_size' => 10,

    // No new cover letters are drafted while this many await review.
    'max_pending_drafts' => 15,

    // Applications only go out on weekdays within these hours, when an
    // employer is likely to read them.
    'timezone' => 'Africa/Nairobi',
    'send_hours' => [8, 17],

    // Never apply to the same company twice within this many days.
    'company_cooldown_days' => 30,

    // Postings older than this are ignored.
    'max_posting_age_days' => 21,

    // Auto-apply pauses itself after this many bounced applications.
    'bounce_pause_threshold' => 3,
    'bounce_window_days' => 7,

    // Careers pages a user can add by hand.
    'max_career_pages' => 10,

    // The daily news stories, posted by Black Mail's official account: one
    // for each career field job seekers' CVs are tagged with, shown to the
    // people tagged with that field.
    'news' => [
        'account_email' => env('JOB_SEARCH_NEWS_ACCOUNT_EMAIL', 'al@mail.black.co.ke'),
        'model' => env('JOB_SEARCH_NEWS_MODEL', env('JOB_SEARCH_AI_MODEL', 'gemini-3.1-flash-lite')),
        'max_articles' => 5,
        // Up to this many fields per CV.
        'max_fields' => 2,
        'fields' => [
            'software_it' => 'Software & IT',
            'data_analytics' => 'Data & analytics',
            'accounting_finance' => 'Accounting & finance',
            'banking_insurance' => 'Banking & insurance',
            'sales_marketing' => 'Sales & marketing',
            'customer_service' => 'Customer service',
            'human_resources' => 'Human resources',
            'administration' => 'Administration & office support',
            'procurement_logistics' => 'Procurement, supply chain & logistics',
            'engineering' => 'Engineering',
            'construction_real_estate' => 'Construction & real estate',
            'healthcare' => 'Healthcare',
            'education' => 'Education & training',
            'legal' => 'Legal & compliance',
            'ngo_development' => 'NGOs & development',
            'agriculture' => 'Agriculture & agribusiness',
            'hospitality_tourism' => 'Hospitality & tourism',
            'media_communications' => 'Media & communications',
            'design_creative' => 'Design & creative',
            'manufacturing' => 'Manufacturing & production',
            'energy_utilities' => 'Energy & utilities',
            'telecommunications' => 'Telecommunications',
            'public_sector' => 'Government & public sector',
            'security' => 'Security',
            'transport_aviation' => 'Transport & aviation',
        ],
    ],

    // Finding company careers pages with Gemini's Google Search.
    'discovery' => [
        'model' => env('JOB_SEARCH_DISCOVERY_MODEL', env('JOB_SEARCH_AI_MODEL', 'gemini-3.1-flash-lite')),
        // One search per user this often; results for the same roles and
        // places are shared between users for the same period.
        'interval_days' => 7,
        // Pages kept per user from searching, and new ones added per search.
        'max_pages' => 20,
        'max_new_per_search' => 8,
        // Search result links resolved to their real address, per search.
        'max_links_resolved' => 10,
        // Never added as careers pages: job boards list many employers (the
        // ones whose terms allow it are read as boards instead, and their
        // hosts are skipped too), and social sites aren't careers pages.
        'blocked_hosts' => [
            'linkedin.com', 'indeed.com', 'glassdoor.com', 'brightermonday.co.ke',
            'myjobmag.co.ke', 'fuzu.com', 'jobwebkenya.com', 'kenyajob.com',
            'careerpointkenya.co.ke', 'jobberman.com', 'ngojobsinafrica.com',
            'opportunitiesforyoungkenyans.co.ke', 'greatkenyanjobs.com',
            'jobsinkenya.co.ke', 'myjobsinkenya.com', 'kenyamoja.com', 'careerjet.co.ke',
            'jooble.org', 'adzuna.co.ke', 'facebook.com', 'x.com', 'twitter.com',
            'instagram.com', 'tiktok.com', 'youtube.com', 'google.com', 'wikipedia.org',
        ],
    ],

    // Job alert emails the user signed up for on other sites (LinkedIn,
    // Fuzu, ...), read from their mailbox: their own mail, so no site is
    // crawled.
    'alerts' => [
        // Senders whose mail is read as job alerts: domain => site name.
        'senders' => [
            'linkedin.com' => 'LinkedIn',
            'indeed.com' => 'Indeed',
            'fuzu.com' => 'Fuzu',
            'brightermonday.co.ke' => 'BrighterMonday',
            'myjobmag.co.ke' => 'MyJobMag',
            'glassdoor.com' => 'Glassdoor',
            'careerjet.co.ke' => 'Careerjet',
            'jooble.org' => 'Jooble',
        ],
        // Alerts the AI reads per user per day, how much of each, and how
        // many jobs are kept from one alert.
        'max_per_user_per_day' => 20,
        'max_chars_for_ai' => 30_000,
        'max_jobs_per_alert' => 30,
    ],

    // Job posts the user shares from WhatsApp, Telegram and the like (text,
    // a link, or posters and screenshots), read by the AI.
    'shared' => [
        'max_per_user_per_day' => 20,
        'max_images' => 4,
        'max_image_kilobytes' => 8192,
        'max_text_chars' => 20_000,
        'max_jobs_per_post' => 5,
    ],

    // Largest page body read from any site, and the slice of it given to the AI.
    'max_page_bytes' => 2_000_000,
    'max_page_chars_for_ai' => 30_000,

    'max_cv_kilobytes' => 5120,

    // The address users without a mailbox get on the app's own domain
    // (services.mailgun.domain), made from their name.
    'mailbox' => [
        'suggestions' => 3,
        'min_length' => 3,
        'max_length' => 30,
        // Never given to a user: role addresses, and ones that would look
        // like they speak for the app.
        'reserved' => [
            'abuse', 'admin', 'administrator', 'billing', 'black', 'careers',
            'contact', 'help', 'hello', 'hostmaster', 'hr', 'info', 'jobs', 'mail',
            'mailer-daemon', 'no-reply', 'noreply', 'postmaster', 'root', 'sales',
            'security', 'support', 'team', 'webmaster',
        ],
    ],

    // Applying on an employer's own form (the "apply yourself" matches):
    // a headless browser (resources/browser/application-form.mjs) reads
    // the form, the AI suggests answers, the user reviews them, and the
    // browser fills the form in and submits it. One at a time, on the
    // "browser" queue.
    'application_forms' => [
        'node_binary' => env('JOB_SEARCH_NODE_BINARY', 'node'),
        'prepare_timeout' => 120,
        // Solving a CAPTCHA alone can take a minute.
        'submit_timeout' => 240,
        'max_answer_chars' => 5000,
        // Answers the user typed, kept to fill in the same question on
        // later forms.
        'remembered_answers' => 60,
        // A solving service fills in CAPTCHAs; without a key, forms with
        // one are left for the user to submit on the site. Solving breaks
        // most job sites' terms, so it's off unless a key is set.
        'captcha' => [
            'provider' => env('CAPTCHA_SOLVER', '2captcha'),
            'key' => env('CAPTCHA_SOLVER_KEY'),
        ],
    ],
];
