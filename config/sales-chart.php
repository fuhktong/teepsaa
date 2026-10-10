<?php
// Sales over time, for the vendor dashboard — the app's (api/v1/dashboard.php)
// and the website's (analytics/index.php), so the two draw the same bars.
//
// The last 30 days, and the last 12 weeks (Monday to Sunday), each with what
// the vendor is paid and how many orders. Unlike the dashboard's sales figures,
// which count only delivered and completed orders, this counts every order that was
// paid and has not been cancelled or refunded — a chart that waits for
// delivery would always show the last few days as empty. An order whose
// refund is still being decided is left out until it is decided.
// Every day and week is sent, zeros included, so the chart has no gaps.
// Dates are Phnom Penh's (db.php sets the connection's time zone).

if (!function_exists('sales_chart')) {
    /** ['days' => [{date, payout, orders}] ×30, 'weeks' => [{start, payout, orders}] ×12] */
    function sales_chart(PDO $pdo, int $businessId): array {
        $sold = "('paid', 'dispatched', 'delivered', 'completed', 'refund_rejected')";

        $today = new DateTimeImmutable('today');
        $from  = $today->modify('-29 days');
        $stmtDays = $pdo->prepare("
            SELECT DATE(created_at) AS d, SUM(vendor_payout) AS payout, COUNT(*) AS orders
              FROM orders
             WHERE business_id = ? AND status IN $sold AND created_at >= ?
             GROUP BY DATE(created_at)
        ");
        $stmtDays->execute([$businessId, $from->format('Y-m-d')]);
        $byDay = [];
        foreach ($stmtDays->fetchAll() as $r) $byDay[$r['d']] = $r;

        $days = [];
        for ($d = $from; $d <= $today; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $days[] = [
                'date'   => $key,
                'payout' => round((float)($byDay[$key]['payout'] ?? 0), 2),
                'orders' => (int)($byDay[$key]['orders'] ?? 0),
            ];
        }

        $thisMonday = $today->modify('monday this week');
        $firstWeek  = $thisMonday->modify('-11 weeks');
        // WEEKDAY() is 0 for Monday, so this is the Monday each order's week began.
        $stmtWeeks = $pdo->prepare("
            SELECT DATE(DATE_SUB(created_at, INTERVAL WEEKDAY(created_at) DAY)) AS w,
                   SUM(vendor_payout) AS payout, COUNT(*) AS orders
              FROM orders
             WHERE business_id = ? AND status IN $sold AND created_at >= ?
             GROUP BY w
        ");
        $stmtWeeks->execute([$businessId, $firstWeek->format('Y-m-d')]);
        $byWeek = [];
        foreach ($stmtWeeks->fetchAll() as $r) $byWeek[$r['w']] = $r;

        $weeks = [];
        for ($w = $firstWeek; $w <= $thisMonday; $w = $w->modify('+1 week')) {
            $key = $w->format('Y-m-d');
            $weeks[] = [
                'start'  => $key,
                'payout' => round((float)($byWeek[$key]['payout'] ?? 0), 2),
                'orders' => (int)($byWeek[$key]['orders'] ?? 0),
            ];
        }

        return ['days' => $days, 'weeks' => $weeks];
    }
}
