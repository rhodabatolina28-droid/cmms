@extends('layouts.app')

@section('title', 'Maintenance Calendar')
@section('page-title', 'Maintenance Calendar')

@vite(['resources/css/maintenance-calendar.css', 'resources/js/maintenance-calendar.js'])

@section('content')
<div class="cal-page">

    {{-- Header --}}
    <div class="cal-header">
        <h1 class="cal-title">
            Maintenance Calendar
        </h1>
    </div>

    {{-- Sub-nav Strip: Counts + Filters --}}
    <div class="cal-subnav">
        <div class="cal-subnav-stats">
            <span class="cal-subnav-stat"><span class="cal-subnav-label">PM</span><span class="cal-subnav-value" id="calPmCount">0 tasks</span></span>
            <span class="cal-subnav-divider">|</span>
            <span class="cal-subnav-stat"><span class="cal-subnav-label">ICT</span><span class="cal-subnav-value" id="calIctCount">0 tasks</span></span>
            <span class="cal-subnav-divider">|</span>
            <span class="cal-subnav-stat"><span class="cal-subnav-label">Done</span><span class="cal-subnav-value cal-subnav-done" id="calDoneCount">0</span></span>
            <span class="cal-subnav-divider">|</span>
            <span class="cal-subnav-stat"><span class="cal-subnav-label">Overdue</span><span class="cal-subnav-value cal-subnav-overdue" id="calOverdueCount">0</span></span>
        </div>
        <div class="cal-subnav-filters">
            <button class="cal-filter-btn active" data-filter="all">All Types</button>
            <button class="cal-filter-btn" data-filter="pm">PM</button>
            <button class="cal-filter-btn" data-filter="ict">ICT</button>
        </div>
    </div>

    {{-- Main Layout --}}
    <div class="cal-layout">

        {{-- Calendar Grid (Left) --}}
        <div class="cal-card">
            <div class="cal-card-header">
                <div>
                    <span class="cal-month-label" id="calMonthLabel">Loading...</span>
                    <span class="cal-today-label" id="calTodayLabel"></span>
                </div>
                <div class="cal-nav-group">
                    <select class="cal-select" id="calMonthSelect"></select>
                    <select class="cal-select" id="calYearSelect"></select>
                    <button class="cal-nav-today" id="calTodayBtn">Today</button>
                    <button class="cal-nav-btn" id="calPrevMonth"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="cal-nav-btn" id="calNextMonth"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
            <table class="cal-grid">
                <thead>
                    <tr>
                        <th>Sun</th>
                        <th>Mon</th>
                        <th>Tue</th>
                        <th>Wed</th>
                        <th>Thu</th>
                        <th>Fri</th>
                        <th>Sat</th>
                    </tr>
                </thead>
                <tbody id="calGridBody">
                    <tr><td colspan="7" class="cal-loading"><i class="fa-solid fa-circle-notch"></i></td></tr>
                </tbody>
            </table>
            <div class="cal-legend">
                <div class="cal-legend-item"><span class="cal-legend-dot cal-legend-dot-pm"></span> Preventive Maintenance</div>
                <div class="cal-legend-item"><span class="cal-legend-dot cal-legend-dot-ict"></span> Information & Communications Technology</div>
                <div class="cal-legend-item"><span class="cal-legend-dot cal-legend-dot-overdue"></span> Overdue</div>
            </div>
        </div>

        {{-- Right Panel --}}
        <div class="cal-right-panel">

            {{-- Card 1: Selected Event Detail --}}
            <div class="cal-detail-card" id="calDetailCard">
                <div class="cal-detail-header">
                    <span class="cal-detail-title" id="calDetailTitle">Event Details</span>
                    <button class="cal-detail-close" id="calDetailClose"><i class="fa-solid fa-times"></i></button>
                </div>
                <div class="cal-detail-body" id="calDetailBody"></div>
            </div>

            {{-- Card 2: Selected Day Tasks --}}
            <div class="cal-tasks-card">
                <div class="cal-tasks-header">
                    <div class="cal-tasks-header-left">
                        <span class="cal-tasks-title">Tasks</span>
                        <span class="cal-tasks-date" id="calTasksDate"></span>
                    </div>
                    <div class="cal-tasks-header-right">
                        <span class="cal-tasks-count" id="calTasksCount">0 items</span>
                        <button class="cal-add-btn" id="calAddTaskBtn" title="Add task"><i class="fa-solid fa-plus"></i> Add</button>
                    </div>
                </div>
                <div class="cal-tasks-body" id="calTasksBody">
                    <div class="cal-empty"><i class="fa-solid fa-calendar-day"></i><p>Select a date to view tasks.</p></div>
                </div>
            </div>

            {{-- Card 3: Monthly Summary (when no day selected) --}}
            <div class="cal-summary-card" id="calSummaryCard">
                <div class="cal-summary-header">
                    <span class="cal-summary-title">Monthly Summary</span>
                </div>
                <div class="cal-summary-grid">
                    <div class="cal-summary-item">
                        <span class="cal-summary-value cal-summary-value-pm" id="calSummaryPm">0</span>
                        <span class="cal-summary-label">PM Tasks</span>
                    </div>
                    <div class="cal-summary-item">
                        <span class="cal-summary-value cal-summary-value-ict" id="calSummaryIct">0</span>
                        <span class="cal-summary-label">ICT Tasks</span>
                    </div>
                    <div class="cal-summary-item">
                        <span class="cal-summary-value cal-summary-value-done" id="calSummaryDone">0</span>
                        <span class="cal-summary-label">Completed</span>
                    </div>
                    <div class="cal-summary-item">
                        <span class="cal-summary-value cal-summary-value-overdue" id="calSummaryOverdue">0</span>
                        <span class="cal-summary-label">Overdue</span>
                    </div>
                </div>
            </div>

            {{-- Card 4: Upcoming Next 7 Days --}}
            <div class="cal-upcoming-card">
                <div class="cal-upcoming-header">
                    <span class="cal-upcoming-title">Upcoming — Next 7 Days</span>
                </div>
                <div class="cal-upcoming-body" id="calUpcomingBody">
                    <div class="cal-empty"><i class="fa-solid fa-calendar-week"></i><p>No upcoming tasks.</p></div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- Schedule Modal --}}
<div class="cal-modal-overlay" id="calModalOverlay">
    <div class="cal-modal-box">
        <div class="cal-modal-header" id="calModalHeader">
            <div>
                <p class="cal-modal-kicker">New Work Order</p>
                <p class="cal-modal-title" id="calModalTitle">Schedule PM Task</p>
            </div>
            <button class="cal-modal-close" id="calModalClose"><i class="fa-solid fa-times"></i></button>
        </div>
        <form id="calModalForm" class="cal-modal-form">
            <input type="hidden" id="calModalType" value="pm">

            {{-- Type Toggle --}}
            <div class="cal-modal-field">
                <label class="cal-modal-label">Task Type</label>
                <div class="cal-type-toggle">
                    <button type="button" class="cal-type-btn active" data-type="pm">PM — Preventive Maint.</button>
                    <button type="button" class="cal-type-btn" data-type="ict">ICT — Information & Comms</button>
                </div>
            </div>

            {{-- Title --}}
            <div class="cal-modal-field">
                <label class="cal-modal-label">Task Title *</label>
                <input type="text" id="calModalTitleInput" class="cal-modal-input" placeholder="e.g. Desktop PC Cleaning & Dust Removal">
                <p class="cal-modal-error" id="calModalTitleError"></p>
            </div>

            {{-- Date + Time --}}
            <div class="cal-modal-row">
                <div class="cal-modal-field">
                    <label class="cal-modal-label">Scheduled Date *</label>
                    <input type="date" id="calModalDate" class="cal-modal-input">
                    <p class="cal-modal-error" id="calModalDateError"></p>
                </div>
                <div class="cal-modal-field">
                    <label class="cal-modal-label">Time *</label>
                    <input type="time" id="calModalTime" class="cal-modal-input" value="08:00">
                </div>
            </div>

            {{-- Assignee --}}
            <div class="cal-modal-field">
                <label class="cal-modal-label">Assignee *</label>
                <input type="text" id="calModalAssignee" class="cal-modal-input" placeholder="e.g. J. Reyes">
                <p class="cal-modal-error" id="calModalAssigneeError"></p>
            </div>

            {{-- Location --}}
            <div class="cal-modal-field">
                <label class="cal-modal-label">Location *</label>
                <input type="text" id="calModalLocation" class="cal-modal-input" placeholder="e.g. Server Room 1">
                <p class="cal-modal-error" id="calModalLocationError"></p>
            </div>

            {{-- Priority --}}
            <div class="cal-modal-field">
                <label class="cal-modal-label">Priority</label>
                <div class="cal-priority-group">
                    <button type="button" class="cal-priority-btn" data-priority="low">Low</button>
                    <button type="button" class="cal-priority-btn active" data-priority="medium">Medium</button>
                    <button type="button" class="cal-priority-btn" data-priority="high">High</button>
                    <button type="button" class="cal-priority-btn" data-priority="critical">Critical</button>
                </div>
            </div>

            {{-- Actions --}}
            <div class="cal-modal-actions">
                <button type="button" class="cal-modal-cancel" id="calModalCancel">Cancel</button>
                <button type="submit" class="cal-modal-submit" id="calModalSubmit">Save Task</button>
            </div>
        </form>
    </div>
</div>

{{-- Hidden input for events URL --}}
<input type="hidden" id="calEventsUrl" value="{{ auth()->user()->role === 'super_admin' ? route('pm-schedules.calendar.events') : route('maintenance.calendar.events') }}">
@endsection