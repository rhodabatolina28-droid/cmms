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
        const gridBody = document.getElementById('calGridBody');
        if (gridBody) gridBody.innerHTML = '<div class="cal-loading"><i class="fa-solid fa-circle-notch"></i></div>';

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

            // Phase 3.0: Hide "Add" task button if an active PM schedule already exists
            const hasActiveSchedule = data.has_active_schedule === true;
            const addBtn = document.getElementById('calAddTaskBtn');
            const calAddTaskHeader = document.getElementById('calAddTaskHeader');
            if (addBtn) {
                addBtn.style.display = hasActiveSchedule ? 'none' : '';
            }
            // Also hide the "Add" button in the header
            const calAddTaskBtnContainer = document.querySelector('.cal-add-task-btn-container');
            if (calAddTaskBtnContainer) {
                calAddTaskBtnContainer.style.display = hasActiveSchedule ? 'none' : '';
            }
        })
        .catch(err => {
            console.error('Calendar load error:', err);
            if (gridBody) gridBody.innerHTML = '<div class="cal-empty"><i class="fa-solid fa-triangle-exclamation"></i><p>Failed to load calendar events.</p></div>';
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

        const gridBody = document.getElementById('calGridBody');
        if (!gridBody) return;
        gridBody.innerHTML = '';

        const firstDay = new Date(currentYear, currentMonth - 1, 1).getDay();
        const daysInMonth = new Date(currentYear, currentMonth, 0).getDate();
        const todayStr = getTodayDateStr();

        for (let i = 0; i < firstDay; i++) {
            gridBody.appendChild(makeCell('', 'cal-other-month'));
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = currentYear + '-' + String(currentMonth).padStart(2,'0') + '-' + String(d).padStart(2,'0');
            const colIndex = (firstDay + d - 1) % 7;
            const isWeekend = colIndex === 0 || colIndex === 6;
            const cell = makeCell(d, isWeekend ? 'cal-weekend' : '', dateStr);

            if (dateStr === todayStr) cell.classList.add('cal-today');
            if (dateStr === selectedDate) cell.classList.add('cal-selected');
            if (dateStr < todayStr) cell.classList.add('cal-past');

            const dayEvents = allEvents.filter(e => e.date === dateStr);
            const contentWrap = cell.querySelector('.cal-day-content');
            dayEvents.slice(0, 3).forEach(e => {
                const chip = document.createElement('button');
                let chipClass = 'cal-chip cal-chip-' + e.event_type;
                const statusLower = (e.status || '').toLowerCase().replace(/\s+/g, '');
                if (statusLower === 'overdue') chipClass += ' cal-chip-overdue';
                if (statusLower === 'completed') chipClass += (e.event_type === 'pm' ? ' cal-chip-completed-pm' : ' cal-chip-completed-ict');
                if (statusLower === 'cancelled') chipClass += ' cal-chip-cancelled';
                if (statusLower === 'ongoing' || statusLower === 'inprogress' || statusLower === 'in-progress') chipClass += ' cal-chip-ongoing';
                chip.className = chipClass;

                // Build compact chip text — always prefer display_number (short) for work orders,
                // fall back to title, and cap length to prevent breaking the calendar cell.
                let chipText = e.display_number || e.title || '';
                if (chipText.length > 22) chipText = chipText.substring(0, 22) + '…';
                chip.textContent = (e.event_type === 'pm' ? 'PM' : 'ICT') + ' — ' + chipText;
                chip.title = (e.display_number || e.title || '') + ' (' + formatDate(e.date) + ')';
                chip.onclick = function(ev) { ev.stopPropagation(); showEventDetail(e); };
                if (contentWrap) contentWrap.appendChild(chip);
            });

            if (dayEvents.length > 3) {
                const more = document.createElement('span');
                more.className = 'cal-more';
                more.textContent = '+' + (dayEvents.length - 3) + ' more';
                if (contentWrap) contentWrap.appendChild(more);
            }

            cell.onclick = function() { selectDate(dateStr); };
            gridBody.appendChild(cell);
        }

        // Fill remaining cells to complete the last week
        const totalCells = gridBody.children.length;
        const remainder = totalCells % 7;
        if (remainder > 0) {
            for (let i = 0; i < 7 - remainder; i++) {
                gridBody.appendChild(makeCell('', 'cal-other-month'));
            }
        }
    }

    function makeCell(day, extraClass, dateStr) {
        const div = document.createElement('div');
        div.className = 'cal-day ' + extraClass;
        if (dateStr) div.dataset.date = dateStr;

        // Fixed-height content wrapper — prevents the cell from expanding
        const content = document.createElement('div');
        content.className = 'cal-day-content';
        div.appendChild(content);

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

            content.appendChild(wrap);
        }
        return div;
    }

    function selectDate(dateStr) {
        selectedDate = dateStr;
        document.querySelectorAll('.cal-day.cal-selected').forEach(c => c.classList.remove('cal-selected'));
        const cell = document.querySelector('.cal-day[data-date="' + dateStr + '"]');
        if (cell) cell.classList.add('cal-selected');
        // Hide the event detail card when switching dates
        document.getElementById('calDetailCard')?.classList.remove('show');
        // Always re-render tasks for the newly selected date (even if empty)
        renderTasksForDate(dateStr);
    }

    function renderTasksForDate(dateStr) {
        const body = document.getElementById('calTasksBody');
        const label = document.getElementById('calTasksDate');
        const count = document.getElementById('calTasksCount');
        const btnAdd = document.getElementById('calAddTaskBtn');
        if (!body) return;

        // Hide the Add button when no date selected, date is in the past, or active schedule exists
        if (btnAdd) {
            const todayStr = getTodayDateStr();
            const hasActiveSchedule = window._calHasActiveSchedule === true;
            btnAdd.style.display = (dateStr && dateStr >= todayStr && !hasActiveSchedule) ? '' : 'none';
        }

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

    function showToast(message, type) {
        const existing = document.querySelector('.cal-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'cal-toast cal-toast-' + (type || 'success');
        toast.innerHTML =
            '<div class="cal-toast-icon"><i class="fa-solid ' + (type === 'error' ? 'fa-circle-xmark' : 'fa-circle-check') + '"></i></div>' +
            '<div class="cal-toast-msg">' + message + '</div>' +
            '<button class="cal-toast-close"><i class="fa-solid fa-xmark"></i></button>';
        document.body.appendChild(toast);

        // Auto-dismiss after 4 seconds
        setTimeout(() => { toast.classList.add('cal-toast-hide'); }, 4000);
        setTimeout(() => { toast.remove(); }, 4500);

        // Close button
        toast.querySelector('.cal-toast-close').addEventListener('click', function() {
            toast.classList.add('cal-toast-hide');
            setTimeout(() => { toast.remove(); }, 300);
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

        // For ICT events, show an IT assign dropdown ONLY when no one is assigned yet (super_admin only)
        let assignHtml = '';
        const isUnassigned = !e.assignee || e.assignee === 'Unassigned' || e.assignee === 'N/A';
        if (e.event_type === 'ict' && isUnassigned && document.getElementById('calItPersonnelJson')) {
            let itPersonnel = [];
            try {
                itPersonnel = JSON.parse(document.getElementById('calItPersonnelJson').value || '[]');
            } catch (err) { itPersonnel = []; }

            if (itPersonnel.length > 0) {
                const assignUrlBase = document.getElementById('calIctAssignUrl')?.value || '';
                const requestId = e.source_id || '';
                assignHtml =
                    '<div class="cal-assign-panel">' +
                        '<label class="cal-modal-label">Assign IT Personnel</label>' +
                        '<div class="cal-assign-flex">' +
                            '<select id="calAssignSelect" class="cal-modal-input">' +
                                '<option value="">Select IT personnel...</option>' +
                                itPersonnel.map(p => '<option value="' + p.id + '">' + p.full_name + '</option>').join('') +
                            '</select>' +
                            '<button class="cal-assign-btn" id="calAssignBtn" data-request-id="' + requestId + '" data-assign-url="' + assignUrlBase + '/' + requestId + '/assign-it">Assign</button>' +
                        '</div>' +
                    '</div>';
            }
        }

        const btnText = e.event_type === 'pm' ? 'View Work Order' : 'View ICT Request';
        const btn = '<a href="' + e.details_url + '" class="cal-detail-btn"><i class="fa-solid fa-arrow-right"></i> ' + btnText + '</a>';

        body.innerHTML = badges + table + assignHtml + btn;
        card.classList.add('show');

        // Wire up assign button
        const assignBtn = document.getElementById('calAssignBtn');
        if (assignBtn) {
            assignBtn.addEventListener('click', function() {
                const select = document.getElementById('calAssignSelect');
                const assignedTo = select ? select.value : '';
                if (!assignedTo) { alert('Please select an IT personnel to assign.'); return; }

                const url = this.dataset.assignUrl;
                const btn = this;
                btn.textContent = 'Assigning...';
                btn.disabled = true;

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ assigned_to: assignedTo })
                })
                .then(r => {
                    if (r.redirected) { window.location.href = r.url; return null; }
                    return r.json();
                })
                .then(data => {
                    if (data === null) return;
                    // Get the selected personnel name for the toast message
                    const selectedPersonName = select.options[select.selectedIndex]?.text || '';
                    const toastMsg = selectedPersonName
                        ? 'Assigned to ' + selectedPersonName + '. ICT request successfully assigned.'
                        : (data.message || 'ICT request assigned successfully.');
                    btn.textContent = 'Assign';
                    btn.disabled = false;
                    showToast(toastMsg, 'success');
                    loadEvents();
                })
                .catch(() => {
                    btn.textContent = 'Assign';
                    btn.disabled = false;
                    showToast('Failed to assign ICT request. Please try again.', 'error');
                });
            });
        }
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

        // Close buttons
        document.getElementById('calModalClose')?.addEventListener('click', closeModal);
        document.getElementById('calModalCancel')?.addEventListener('click', closeModal);
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeModal();
        });

        // Add task button in day tasks header
        document.getElementById('calAddTaskBtn')?.addEventListener('click', function() {
            openModal();
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

    function openModal() {
        // Reset all fields
        document.getElementById('calModalScheduleName').value = '';
        document.getElementById('calModalDivision').value = '';
        document.getElementById('calModalFrequency').value = '';
        document.getElementById('calModalStartDate').value = '';

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
        if (title) title.textContent = 'Create PM Schedule';
        if (header) {
            header.style.background = '#0038A8';
        }
        const submit = document.getElementById('calModalSubmit');
        if (submit) {
            submit.style.background = '#0038A8';
            submit.style.borderColor = '#0038A8';
        }
    }

    function handleModalSubmit() {
        const scheduleName = document.getElementById('calModalScheduleName').value.trim();
        const division = document.getElementById('calModalDivision').value;
        const frequency = document.getElementById('calModalFrequency').value;
        const startDate = document.getElementById('calModalStartDate').value;

        let hasError = false;

        // Validate schedule name
        if (!scheduleName) {
            document.getElementById('calModalScheduleNameError').textContent = 'Schedule name is required.';
            document.getElementById('calModalScheduleName').style.borderColor = '#dc2626';
            hasError = true;
        } else {
            document.getElementById('calModalScheduleNameError').textContent = '';
            document.getElementById('calModalScheduleName').style.borderColor = '';
        }

        // Validate division
        if (!division) {
            document.getElementById('calModalDivisionError').textContent = 'Target division is required.';
            document.getElementById('calModalDivision').style.borderColor = '#dc2626';
            hasError = true;
        } else {
            document.getElementById('calModalDivisionError').textContent = '';
            document.getElementById('calModalDivision').style.borderColor = '';
        }

        // Validate frequency
        if (!frequency) {
            document.getElementById('calModalFrequencyError').textContent = 'Frequency is required.';
            document.getElementById('calModalFrequency').style.borderColor = '#dc2626';
            hasError = true;
        } else {
            document.getElementById('calModalFrequencyError').textContent = '';
            document.getElementById('calModalFrequency').style.borderColor = '';
        }

        // Validate start date
        if (!startDate) {
            document.getElementById('calModalStartDateError').textContent = 'Start date is required.';
            document.getElementById('calModalStartDate').style.borderColor = '#dc2626';
            hasError = true;
        } else {
            // Check if weekend
            const day = new Date(startDate).getDay();
            if (day === 0 || day === 6) {
                document.getElementById('calModalStartDateError').textContent = 'Start date cannot be a weekend. Please choose a weekday.';
                document.getElementById('calModalStartDate').style.borderColor = '#dc2626';
                hasError = true;
            } else {
                document.getElementById('calModalStartDateError').textContent = '';
                document.getElementById('calModalStartDate').style.borderColor = '';
            }
        }

        if (hasError) return;

        // Get the store URL from the hidden input
        const storeUrlInput = document.getElementById('calStorePmScheduleUrl');
        const storeUrl = storeUrlInput ? storeUrlInput.value : '';
        if (!storeUrl) {
            showToast('PM schedule creation is not configured for this user.', 'error');
            return;
        }

        // POST to server — creates a real pm_schedules row
        const submitBtn = document.getElementById('calModalSubmit');
        submitBtn.textContent = 'Creating...';
        submitBtn.disabled = true;

        // Use form data (not JSON) because the controller expects form-encoded data
        const formData = new FormData();
        formData.append('schedule_name', scheduleName);
        // "All" means no division filter — send null
        formData.append('division_filter', division === 'All' ? '' : division);
        formData.append('frequency', frequency);
        formData.append('next_scheduled_date', startDate);

        fetch(storeUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(r => {
            // Always expect JSON response from the action (AJAX mode)
            return r.json();
        })
        .then(data => {
            submitBtn.textContent = 'Create Schedule';
            submitBtn.disabled = false;

            // Handle validation errors (422)
            if (data.errors) {
                const fieldMap = {
                    'schedule_name': { error: 'calModalScheduleNameError', input: 'calModalScheduleName' },
                    'division_filter': { error: 'calModalDivisionError', input: 'calModalDivision' },
                    'frequency': { error: 'calModalFrequencyError', input: 'calModalFrequency' },
                    'next_scheduled_date': { error: 'calModalStartDateError', input: 'calModalStartDate' },
                };
                Object.keys(data.errors).forEach(key => {
                    const map = fieldMap[key];
                    if (map) {
                        const errorEl = document.getElementById(map.error);
                        if (errorEl) errorEl.textContent = data.errors[key][0];
                        const inputEl = document.getElementById(map.input);
                        if (inputEl) inputEl.style.borderColor = '#dc2626';
                    }
                });
                return;
            }

            // Handle one-active-per-branch error (success: false)
            if (data.success === false) {
                document.getElementById('calModalScheduleNameError').textContent = data.message;
                document.getElementById('calModalScheduleName').style.borderColor = '#dc2626';
                showToast(data.message, 'error');
                return;
            }

            // Handle success (success: true)
            if (data.success === true) {
                closeModal();
                loadEvents();
                showToast(data.message || 'PM Schedule created successfully.', 'success');
                return;
            }

            // Fallback — unknown response
            if (data.message) {
                showToast(data.message, 'error');
            }
        })
        .catch(() => {
            submitBtn.textContent = 'Create Schedule';
            submitBtn.disabled = false;
            showToast('Failed to create PM Schedule. Please try again.', 'error');
        });
    }

    // Event Listeners
    function resetDateSelection() {
        selectedDate = null;
        document.querySelectorAll('.cal-day.cal-selected').forEach(c => c.classList.remove('cal-selected'));
        document.getElementById('calDetailCard')?.classList.remove('show');
        renderTasksForDate(null);
    }

    document.getElementById('calPrevMonth')?.addEventListener('click', function() {
        currentMonth--;
        if (currentMonth < 1) { currentMonth = 12; currentYear--; }
        syncSelects();
        resetDateSelection();
        loadEvents();
    });

    document.getElementById('calNextMonth')?.addEventListener('click', function() {
        currentMonth++;
        if (currentMonth > 12) { currentMonth = 1; currentYear++; }
        syncSelects();
        resetDateSelection();
        loadEvents();
    });

    document.getElementById('calMonthSelect')?.addEventListener('change', function() {
        currentMonth = parseInt(this.value);
        syncSelects();
        resetDateSelection();
        loadEvents();
    });

    document.getElementById('calYearSelect')?.addEventListener('change', function() {
        currentYear = parseInt(this.value);
        syncSelects();
        resetDateSelection();
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
        resetDateSelection();
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