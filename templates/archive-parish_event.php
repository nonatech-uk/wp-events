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

        <?php Parish_Events::render_subscribe_block(); ?>

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
        </div>
    </div>
</main>

<?php
get_footer();
