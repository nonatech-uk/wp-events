<?php
/**
 * Single Event Template
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()): the_post();
    $post_id = get_the_ID();
    $event_date = get_post_meta($post_id, '_parish_event_date', true);
    $event_time = get_post_meta($post_id, '_parish_event_time', true);
    $event_end_date = get_post_meta($post_id, '_parish_event_end_date', true);
    $event_end_time = get_post_meta($post_id, '_parish_event_end_time', true);
    $event_location = get_post_meta($post_id, '_parish_event_location', true);
    $event_url = get_post_meta($post_id, '_parish_event_url', true);
    $manual_documents = get_post_meta($post_id, '_parish_event_documents', true);

    // Get auto-linked documents
    global $parish_events_plugin;
    $auto_result = $parish_events_plugin->get_auto_documents($post_id);
    $auto_documents = $auto_result['documents'];
    $auto_doc_status = $auto_result['status_message'];

    // Merge documents (auto first, then manual)
    $documents = array_merge($auto_documents, is_array($manual_documents) ? $manual_documents : array());

    // Check if event is cancelled
    $is_cancelled = get_post_meta($post_id, '_parish_event_cancelled', true);
    $display_title = $is_cancelled ? 'Cancelled: ' . get_the_title() : get_the_title();
?>

<main id="main" class="site-main">
    <article id="post-<?php the_ID(); ?>" <?php post_class('parish-event-single' . ($is_cancelled ? ' parish-event-cancelled' : '')); ?>>
        <header class="entry-header">
            <h1 class="entry-title"><?php echo esc_html($display_title); ?></h1>
        </header>

        <div class="parish-event-details">
            <?php if ($event_date): ?>
                <div class="parish-event-detail parish-event-date-time">
                    <svg class="parish-event-icon" viewBox="0 0 24 24" width="20" height="20"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/></svg>
                    <span>
                        <?php
                        echo date('l, j F Y', strtotime($event_date));
                        if ($event_end_date && $event_end_date !== $event_date) {
                            echo ' - ' . date('l, j F Y', strtotime($event_end_date));
                        }
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($event_time): ?>
                <div class="parish-event-detail parish-event-time">
                    <svg class="parish-event-icon" viewBox="0 0 24 24" width="20" height="20"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 6v6l4 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <span>
                        <?php
                        echo date('g:ia', strtotime($event_time));
                        if ($event_end_time) {
                            echo ' – ' . date('g:ia', strtotime($event_end_time));
                        }
                        ?>
                    </span>
                </div>
            <?php endif; ?>

            <?php if ($event_location): ?>
                <div class="parish-event-detail parish-event-location">
                    <svg class="parish-event-icon" viewBox="0 0 24 24" width="20" height="20"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="9" r="2.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                    <span><?php echo esc_html($event_location); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($event_url): ?>
                <div class="parish-event-detail parish-event-url">
                    <svg class="parish-event-icon" viewBox="0 0 24 24" width="20" height="20"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="15 3 21 3 21 9" fill="none" stroke="currentColor" stroke-width="2"/><line x1="10" y1="14" x2="21" y2="3" stroke="currentColor" stroke-width="2"/></svg>
                    <a href="<?php echo esc_url($event_url); ?>" target="_blank" rel="noopener">More information</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="entry-content">
            <?php the_content(); ?>
        </div>

        <?php if (!empty($documents) && is_array($documents)): ?>
            <div class="parish-event-documents">
                <h3>Documents</h3>
                <ul>
                    <?php foreach ($documents as $doc): ?>
                        <?php if (!empty($doc['url'])): ?>
                            <li>
                                <a href="<?php echo esc_url($doc['url']); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html($doc['title'] ?: 'Download'); ?>
                                </a>
                                <?php if (!empty($doc['is_annex'])): ?>
                                    <span class="parish-event-annex-badge">Annex</span>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php elseif (!empty($auto_doc_status)): ?>
            <div class="parish-event-documents">
                <h3>Documents</h3>
                <p class="parish-event-doc-status"><?php echo esc_html($auto_doc_status); ?></p>
            </div>
        <?php endif; ?>

        <nav class="parish-event-nav">
            <a href="<?php echo get_post_type_archive_link('parish_event'); ?>">&larr; Back to all events</a>
        </nav>
    </article>
</main>

<?php
endwhile;

get_footer();
