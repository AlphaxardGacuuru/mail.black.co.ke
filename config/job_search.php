<?php

// Job search: job discovery and email applications. Every limit here is
// a guardrail on AI spend, sending reputation or third-party sites.
return [

    'model' => env('JOB_SEARCH_AI_MODEL', 'gemini-2.5-flash-lite'),

    'user_agent' => env('JOB_SEARCH_USER_AGENT', 'BlackMailJobBot/1.0 (+https://mail.black.co.ke)'),

    'myjobmag' => [
        'feed_url' => 'https://www.myjobmag.co.ke/jobsxml.xml',
        // Job pages fetched per run, one at a time with a pause between.
        'details_per_run' => 40,
        'delay_ms' => 1500,
    ],

    // Applications sent per user per day: the default and the most a user
    // can choose.
    'default_daily_limit' => 5,
    'max_daily_limit' => 10,

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

    // Finding company careers pages with Gemini's Google Search.
    'discovery' => [
        'model' => env('JOB_SEARCH_DISCOVERY_MODEL', env('JOB_SEARCH_AI_MODEL', 'gemini-2.5-flash-lite')),
        // One search per user this often; results for the same roles and
        // places are shared between users for the same period.
        'interval_days' => 7,
        // Pages kept per user from searching, and new ones added per search.
        'max_pages' => 20,
        'max_new_per_search' => 8,
        // Search result links resolved to their real address, per search.
        'max_links_resolved' => 10,
        // Job boards, aggregators and social sites are never added: their
        // terms don't allow automated applying, and myjobmag is read anyway.
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

    // Largest page body read from any site, and the slice of it given to the AI.
    'max_page_bytes' => 2_000_000,
    'max_page_chars_for_ai' => 30_000,

    'max_cv_kilobytes' => 5120,
];
