/**
 * Parish Events JavaScript
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initCalendars();
        initEventsPagination();
    });

    function initCalendars() {
        var calendars = document.querySelectorAll('.parish-events-calendar');

        calendars.forEach(function(calendar) {
            var month = parseInt(calendar.dataset.month);
            var year = parseInt(calendar.dataset.year);

            // Load events for current month
            loadEventsForMonth(calendar, month, year);

            // Navigation buttons
            var prevBtn = calendar.querySelector('.parish-calendar-prev');
            var nextBtn = calendar.querySelector('.parish-calendar-next');

            if (prevBtn) {
                prevBtn.addEventListener('click', function() {
                    navigateMonth(calendar, parseInt(this.dataset.month), parseInt(this.dataset.year));
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    navigateMonth(calendar, parseInt(this.dataset.month), parseInt(this.dataset.year));
                });
            }
        });
    }

    function loadEventsForMonth(calendar, month, year) {
        var loading = calendar.querySelector('.parish-calendar-loading');
        if (loading) {
            loading.style.display = 'block';
        }

        var formData = new FormData();
        formData.append('action', 'parish_events_get_month');
        formData.append('month', month);
        formData.append('year', year);

        fetch(parishEvents.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(result) {
            if (loading) {
                loading.style.display = 'none';
            }

            if (result.success && result.data.events) {
                displayEventsOnCalendar(calendar, result.data.events);
            }
        })
        .catch(function(error) {
            if (loading) {
                loading.style.display = 'none';
            }
            console.error('Failed to load events:', error);
        });
    }

    function displayEventsOnCalendar(calendar, events) {
        // Clear existing events
        var eventContainers = calendar.querySelectorAll('.parish-calendar-day-events');
        eventContainers.forEach(function(container) {
            container.innerHTML = '';
        });

        // Group events by date
        var eventsByDate = {};
        events.forEach(function(event) {
            if (!eventsByDate[event.date]) {
                eventsByDate[event.date] = [];
            }
            eventsByDate[event.date].push(event);
        });

        // Display events on calendar
        for (var date in eventsByDate) {
            var dayCell = calendar.querySelector('.parish-calendar-day[data-date="' + date + '"]');
            if (dayCell) {
                var container = dayCell.querySelector('.parish-calendar-day-events');
                eventsByDate[date].forEach(function(event) {
                    var link = document.createElement('a');
                    link.href = event.permalink;
                    link.className = 'parish-calendar-day-event';
                    if (event.cancelled) {
                        link.className += ' parish-event-cancelled';
                        link.textContent = 'Cancelled: ' + event.title;
                        link.title = 'Cancelled: ' + event.title;
                    } else {
                        link.textContent = event.title;
                        link.title = event.title;
                    }
                    container.appendChild(link);
                });
            }
        }
    }

    function navigateMonth(calendar, month, year) {
        // Update calendar header
        var title = calendar.querySelector('.parish-calendar-title');
        var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                          'July', 'August', 'September', 'October', 'November', 'December'];
        title.textContent = monthNames[month - 1] + ' ' + year;

        // Update navigation buttons
        var prevMonth = month - 1;
        var prevYear = year;
        if (prevMonth < 1) {
            prevMonth = 12;
            prevYear--;
        }

        var nextMonth = month + 1;
        var nextYear = year;
        if (nextMonth > 12) {
            nextMonth = 1;
            nextYear++;
        }

        var prevBtn = calendar.querySelector('.parish-calendar-prev');
        var nextBtn = calendar.querySelector('.parish-calendar-next');
        prevBtn.dataset.month = prevMonth;
        prevBtn.dataset.year = prevYear;
        nextBtn.dataset.month = nextMonth;
        nextBtn.dataset.year = nextYear;

        // Rebuild calendar grid
        rebuildCalendarGrid(calendar, month, year);

        // Load events
        loadEventsForMonth(calendar, month, year);
    }

    function rebuildCalendarGrid(calendar, month, year) {
        var daysContainer = calendar.querySelector('.parish-calendar-days');
        daysContainer.innerHTML = '';

        var firstDay = new Date(year, month - 1, 1);
        var daysInMonth = new Date(year, month, 0).getDate();
        var startDay = firstDay.getDay();
        // Convert to Monday-start (0 = Monday, 6 = Sunday)
        startDay = startDay === 0 ? 6 : startDay - 1;

        var today = new Date().toISOString().split('T')[0];

        // Empty cells before first day
        for (var i = 0; i < startDay; i++) {
            var emptyCell = document.createElement('div');
            emptyCell.className = 'parish-calendar-day parish-calendar-day-empty';
            daysContainer.appendChild(emptyCell);
        }

        // Days of month
        for (var day = 1; day <= daysInMonth; day++) {
            var date = year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            var dayCell = document.createElement('div');
            dayCell.className = 'parish-calendar-day';
            if (date === today) {
                dayCell.className += ' parish-calendar-day-today';
            }
            dayCell.dataset.date = date;

            var dayNumber = document.createElement('span');
            dayNumber.className = 'parish-calendar-day-number';
            dayNumber.textContent = day;
            dayCell.appendChild(dayNumber);

            var eventsContainer = document.createElement('div');
            eventsContainer.className = 'parish-calendar-day-events';
            dayCell.appendChild(eventsContainer);

            daysContainer.appendChild(dayCell);
        }
    }

    /**
     * Events List Pagination
     */
    function initEventsPagination() {
        var containers = document.querySelectorAll('.parish-events-container');
        containers.forEach(function(container) {
            initPaginationContainer(container);
        });
    }

    function initPaginationContainer(container) {
        var perPage = parseInt(container.dataset.perPage) || 10;
        var total = parseInt(container.dataset.total) || 0;
        var show = container.dataset.show || 'upcoming';
        var currentPage = 1;

        var list = container.querySelector('.parish-events-list');
        var perPageSelect = container.querySelector('.parish-events-per-page-select');
        var prevBtn = container.querySelector('.parish-events-prev');
        var nextBtn = container.querySelector('.parish-events-next');
        var pageNumbers = container.querySelector('.parish-events-page-numbers');
        var pagination = container.querySelector('.parish-events-pagination');

        if (!list || total <= perPage) {
            return;
        }

        function loadPage(page, newPerPage) {
            if (newPerPage !== undefined) {
                perPage = newPerPage;
            }

            container.classList.add('loading');

            var formData = new FormData();
            formData.append('action', 'parish_events_paginate');
            formData.append('nonce', parishEvents.paginateNonce);
            formData.append('page', page);
            formData.append('per_page', perPage === 0 ? -1 : perPage);
            formData.append('show', show);

            fetch(parishEvents.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                container.classList.remove('loading');

                if (data.success) {
                    currentPage = data.data.page;
                    list.innerHTML = data.data.html;
                    total = data.data.total;
                    updateControls();
                }
            })
            .catch(function(error) {
                container.classList.remove('loading');
                console.error('Parish Events pagination error:', error);
            });
        }

        function updateControls() {
            var totalPages = perPage === 0 ? 1 : Math.ceil(total / perPage);
            var start = perPage === 0 ? 1 : (currentPage - 1) * perPage + 1;
            var end = perPage === 0 ? total : Math.min(currentPage * perPage, total);

            // Update info text
            var startEl = container.querySelector('.parish-events-showing-start');
            var endEl = container.querySelector('.parish-events-showing-end');
            var totalEl = container.querySelector('.parish-events-total');
            if (startEl) startEl.textContent = start;
            if (endEl) endEl.textContent = end;
            if (totalEl) totalEl.textContent = total;

            // Update prev/next buttons
            if (prevBtn) {
                prevBtn.disabled = currentPage <= 1 || perPage === 0;
            }
            if (nextBtn) {
                nextBtn.disabled = currentPage >= totalPages || perPage === 0;
            }

            // Update page numbers
            if (pageNumbers) {
                pageNumbers.innerHTML = '';
                if (perPage > 0 && totalPages > 1) {
                    for (var i = 1; i <= totalPages; i++) {
                        var btn = document.createElement('button');
                        btn.className = 'parish-events-page-num' + (i === currentPage ? ' active' : '');
                        btn.textContent = i;
                        btn.dataset.page = i;
                        btn.addEventListener('click', function() {
                            loadPage(parseInt(this.dataset.page));
                        });
                        pageNumbers.appendChild(btn);
                    }
                }
            }

            // Show/hide pagination when showing all
            if (pagination) {
                pagination.style.display = (perPage === 0 || totalPages <= 1) ? 'none' : '';
            }
        }

        // Per-page select handler
        if (perPageSelect) {
            perPageSelect.addEventListener('change', function() {
                var value = this.value;
                var newPerPage = value === 'all' ? 0 : parseInt(value);
                currentPage = 1;
                loadPage(1, newPerPage);
            });
        }

        // Prev/Next button handlers
        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                if (currentPage > 1) {
                    loadPage(currentPage - 1);
                }
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                var totalPages = perPage === 0 ? 1 : Math.ceil(total / perPage);
                if (currentPage < totalPages) {
                    loadPage(currentPage + 1);
                }
            });
        }

        // Page number click handlers (for initial page numbers)
        if (pageNumbers) {
            pageNumbers.querySelectorAll('.parish-events-page-num').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    loadPage(parseInt(this.dataset.page));
                });
            });
        }
    }
})();
