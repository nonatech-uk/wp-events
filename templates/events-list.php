<?php
/**
 * Events List Template
 *
 * Variables available:
 * - $events: Array of event data
 * - $atts: Shortcode attributes
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="parish-events-list">
    <?php if (!empty($events)): ?>
        <?php foreach ($events as $event): ?>
            <article class="parish-event-item">
                <div class="parish-event-date-badge">
                    <span class="parish-event-day"><?php echo date('d', strtotime($event['date'])); ?></span>
                    <span class="parish-event-month"><?php echo date('M', strtotime($event['date'])); ?></span>
                    <span class="parish-event-year"><?php echo date('Y', strtotime($event['date'])); ?></span>
                </div>
                <div class="parish-event-content">
                    <h3 class="parish-event-title">
                        <a href="<?php echo esc_url($event['permalink']); ?>"><?php echo esc_html($event['title']); ?></a>
                    </h3>
                    <div class="parish-event-meta">
                        <?php if (!empty($event['time'])): ?>
                            <span class="parish-event-time">
                                <svg class="parish-event-icon" viewBox="0 0 24 24" width="16" height="16"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 6v6l4 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                <?php echo esc_html(date('g:ia', strtotime($event['time']))); ?><?php if (!empty($event['end_time'])): ?> – <?php echo esc_html(date('g:ia', strtotime($event['end_time']))); ?><?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($event['location'])): ?>
                            <span class="parish-event-location">
                                <svg class="parish-event-icon" viewBox="0 0 24 24" width="16" height="16"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="9" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                                <?php echo esc_html($event['location']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($event['excerpt'])): ?>
                        <div class="parish-event-excerpt"><?php echo wp_kses_post($event['excerpt']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($event['documents'])): ?>
                        <div class="parish-event-documents">
                            <strong>Documents:</strong>
                            <?php foreach ($event['documents'] as $doc): ?>
                                <a href="<?php echo esc_url($doc['url']); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html($doc['title']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($event['url'])): ?>
                        <a href="<?php echo esc_url($event['url']); ?>" class="parish-event-link" target="_blank" rel="noopener">
                            More info &rarr;
                        </a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="parish-events-no-results">
            <?php echo ($atts['show'] === 'upcoming') ? 'No upcoming events.' : 'No events found.'; ?>
        </p>
    <?php endif; ?>
</div>

<div class="parish-events-subscribe">
    <a href="<?php echo home_url('/events/feed.ics'); ?>" class="parish-events-subscribe-link">
        <svg viewBox="0 0 24 24" width="16" height="16"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/></svg>
        Subscribe to calendar
    </a>
</div>
