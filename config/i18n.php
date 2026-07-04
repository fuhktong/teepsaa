<?php
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
        if (($_SESSION['lang'] ?? 'km') !== 'km') return $out;

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
