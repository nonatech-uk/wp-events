<?php
/**
 * Next Event Template
 *
 * Variables available:
 * - $event: Event data array
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_cancelled = !empty($event['cancelled']);
$display_title = $is_cancelled ? 'Cancelled: ' . $event['title'] : $event['title'];
$wrapper_class = 'parish-next-event' . ($is_cancelled ? ' parish-event-cancelled' : '');
?>

<div class="<?php echo esc_attr($wrapper_class); ?>">
    <div class="parish-next-event-header">
        <span class="parish-next-event-label">Next Event</span>
    </div>

    <div class="parish-next-event-date">
        <span class="parish-next-event-day"><?php echo date('l', strtotime($event['date'])); ?></span>
        <span class="parish-next-event-full-date"><?php echo date('j F Y', strtotime($event['date'])); ?></span>
        <?php if (!empty($event['time'])): ?>
            <span class="parish-next-event-time">
                <?php if (!empty($event['end_time'])): ?>
                    <?php echo date('g:ia', strtotime($event['time'])); ?> – <?php echo date('g:ia', strtotime($event['end_time'])); ?>
                <?php else: ?>
                    at <?php echo date('g:ia', strtotime($event['time'])); ?>
                <?php endif; ?>
            </span>
        <?php endif; ?>
    </div>

    <h3 class="parish-next-event-title">
        <a href="<?php echo esc_url($event['permalink']); ?>"><?php echo esc_html($display_title); ?></a>
    </h3>

    <?php if (!empty($event['location'])): ?>
        <div class="parish-next-event-location">
            <svg class="parish-event-icon" viewBox="0 0 24 24" width="18" height="18"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="9" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>
            <?php echo esc_html($event['location']); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($event['content']) && $summary_length !== 0): ?>
        <div class="parish-next-event-description">
            <?php
            $content = wp_strip_all_tags($event['content']);
            if ($summary_length > 0 && mb_strlen($content) > $summary_length) {
                echo esc_html(mb_substr($content, 0, $summary_length)) . '…';
            } else {
                echo wp_kses_post($event['content']);
            }
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($event['documents'])): ?>
        <div class="parish-next-event-documents">
            <h4>Related Documents</h4>
            <ul>
                <?php foreach ($event['documents'] as $doc): ?>
                    <li>
                        <a href="<?php echo esc_url($doc['url']); ?>" target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" width="16" height="16"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="14 2 14 8 20 8" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                            <?php echo esc_html($doc['title']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($event['url'])): ?>
        <div class="parish-next-event-actions">
            <a href="<?php echo esc_url($event['url']); ?>" class="parish-next-event-button" target="_blank" rel="noopener">
                More Information &rarr;
            </a>
        </div>
    <?php endif; ?>

    <div class="parish-next-event-footer">
        <a href="<?php echo esc_url($event['permalink']); ?>">View event details</a>
        <span class="parish-next-event-separator">|</span>
        <a href="<?php echo home_url('/events/'); ?>">See all events</a>
    </div>
</div>
