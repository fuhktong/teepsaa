<?php
// Subdomain routing loads here so every page gets it through db.php —
// db.php itself is unmanaged on the server, so it can't hold the require.
require_once __DIR__ . '/subdomain.php';

// ── Which language this request renders in ───────────────────────────
//
// Khmer is what a visitor gets when they haven't said otherwise, and that
// stays true. What's new is that the language can also be named in the
// address: `?lang=en` on any page renders it in English no matter what the
// session says.
//
// That exists for search engines. Language used to live only in the session,
// and a session only exists for someone who has clicked the language
// switcher. Crawlers never click anything, so Google could only ever see the
// default — the English half of the site had no address of its own and was
// invisible to search. Now every page has two addresses: the bare one
// (Khmer) and `?lang=en`. config/seo.php pairs them with hreflang tags and
// sitemap.php lists both, which is what tells Google they're one page in two
// languages rather than two competing pages.
//
// A URL choice is also written to the session, so a person who arrives on an
// English link from Google stays in English as they click around without
// every link needing the parameter. Crawlers keep no cookies, so for them
// each request is decided by its own URL — exactly what we want.
define('DEFAULT_LANG', 'km');

if (!function_exists('current_lang')) {
    function current_lang(): string {
        static $lang = null;
        if ($lang !== null) return $lang;

        $fromUrl = $_GET['lang'] ?? '';
        if ($fromUrl === 'en' || $fromUrl === 'km') {
            // Only persist if a session is actually running — cron scripts and
            // CLI tools load this file too and have no session.
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['lang'] = $fromUrl;
            }
            return $lang = $fromUrl;
        }

        $fromSession = $_SESSION['lang'] ?? '';
        return $lang = ($fromSession === 'en' || $fromSession === 'km')
            ? $fromSession
            : DEFAULT_LANG;
    }
}

// An internal link that keeps the reader in the language they're reading.
//
// A person doesn't need this — their session remembers the switch. Googlebot
// does: it keeps no cookies, so on an English page every bare link would take
// the crawler straight back to Khmer, and the English catalogue would be
// reachable only from the sitemap, with no internal links pointing at it.
// Wrap the links along the public crawl path (nav, category, product, shop)
// and the English site becomes a site rather than a list of orphans.
//
// Returns an href for HTML, so the separator is &amp;. Pass the path exactly
// as it would otherwise be written, escaping and all.
if (!function_exists('lang_href')) {
    function lang_href(string $path): string {
        $lang = current_lang();
        if ($lang === DEFAULT_LANG) return $path;
        return $path . (str_contains($path, '?') ? '&amp;' : '?') . 'lang=' . $lang;
    }
}

// ── Row helpers ──────────────────────────────────────────────────────
//
// These three used to live in config/db.php. They moved here because db.php
// is unmanaged on the server (it holds the live credentials and is excluded
// from deploys), so a change made there would never ship. db.php requires
// this file *before* its own copies, and every copy is wrapped in
// `if (!function_exists(...))` — so these win and db.php's are skipped. Edit
// them here.

// Pick a row's field in the current language, falling back to the base
// (English) value when the Khmer variant is empty. e.g. lang_field($p, 'name')
// returns name_km when the page is Khmer and it's set, otherwise name.
if (!function_exists('lang_field')) {
    function lang_field(array $row, string $field): string {
        $km = $field . '_km';
        return (current_lang() === 'km' && !empty($row[$km]))
            ? $row[$km]
            : ($row[$field] ?? '');
    }
}

// Pick between a base value and its Khmer variant directly (for aliased
// columns where the row keys aren't `field`/`field_km`, e.g. a joined
// business_name / business_name_km).
if (!function_exists('pick_lang')) {
    function pick_lang(?string $base, ?string $km): string {
        return (current_lang() === 'km' && !empty($km))
            ? $km
            : (string)($base ?? '');
    }
}

// Category name in the current language (fallback to English).
if (!function_exists('cat_name')) {
    function cat_name(array $c): string {
        return lang_field($c, 'name');
    }
}

// Localised date formatting. fmt_date() mirrors PHP's date() signature
// (format first, then the timestamp/date string) so a display `date(...)`
// call can be swapped to `fmt_date(...)` verbatim. In Khmer it translates
// English month names, am/pm, and digits to Khmer; in English it's identical
// to date(). Only use it for DISPLAY dates — never for input values or
// comparisons (keep date('Y-m-d') etc. as-is for those).

if (!function_exists('km_num')) {
    function km_num(string $s): string {
        return strtr($s, ['0'=>'០','1'=>'១','2'=>'២','3'=>'៣','4'=>'៤','5'=>'៥','6'=>'៦','7'=>'៧','8'=>'៨','9'=>'៩']);
    }
}

if (!function_exists('fmt_date')) {
    function fmt_date(string $fmt, $when = null): string {
        $ts = ($when === null) ? time() : (is_numeric($when) ? (int)$when : strtotime((string)$when));
        if (!$ts) return '';
        $out = date($fmt, $ts);
        if (current_lang() !== 'km') return $out;

        // Longest keys first so "September" wins over "Sep", etc.
        $out = strtr($out, [
            'January'=>'មករា','February'=>'កុម្ភៈ','March'=>'មីនា','April'=>'មេសា',
            'June'=>'មិថុនា','July'=>'កក្កដា','August'=>'សីហា','September'=>'កញ្ញា',
            'October'=>'តុលា','November'=>'វិច្ឆិកា','December'=>'ធ្នូ','May'=>'ឧសភា',
            'Jan'=>'មករា','Feb'=>'កុម្ភៈ','Mar'=>'មីនា','Apr'=>'មេសា','Jun'=>'មិថុនា',
            'Jul'=>'កក្កដា','Aug'=>'សីហា','Sep'=>'កញ្ញា','Oct'=>'តុលា','Nov'=>'វិច្ឆិកា','Dec'=>'ធ្នូ',
            'Monday'=>'ច័ន្ទ','Tuesday'=>'អង្គារ','Wednesday'=>'ពុធ','Thursday'=>'ព្រហស្បតិ៍',
            'Friday'=>'សុក្រ','Saturday'=>'សៅរ៍','Sunday'=>'អាទិត្យ',
            'Mon'=>'ច័ន្ទ','Tue'=>'អង្គារ','Wed'=>'ពុធ','Thu'=>'ព្រហស្បតិ៍','Fri'=>'សុក្រ','Sat'=>'សៅរ៍','Sun'=>'អាទិត្យ',
            'am'=>'ព្រឹក','pm'=>'ល្ងាច','AM'=>'ព្រឹក','PM'=>'ល្ងាច',
        ]);
        return km_num($out);
    }
}
