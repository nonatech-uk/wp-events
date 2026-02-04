<?php
/**
 * Events Calendar Template
 *
 * Variables available:
 * - $month: Current month (1-12)
 * - $year: Current year
 */

if (!defined('ABSPATH')) {
    exit;
}

$first_day = mktime(0, 0, 0, $month, 1, $year);
$days_in_month = date('t', $first_day);
$start_day = date('N', $first_day); // 1 = Monday, 7 = Sunday
$month_name = date('F Y', $first_day);

$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}
?>

<div class="parish-events-calendar" data-month="<?php echo $month; ?>" data-year="<?php echo $year; ?>">
    <div class="parish-calendar-header">
        <button class="parish-calendar-nav parish-calendar-prev" data-month="<?php echo $prev_month; ?>" data-year="<?php echo $prev_year; ?>">
            &larr; Previous
        </button>
        <h3 class="parish-calendar-title"><?php echo esc_html($month_name); ?></h3>
        <button class="parish-calendar-nav parish-calendar-next" data-month="<?php echo $next_month; ?>" data-year="<?php echo $next_year; ?>">
            Next &rarr;
        </button>
    </div>

    <div class="parish-calendar-grid">
        <div class="parish-calendar-weekdays">
            <span>Mon</span>
            <span>Tue</span>
            <span>Wed</span>
            <span>Thu</span>
            <span>Fri</span>
            <span>Sat</span>
            <span>Sun</span>
        </div>

        <div class="parish-calendar-days">
            <?php
            // Empty cells for days before the 1st
            for ($i = 1; $i < $start_day; $i++) {
                echo '<div class="parish-calendar-day parish-calendar-day-empty"></div>';
            }

            // Days of the month
            $today = date('Y-m-d');
            for ($day = 1; $day <= $days_in_month; $day++) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $is_today = ($date === $today);
                $classes = 'parish-calendar-day';
                if ($is_today) {
                    $classes .= ' parish-calendar-day-today';
                }
                ?>
                <div class="<?php echo $classes; ?>" data-date="<?php echo $date; ?>">
                    <span class="parish-calendar-day-number"><?php echo $day; ?></span>
                    <div class="parish-calendar-day-events"></div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>

    <div class="parish-calendar-loading" style="display: none;">Loading...</div>
</div>

<div class="parish-events-subscribe" style="margin-top: 20px;">
    <a href="<?php echo home_url('/events/feed.ics'); ?>" class="parish-events-subscribe-link">
        <svg viewBox="0 0 24 24" width="16" height="16"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/></svg>
        Subscribe to calendar
    </a>
</div>
