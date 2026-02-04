<?php
/**
 * Archive Template for Parish Events
 * Shows upcoming events with full details and pagination
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Get the Parish_Events instance
global $parish_events_plugin;
$parish_events = $parish_events_plugin;

$per_page = 10;
$show = 'upcoming';
$total_events = $parish_events->get_total_events($show);
$use_pagination = ($total_events > $per_page);

$events = $parish_events->get_events(array(
    'limit' => $per_page,
    'show'  => $show,
));

$instance_id = 'parish-events-archive';
?>

<main id="main" class="site-main">
    <div class="parish-events-archive-wrapper" style="max-width: 800px; margin: 0 auto; padding: 20px;">
        <header class="page-header">
            <h1 class="page-title">Events</h1>
        </header>

        <div class="parish-events-container"
             id="<?php echo esc_attr($instance_id); ?>"
             data-per-page="<?php echo esc_attr($per_page); ?>"
             data-total="<?php echo esc_attr($total_events); ?>"
             data-show="<?php echo esc_attr($show); ?>">

            <?php if ($use_pagination): ?>
            <div class="parish-events-controls">
                <div class="parish-events-per-page">
                    <label>Show:</label>
                    <select class="parish-events-per-page-select">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="parish-events-page-info">
                    Showing <span class="parish-events-showing-start">1</span>–<span class="parish-events-showing-end"><?php echo min($per_page, $total_events); ?></span> of <span class="parish-events-total"><?php echo $total_events; ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="parish-events-list">
                <?php if (!empty($events)): ?>
                    <?php foreach ($events as $event): ?>
                        <?php echo $parish_events->render_event_item($event); ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="parish-events-no-results">No upcoming events.</p>
                <?php endif; ?>
            </div>

            <?php if ($use_pagination):
                $total_pages = ceil($total_events / $per_page);
            ?>
            <div class="parish-events-pagination">
                <button class="parish-events-page-btn parish-events-prev" disabled>&laquo; Previous</button>
                <span class="parish-events-page-numbers">
                    <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
                        <button class="parish-events-page-num <?php echo $i === 1 ? 'active' : ''; ?>" data-page="<?php echo $i; ?>"><?php echo $i; ?></button>
                    <?php endfor; ?>
                </span>
                <button class="parish-events-page-btn parish-events-next" <?php echo $total_pages <= 1 ? 'disabled' : ''; ?>>Next &raquo;</button>
            </div>
            <?php endif; ?>

            <div class="parish-events-subscribe">
                <a href="<?php echo home_url('/events/feed.ics'); ?>" class="parish-events-subscribe-link">
                    <svg viewBox="0 0 24 24" width="16" height="16"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/></svg>
                    Subscribe to calendar
                </a>
            </div>
        </div>
    </div>
</main>

<?php
get_footer();
