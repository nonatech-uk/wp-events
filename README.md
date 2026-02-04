# Parish Events

A WordPress plugin for managing parish events with calendar display, recurring events, iCal feed, and Meilisearch integration.

## Features

- **Custom Post Type** - Events managed in WordPress admin
- **Event Details** - Date, time, location, and external URL
- **Document Links** - Attach related documents (agendas, minutes, etc.)
- **Recurring Events** - Weekly, monthly, or yearly recurrence
- **Multiple Views** - List view, calendar view, and next event widget
- **iCal Feed** - Subscribe to events in Outlook, Google Calendar, Apple Calendar
- **Meilisearch Integration** - Events indexed for site-wide search

## Installation

1. Upload the `parish-events` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress Plugins menu
3. Go to **Settings > Permalinks** and click Save (flushes rewrite rules for iCal feed)
4. Configure Meilisearch settings in the Parish Search plugin (shared configuration)

## Usage

### Adding Events

1. Go to **Events > Add New** in WordPress admin
2. Enter the event title and description
3. Fill in event details:
   - **Event Date** (required)
   - **Start Time** / **End Time**
   - **End Date** (for multi-day events)
   - **Location**
   - **More Info URL**
4. Add related documents (optional):
   - Click **+ Add Document**
   - Enter document title and URL
   - Repeat for multiple documents
5. Set recurrence (optional):
   - Select frequency: Weekly, Monthly, or Yearly
   - Set end date for recurrence

### Shortcodes

#### Event List

```
[parish_events]                  # 10 upcoming events
[parish_events limit="5"]        # 5 upcoming events
[parish_events show="past"]      # Past events
[parish_events show="all"]       # All events
```

| Attribute | Default | Description |
|-----------|---------|-------------|
| `limit` | 10 | Maximum events to display |
| `show` | `upcoming` | `upcoming`, `past`, or `all` |

#### Calendar View

```
[parish_events_calendar]                    # Current month
[parish_events_calendar month="6" year="2026"]  # Specific month
```

#### Next Event Widget

```
[next_parish_event]
```

Displays the next upcoming event with full details including:
- Date and time
- Location
- Description
- Related documents
- Link to more info

### iCal Feed

Subscribe to the parish calendar at:

```
https://yoursite.com/events/feed.ics
```

Or with query parameter:
```
https://yoursite.com/?parish_events_ical=1
```

The feed includes:
- All published events
- Recurring events with RRULE
- Europe/London timezone

### Example Page Layout

```html
<h2>Next Council Meeting</h2>
[next_parish_event]

<h2>Upcoming Events</h2>
[parish_events limit="5"]

<h2>Event Calendar</h2>
[parish_events_calendar]

<p><a href="/events/feed.ics">Subscribe to calendar</a></p>
```

## Recurring Events

When you set an event to repeat:

1. Select frequency (Weekly, Monthly, Yearly)
2. Set an end date (when to stop generating occurrences)
3. The original event date is the first occurrence
4. Subsequent occurrences are generated automatically

**Notes:**
- Monthly recurrence uses the same day of month (e.g., 15th)
- If a month doesn't have that day, it uses the last day
- Maximum 100 occurrences are generated (or 2 years if no end date)

## Meilisearch Integration

Events are automatically indexed to Meilisearch when published. The plugin reuses settings from the Parish Search plugin.

Each event is indexed with:
- `id` - `event_{post_id}`
- `type` - `event`
- `title` - Event title
- `content` - Event description (text only)
- `date_display` - Formatted date (e.g., "15 January 2026")
- `date_sortable` - YYYYMMDD integer for filtering
- `year` - Year for filtering
- `event_time` - Start time
- `event_location` - Location

Search for events using `type:event` in the search grammar.

## Admin Columns

The Events list in admin shows:
- Event Date
- Location
- Recurrence status

Events are sorted by event date (most recent first).

## Styling

The plugin includes default CSS. Override styles by targeting:

### List View
- `.parish-events-list` - List container
- `.parish-event-item` - Individual event
- `.parish-event-date-badge` - Date badge (day/month/year)
- `.parish-event-content` - Event details
- `.parish-event-documents` - Document links

### Calendar View
- `.parish-events-calendar` - Calendar wrapper
- `.parish-calendar-header` - Month navigation
- `.parish-calendar-grid` - Calendar grid
- `.parish-calendar-day` - Day cell
- `.parish-calendar-day-event` - Event on calendar

### Next Event
- `.parish-next-event` - Widget container
- `.parish-next-event-date` - Date display
- `.parish-next-event-documents` - Document list

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Parish Search plugin (for Meilisearch settings)

## License

GPL v2 or later
