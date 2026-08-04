(function() {
    let currentMonth = new Date().getMonth() + 1;
    let currentYear = new Date().getFullYear();
    let selectedDate = null;
    let activeFilter = 'all';
    let allEvents = [];
    let modalType = 'pm';
    let modalPriority = 'medium';

    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    function init() {
        const todayLabel = document.getElementById('calTodayLabel');
        if (todayLabel) todayLabel.textContent = 'Today — ' + formatDate(new Date());
        populateSelects();
        loadEvents();
        initModal();
    }

    function populateSelects() {
        const monthSelect = document.getElementById('calMonthSelect');
        const yearSelect = document.getElementById('calYearSelect');
        if (monthSelect) {
            monthSelect.innerHTML = '';
            monthNames.forEach((name, i) => {
                const opt = document.createElement('option');
                opt.value = i + 1;
                opt.textContent = name;
                monthSelect.appendChild(opt);
            });
            monthSelect.value = currentMonth;
        }
        if (yearSelect) {
            yearSelect.innerHTML = '';
            const currentYear = new Date().getFullYear();
            for (let y = currentYear - 2; y <= currentYear + 3; y++) {
                const opt = document.createElement('option');
                opt.value = y;
                opt.textContent = y;
                yearSelect.appendChild(opt);
            }
            yearSelect.value = currentYear;
        }
    }

    function getEventsUrl() {
        return document.getElementById('calEventsUrl')?.value || '';
    }

    function loadEvents() {
        const tbody = document.getElementById('calGridBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="cal-loading"><i class="fa-solid fa-circle-notch"></i></td></tr>';

        const params = new URLSearchParams({ month: currentMonth, year: currentYear, filter: activeFilter });
        fetch(getEventsUrl() + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            allEvents = data.events || [];
            updateSummary(data.summary || {});
            renderCalendar();
            renderTasksForDate(selectedDate);
            renderUpcoming();
            renderMonthlySummary(data.summary || {});
        })
        .catch(err => {
            console.error('Calendar load error:', err);
            if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="cal-empty"><i class="fa-solid fa-triangle-exclamation"></i><p>Failed to load calendar events.</p></td></tr>';
        });
    }

    function updateSummary(s) {
        const pm = document.getElementById('calPmCount');
        const ict = document.getElementById('calIctCount');
        const done = document.getElementById('calDoneCount');
        const overdue = document.getElementById('calOverdueCount');
        if (pm) pm.textContent = (s.pm || 0) + ' tasks';
        if (ict) ict.textContent = (s.ict || 0) + ' tasks';
        if (done) done.textContent = s.done || 0;
        if (overdue) overdue.textContent = s.overdue || 0;
    }

    function renderMonthlySummary(s) {
        const pm = document.getElementById('calSummaryPm');
        const ict = document.getElementById('calSummaryIct');
        const done = document.getElementById('calSummaryDone');
        const overdue = document.getElementById('calSummaryOverdue');
        if (pm) pm.textContent = s.pm || 0;
        if (ict) ict.textContent = s.ict || 0;
        if (done) done.textContent = s.done || 0;
        if (overdue) overdue.textContent = s.overdue || 0;
    }

    function renderCalendar() {
        const monthLabel = document.getElementById('calMonthLabel');
        if (monthLabel) monthLabel.textContent = monthNames[currentMonth - 1] + ' ' + currentYear;

        const tbody = document.getElementById('calGridBody');
        if (!tbody) return;
        tbody.innerHTML = '';

        const firstDay = new Date(currentYear, currentMonth - 1, 1).getDay();
        const daysInMonth = new Date(currentYear, currentMonth, 0).getDate();
        const todayStr = getTodayDateStr();

        let row = document.createElement('tr');

        for (let i = 0; i < firstDay; i++) {
            row.appendChild(makeCell('', 'cal-other-month'));
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = currentYear + '-' + String(currentMonth).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            const colIndex = (firstDay + d - 1) % 7;
            const isWeekend = colIndex === 0 || colIndex === 6;
            const cell = makeCell(d, isWeekend ? 'cal-weekend' : '', dateStr);

            if (dateStr === todayStr) cell.classList.add('cal-today');
            if (dateStr === selectedDate) cell.classList.add('cal-selected');

            const dayEvents = allEvents.filter(e => e.date === dateStr);
            dayEvents.slice(0, 3).forEach(e => {
                const chip = document.createElement('button');
                let chipClass = 'cal-chip cal-chip-' + e.event_type;
                const statusLower = (e.status || '').toLowerCase().replace(/\s+/g, '');
                if (statusLower === 'overdue') chipClass += ' cal-chip-overdue';
                if (statusLower === 'completed') chipClass += ' cal-chip-completed';
                if (statusLower === 'cancelled') chipClass += ' cal-chip-cancelled';
                chip.className = chipClass;

                // Build compact chip text — always prefer display_number (short) for work orders,
                // fall back to title, and cap length to prevent breaking the calendar cell.
                let chipText = e.display_number || e.title || '';
                if (chipText.length > 22) chipText = chipText.substring(0, 22) + '…';
                chip.textContent = (e.event_type === 'pm' ? 'PM' : 'ICT') + ' — ' + chipText;
                chip.title = (e.display_number || e.title || '') + ' (' + formatDate(e.date) + ')';
                chip.onclick = function(ev) { ev.stopPropagation(); showEventDetail(e); };
                cell.appendChild(chip);
            });

            if (dayEvents.length > 3) {
                const more = document.createElement('span');
                more.className = 'cal-more';
                more.textContent = '+' + (dayEvents.length - 3) + ' more';
                cell.appendChild(more);
            }

            cell.onclick = function() { selectDate(dateStr); };
            row.appendChild(cell);

            if (row.children.length === 7) {
                tbody.appendChild(row);
                row = document.createElement('tr');
            }
        }

        while (row.children.length > 0 && row.children.length < 7) {
            row.appendChild(makeCell('', 'cal-other-month'));
        }
        if (row.children.length > 0) tbody.appendChild(row);
    }

    function makeCell(day, extraClass, dateStr) {
        const td = document.createElement('td');
        td.className = 'cal-day ' + extraClass;
        if (dateStr) td.dataset.date = dateStr;
        if (day) {
            const wrap = document.createElement('div');
            wrap.className = 'cal-day-num-wrap';

            const label = document.createElement('span');
            label.className = 'cal-day-num';
            label.textContent = day;
            wrap.appendChild(label);

            // Hover add button
            const addBtn = document.createElement('button');
            addBtn.className = 'cal-day-add';
            addBtn.innerHTML = '<i class="fa-solid fa-plus"></i>';
            addBtn.title = 'Add task';
            addBtn.onclick = function(ev) {
                ev.stopPropagation();
                openModal('pm', dateStr);
            };
            wrap.appendChild(addBtn);

            td.appendChild(wrap);
        }
        return td;
    }

    function selectDate(dateStr) {
        selectedDate = dateStr;
        document.querySelectorAll('.cal-day.cal-selected').forEach(c => c.classList.remove('cal-selected'));
        const cell = document.querySelector('.cal-day[data-date="' + dateStr + '"]');
        if (cell) cell.classList.add('cal-selected');
        renderTasksForDate(dateStr);
    }

    function renderTasksForDate(dateStr) {
        const body = document.getElementById('calTasksBody');
        const label = document.getElementById('calTasksDate');
        const count = document.getElementById('calTasksCount');
        if (!body) return;

        if (!dateStr) {
            body.innerHTML = '<div class="cal-empty"><i class="fa-solid fa-calendar-day"></i><p>Select a date to view tasks.</p></div>';
            if (label) label.textContent = '';
            if (count) count.textContent = '0 items';
            return;
        }

        if (label) label.textContent = '— ' + formatDate(dateStr);
        const tasks = allEvents.filter(e => e.date === dateStr);
        if (count) count.textContent = tasks.length + ' item' + (tasks.length !== 1 ? 's' : '');

        if (tasks.length === 0) {
            body.innerHTML = '<div class="cal-empty"><i class="fa-solid fa-check-circle"></i><p>No maintenance tasks scheduled.</p></div>';
            return;
        }

        body.innerHTML = '';
        tasks.forEach(e => {
            const row = document.createElement('div');
            row.className = 'cal-event-row';
            const badgeClass = e.event_type === 'pm' ? 'cal-event-badge-pm' : 'cal-event-badge-ict';
            const badgeText = e.event_type === 'pm' ? 'PM' : 'ICT';
            const statusLower = (e.status || '').toLowerCase().replace(/\s+/g, '');
            const statusClass = 'cal-event-status-' + (statusLower || 'pending');
            row.innerHTML =
                '<div class="cal-event-badge ' + badgeClass + '">' + badgeText + '</div>' +
                '<div class="cal-event-info">' +
                    '<div class="cal-event-title">' + (e.display_number || e.title || '') + '</div>' +
                    '<div class="cal-event-meta">' +
                        (e.assignee ? e.assignee : '') +
                        (e.assignee && e.office ? ' · ' : '') +
                        (e.office ? e.office : '') +
                    '</div>' +
                '</div>' +
                '<span class="cal-event-status ' + statusClass + '">' + (e.status || 'N/A') + '</span>';
            row.onclick = function() { showEventDetail(e); };
            body.appendChild(row);
        });
    }

    function renderUpcoming() {
        const body = document.getElementById('calUpcomingBody');
        if (!body) return;

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const sevenDays = new Date(today);
        sevenDays.setDate(today.getDate() + 7);

        const upcoming = allEvents.filter(e => {
            const d = new Date(e.date);
            return d >= today && d <= sevenDays;
        }).sort((a, b) => a.date.localeCompare(b.date));

        if (upcoming.length === 0) {
            body.innerHTML = '<div class="cal-empty"><i class="fa-solid fa-calendar-week"></i><p>No upcoming tasks.</p></div>';
            return;
        }

        body.innerHTML = '';
        upcoming.forEach(e => {
            const row = document.createElement('div');
            row.className = 'cal-event-row';
            const badgeClass = e.event_type === 'pm' ? 'cal-event-badge-pm' : 'cal-event-badge-ict';
            const badgeText = e.event_type === 'pm' ? 'PM' : 'ICT';
            const statusLower = (e.status || '').toLowerCase().replace(/\s+/g, '');
            const statusClass = 'cal-event-status-' + (statusLower || 'pending');
            row.innerHTML =
                '<div class="cal-event-badge ' + badgeClass + '">' + badgeText + '</div>' +
                '<div class="cal-event-info">' +
                    '<div class="cal-event-title">' + (e.title || e.display_number || '') + '</div>' +
                    '<div class="cal-event-meta">' + formatDate(e.date) + '</div>' +
                '</div>' +
                '<span class="cal-event-status ' + statusClass + '">' + (e.status || 'N/A') + '</span>';
            row.onclick = function() { showEventDetail(e); };
            body.appendChild(row);
        });
    }

    function showEventDetail(e) {
        const card = document.getElementById('calDetailCard');
        const title = document.getElementById('calDetailTitle');
        const body = document.getElementById('calDetailBody');
        if (!card || !title || !body) return;

        title.textContent = e.display_number || e.title || 'Event';

        let badges = '<div class="cal-detail-badges">';
        badges += '<span class="cal-badge cal-badge-' + e.event_type + '">' + (e.event_type === 'pm' ? 'PM' : 'ICT') + '</span>';
        badges += '<span class="cal-badge cal-badge-status">' + (e.status || 'N/A') + '</span>';
        if (e.priority) badges += '<span class="cal-badge cal-badge-priority">' + e.priority + ' priority</span>';
        if ((e.status || '').toLowerCase() === 'overdue') badges += '<span class="cal-badge cal-badge-overdue">Overdue</span>';
        badges += '</div>';

        let table = '<table class="cal-detail-table">';
        table += '<tr><td>Date</td><td>' + formatDate(e.date) + '</td></tr>';
        table += '<tr><td>Assignee</td><td>' + (e.assignee || 'N/A') + '</td></tr>';
        table += '<tr><td>Office</td><td>' + (e.office || 'N/A') + '</td></tr>';
        table += '</table>';

        const btnText = e.event_type === 'pm' ? 'View Work Order' : 'View ICT Request';
        const btn = '<a href="' + e.details_url + '" class="cal-detail-btn"><i class="fa-solid fa-arrow-right"></i> ' + btnText + '</a>';

        body.innerHTML = badges + table + btn;
        card.classList.add('show');
    }

    function formatDate(d) {
        if (typeof d === 'string') d = new Date(d);
        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    }

    /* ══════════════════════════════════════════════
       SCHEDULE MODAL
       ══════════════════════════════════════════════ */
    function initModal() {
        const overlay = document.getElementById('calModalOverlay');
        if (!overlay) return;

        // Type toggle
        document.querySelectorAll('.cal-type-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.cal-type-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                modalType = this.dataset.type;
                document.getElementById('calModalType').value = modalType;
                updateModalHeader();
            });
        });

        // Priority buttons
        document.querySelectorAll('.cal-priority-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.cal-priority-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                modalPriority = this.dataset.priority;
            });
        });

        // Close buttons
        document.getElementById('calModalClose')?.addEventListener('click', closeModal);
        document.getElementById('calModalCancel')?.addEventListener('click', closeModal);
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeModal();
        });

        // Add task button in day tasks header
        document.getElementById('calAddTaskBtn')?.addEventListener('click', function() {
            const dateStr = selectedDate || getTodayDateStr();
            openModal('pm', dateStr);
        });

        // Form submit
        document.getElementById('calModalForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            handleModalSubmit();
        });
    }

    function getTodayDateStr() {
        const d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function openModal(type, dateStr) {
        modalType = type || 'pm';
        document.getElementById('calModalType').value = modalType;
        document.getElementById('calModalDate').value = dateStr || getTodayDateStr();
        document.getElementById('calModalTitleInput').value = '';
        document.getElementById('calModalAssignee').value = '';
        document.getElementById('calModalLocation').value = '';
        document.getElementById('calModalTime').value = '08:00';
        modalPriority = 'medium';

        // Reset type buttons
        document.querySelectorAll('.cal-type-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.cal-type-btn[data-type="' + modalType + '"]')?.classList.add('active');

        // Reset priority buttons
        document.querySelectorAll('.cal-priority-btn').forEach(b => b.classList.remove('active'));
        document.querySelector('.cal-priority-btn[data-priority="medium"]')?.classList.add('active');

        // Clear errors
        document.querySelectorAll('.cal-modal-error').forEach(el => el.textContent = '');
        document.querySelectorAll('.cal-modal-input').forEach(el => el.style.borderColor = '');

        updateModalHeader();
        document.getElementById('calModalOverlay').classList.add('show');
    }

    function closeModal() {
        document.getElementById('calModalOverlay')?.classList.remove('show');
    }

    function updateModalHeader() {
        const title = document.getElementById('calModalTitle');
        const header = document.getElementById('calModalHeader');
        if (title) title.textContent = 'Schedule ' + (modalType === 'pm' ? 'PM' : 'ICT') + ' Task';
        if (header) {
            header.style.background = modalType === 'pm' ? '#0038A8' : '#10b981';
        }
        const submit = document.getElementById('calModalSubmit');
        if (submit) {
            submit.style.background = modalType === 'pm' ? '#0038A8' : '#10b981';
            submit.style.borderColor = modalType === 'pm' ? '#0038A8' : '#10b981';
        }
    }

    function handleModalSubmit() {
        const title = document.getElementById('calModalTitleInput').value.trim();
        const dateStr = document.getElementById('calModalDate').value;
        const time = document.getElementById('calModalTime').value;
        const assignee = document.getElementById('calModalAssignee').value.trim();
        const location = document.getElementById('calModalLocation').value.trim();

        let hasError = false;

        // Validate title
        if (!title) {
            document.getElementById('calModalTitleError').textContent = 'Title is required.';
            document.getElementById('calModalTitleInput').style.borderColor = '#dc2626';
            hasError = true;
        } else {
            document.getElementById('calModalTitleError').textContent = '';
            document.getElementById('calModalTitleInput').style.borderColor = '';
        }

        // Validate date
        if (!dateStr) {
            document.getElementById('calModalDateError').textContent = 'Date is required.';
            document.getElementById('calModalDate').style.borderColor = '#dc2626';
            hasError = true;
        } else {
            document.getElementById('calModalDateError').textContent = '';
            document.getElementById('calModalDate').style.borderColor = '';
        }

        // Validate assignee
        if (!assignee) {
            document.getElementById('calModalAssigneeError').textContent = 'Assignee is required.';
            document.getElementById('calModalAssignee').style.borderColor = '#dc2626';
            hasError = true;
        } else {
            document.getElementById('calModalAssigneeError').textContent = '';
            document.getElementById('calModalAssignee').style.borderColor = '';
        }

        // Validate location
        if (!location) {
            document.getElementById('calModalLocationError').textContent = 'Location is required.';
            document.getElementById('calModalLocation').style.borderColor = '#dc2626';
            hasError = true;
        } else {
            document.getElementById('calModalLocationError').textContent = '';
            document.getElementById('calModalLocation').style.borderColor = '';
        }

        if (hasError) return;

        // Build event object
        const parsed = new Date(dateStr + 'T00:00:00');
        const newEvent = {
            id: 'local-' + Date.now(),
            event_type: modalType,
            source: 'local',
            source_id: null,
            date: dateStr,
            title: title,
            status: 'Scheduled',
            display_number: 'WO-' + Math.floor(1000 + Math.random() * 9000),
            office: location,
            assignee: assignee,
            priority: modalPriority,
            details_url: '#',
            is_editable: true
        };

        // Add to events and re-render
        allEvents.push(newEvent);
        const parsedDate = new Date(dateStr);
        currentMonth = parsedDate.getMonth() + 1;
        currentYear = parsedDate.getFullYear();
        selectedDate = dateStr;

        // Update summary counts
        const summary = {
            pm: allEvents.filter(e => e.event_type === 'pm').length,
            ict: allEvents.filter(e => e.event_type === 'ict').length,
            done: allEvents.filter(e => e.status === 'Completed').length,
            overdue: allEvents.filter(e => e.status === 'Overdue').length
        };
        updateSummary(summary);
        renderMonthlySummary(summary);

        renderCalendar();
        renderTasksForDate(dateStr);
        renderUpcoming();
        showEventDetail(newEvent);
        closeModal();
    }

    // Event Listeners
    document.getElementById('calPrevMonth')?.addEventListener('click', function() {
        currentMonth--;
        if (currentMonth < 1) { currentMonth = 12; currentYear--; }
        syncSelects();
        loadEvents();
    });

    document.getElementById('calNextMonth')?.addEventListener('click', function() {
        currentMonth++;
        if (currentMonth > 12) { currentMonth = 1; currentYear++; }
        syncSelects();
        loadEvents();
    });

    document.getElementById('calMonthSelect')?.addEventListener('change', function() {
        currentMonth = parseInt(this.value);
        syncSelects();
        loadEvents();
    });

    document.getElementById('calYearSelect')?.addEventListener('change', function() {
        currentYear = parseInt(this.value);
        syncSelects();
        loadEvents();
    });

    function syncSelects() {
        const monthSelect = document.getElementById('calMonthSelect');
        const yearSelect = document.getElementById('calYearSelect');
        if (monthSelect) monthSelect.value = currentMonth;
        if (yearSelect) yearSelect.value = currentYear;
    }

    document.getElementById('calTodayBtn')?.addEventListener('click', function() {
        currentMonth = new Date().getMonth() + 1;
        currentYear = new Date().getFullYear();
        syncSelects();
        loadEvents();
    });

    document.getElementById('calDetailClose')?.addEventListener('click', function() {
        document.getElementById('calDetailCard')?.classList.remove('show');
    });

    document.querySelectorAll('.cal-filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.cal-filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            activeFilter = this.dataset.filter;
            loadEvents();
        });
    });

    // Initialize
    if (document.getElementById('calGridBody')) {
        init();
    }
})();