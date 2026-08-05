# Preventive Maintenance Calendar and Manual Scheduling Implementation Plan

## Purpose

Add a calendar-based view and a manual "Schedule for Later" option to the existing Preventive Maintenance module without changing the current PM eligibility rules, recurring-cycle logic, request creation flow, roles, notifications, audit behavior, routes, or existing Generate Now behavior.

This is an enhancement project, not a replacement of the current PM module.

## Verified Existing Architecture

The following components already exist and are the authoritative PM workflow:

- `pm_schedules` is the recurring PM configuration table and is represented by `App\\Models\\PMSchedule`.
- `pm_cycles` tracks a generated PM cycle.
- `pm_division_schedules` tracks actual per-division completion and the next computed due date.
- `GeneratePMScheduleService::generate(PMSchedule $schedule)` is the only service that creates PM requests and preventive-maintenance detail records.
- `pm:generate-scheduled` is already registered to run daily. It advances completed divisions and triggers due recurring schedules.
- Existing generation sends the current PM notifications and creates existing PM history.
- Current PM web routes are restricted to `super_admin`. The new PM calendar and manual scheduling routes must use the same role boundary.

## Combined PM and ICT Calendar Scope

This section supersedes every PM-only calendar and dashboard-display restriction elsewhere in this plan. Manual future-generation scheduling remains PM-only. The calendar and dashboard are a combined, read-only view of existing PM and ICT work.

### Event sources

The combined calendar must use existing records only:

| Event type | Existing source | Calendar date rule | Detail destination |
| --- | --- | --- | --- |
| Future PM generation | new `pm_generation_schedules` | `scheduled_date` | new queued-generation detail page |
| Generated PM work order | existing `requests` where `type = Preventive Maintenance` | existing PM service/maintenance date; fall back to request creation date | existing maintenance request detail |
| ICT request | existing `requests` where `type = ICT` with related `repair_requests` | use `repair_requests.service_schedule_date` when set; otherwise `date_received`; otherwise the existing request `created_at` date | existing ICT request detail |

No new ICT database column is required. An ICT request appears on the calendar immediately using its existing request date. When IT sets the already-existing `service_schedule_date`, the calendar shows the request on that scheduled service date instead.

The calendar must not create, update, assign, approve, reschedule, notify, or alter any ICT request. It only reads the existing ICT request, repair-request dates, type, status, office, and assigned user.

### Existing visibility and authorization

The calendar page may be available to the existing authenticated roles (`user`, `it`, `admin`, and `super_admin`), but it must return only events the current user can already view through existing request-policy behavior.

The combined calendar data Action must reuse existing request visibility checks:

- ICT events use the current `viewIct` policy behavior.
- PM work-order events use the current `viewMaintenance` policy behavior.
- PM manual queue records and Schedule for Later / reschedule / cancel controls remain super-admin-only, matching existing PM schedule routes.

Do not create a broader ICT or PM permission. The calendar is an additional read-only surface for data each role is already authorized to see.

### Calendar layout and functional filters

The supplied reference layout becomes a combined Maintenance Calendar:

- `All Types` shows visible PM and ICT events together.
- `PM` shows visible PM queue and PM work-order events only.
- `ICT` shows visible ICT request events only.
- The monthly grid, selected-day panel, and Upcoming - Next 7 Days list must all use the same active filter.
- Chips are explicitly labelled `PM - ...` or `ICT - ...`.
- PM and ICT colors must remain visually distinct, while status badges still show the existing request status.

The header summary strip must use live, visibility-filtered counts:

- PM tasks: visible PM events in the displayed period.
- ICT tasks: visible ICT events in the displayed period.
- Done: visible PM or ICT requests with the existing `Completed` status.
- Overdue: derived pending manual PM queue rows past their scheduled date; it must not invent an ICT overdue rule.
- Pending / Scheduled: existing status counts shown only when their status exists in the current filtered event set.

### Selected-day and upcoming task panels

For the selected day and the upcoming seven days, display both PM and ICT entries that are visible to the current user.

Each row must include:

- `PM` or `ICT` badge.
- Existing request number or schedule name.
- Existing request status.
- Existing office/division.
- Existing scheduled/request date.
- Existing detail-page link.

For an ICT event, the side panel may show `Service Schedule` when `service_schedule_date` exists; otherwise it must show `Request Date`. It must not display a fabricated service date.

### Dashboard integration

Add the calendar widget to each existing dashboard using the same combined calendar summary Action and the existing dashboard user's visibility.

The widget must show:

- Today: visible PM and ICT tasks for today.
- Upcoming: visible PM and ICT tasks in the next seven days.
- This Week: visible PM and ICT tasks in the current week.
- Completed: visible PM and ICT requests already completed in the displayed period.
- Overdue: only the derived manual PM queue overdue count.

Each dashboard link opens the combined calendar with the matching filter. The link must not bypass existing request policies.

### Required routes

Use a new combined calendar route namespace, separate from existing PM routes:

| Method | URI | Name | Access |
| --- | --- | --- | --- |
| GET | `/maintenance-calendar` | `maintenance-calendar.index` | existing authenticated roles |
| GET | `/maintenance-calendar/events` | `maintenance-calendar.events` | existing authenticated roles; policy-filtered data |
| GET | `/maintenance-calendar/{eventType}/{id}` | `maintenance-calendar.show` | existing authenticated roles; policy-checked detail redirect/view |

The new PM manual-generation routes remain under the existing super-admin PM group. No existing ICT or PM route is renamed or replaced.

### Combined-calendar tests

Add tests proving:

1. An ICT request with no service schedule date appears on its existing request date.
2. An ICT request with a service schedule date appears on that existing service date.
3. PM and ICT events appear together for an authorized user using All Types.
4. The PM and ICT filters return only their matching type.
5. An end user cannot receive another user's ICT event.
6. IT, admin, and super-admin see only the ICT and PM events allowed by their existing policies.
7. Calendar and dashboard counts use the same filtered source data.
8. The calendar reads ICT records without updating any ICT request, repair request, notification, audit log, or asset state.

## Non-Negotiable Preservation Rules

The implementation must not modify or duplicate:

1. `GeneratePMScheduleService::generate()` eligibility queries, asset filters, ordering, request fields, notifications, or transaction workflow.
2. PM cycle and division advancement behavior in `GeneratePMScheduleService::checkAndAdvance()`.
3. Existing `pm_schedules`, `pm_cycles`, `pm_division_schedules`, `requests`, `preventive_maintenances`, and `pm_schedule_histories` records.
4. Existing Generate Now endpoint, response JSON, route name, throttle, or UI behavior.
5. Existing PM role middleware, policies, notifications, audit logs, redirects, messages, or request validation.
6. Existing daily `pm:generate-scheduled` selection logic.

The new feature may call the existing generation service. It must never reimplement the PM asset or user selection algorithm.

## Key Design Decision

Do not create another `pm_schedules` table and do not add manual-queue fields to the existing `pm_schedules` table.

The existing table is a recurring PM configuration and is already used by cycles, divisions, requests, the scheduler, and views. Reusing it as a one-time future-generation queue would conflate two different responsibilities and risk changing automatic PM behavior.

Create a separate table named `pm_generation_schedules`.

A `pm_generation_schedules` row represents one manually requested future execution of one existing `pm_schedules` configuration.

## Proposed Data Model

### New table: `pm_generation_schedules`

| Column | Type | Purpose |
| --- | --- | --- |
| `id` | bigint | Primary key |
| `pm_schedule_id` | foreign key | Existing recurring PM configuration to run |
| `scheduled_date` | date | Requested future generation date |
| `generated_at` | timestamp nullable | When the existing generation service actually ran |
| `generated_by` | foreign key | Super admin who created the future run |
| `status` | string | Internal queue state: `Pending`, `Processing`, `Generated`, `Cancelled`, or `Failed` |
| `remarks` | text nullable | Existing request key and user-entered scheduling note |
| `estimated_asset_count` | unsigned integer nullable | Read-only preview count at scheduling time |
| `generated_count` | unsigned integer nullable | Actual count returned after generation |
| `division_filter_snapshot` | string nullable | Existing schedule division filter at queue time |
| `generated_division` | string nullable | Actual generated focus division after service execution |
| `pm_cycle_id` | foreign key nullable | Cycle created or used by the existing generator |
| `failure_message` | text nullable | Operational error when the generation service throws |
| timestamps | timestamps | Standard timestamps |

Required indexes:

- index on `[status, scheduled_date]` for the daily scheduler
- index on `pm_schedule_id`
- index on `generated_by`
- unique index on `[pm_schedule_id, scheduled_date]`

The unique index prevents two manually queued runs for the same PM configuration on the same day, which could otherwise attempt duplicate generation. It does not alter existing automatic scheduling.

### New model: `App\\Models\\PMGenerationSchedule`

Required relationships:

- `schedule(): BelongsTo` to `PMSchedule` using `pm_schedule_id`
- `generator(): BelongsTo` to `User` using `generated_by`
- `cycle(): BelongsTo` to `PMCycle` using `pm_cycle_id`

Required casts:

- `scheduled_date` as `date`
- `generated_at` as `datetime`

Suggested query scopes:

- `scopePending($query)`
- `scopeDueOnOrBefore($query, CarbonInterface $date)`
- `scopeVisibleToBranch($query, User $user)` only if it can be copied from existing PM branch behavior without changing its result

## Status and Calendar Mapping

The persistent queue state is not the same as the visual calendar status.

| Queue / PM state | Calendar label | Rule |
| --- | --- | --- |
| `Cancelled` | Cancelled | Persisted cancellation |
| `Pending` and date before today | Overdue | Derived; no automatic database mutation |
| `Pending` and date today/future | Pending | Future or due manual generation awaiting scheduler |
| `Processing` | Scheduled | Scheduler has locked the queue record and is invoking existing generation |
| `Generated` with active PM work orders | Scheduled | Linked PM requests include Scheduled, Ongoing, or Awaiting Signature |
| `Generated` with complete linked cycle/work orders | Completed | Existing generated PM work has completed |
| `Failed` | Overdue | Visible as overdue with an operational detail; no silent retry or duplicate creation |

The initial manual queue state is `Pending`. This is required so the rule "only Pending schedules can be rescheduled" remains exact.

## Phase A - Database and Model Only

1. Create one additive migration for `pm_generation_schedules`.
2. Do not alter the existing PM migrations or existing PM schema.
3. Create `PMGenerationSchedule` with only the new table relationships and casts.
4. Add no model observer and no automatic generation behavior in the model.
5. Add migration tests that confirm the table and foreign keys work with the existing PM schedule model.

Verification:

- `php artisan migrate:fresh --env=testing`
- `php artisan test`
- Confirm existing `PMFlowTest` remains unchanged and passing.

## Phase B - Requests and Actions

### New Form Requests

#### `StorePMGenerationScheduleRequest`

Use `authorize(): true` initially. Existing super-admin middleware remains the authorization authority.

Rules:

- `pm_schedule_id`: required, exists in `pm_schedules,id`
- `scheduled_date`: required, date, after:today
- `remarks`: nullable, string, maximum agreed existing text length

The request keys must remain stable once introduced.

#### `UpdatePMGenerationScheduleRequest`

Use `authorize(): true`.

Rules:

- `scheduled_date`: required, date, after:today
- `remarks`: nullable, string, same maximum as create

The Action, not the Form Request, must enforce that only a `Pending` row can be changed. This maintains the current controller/middleware authorization style and produces one controlled response path.

### New Actions

#### `CreatePMGenerationScheduleAction`

Inputs:

- the entire request object
- the authenticated super-admin user
- the existing `PMSchedule`
- the existing `GeneratePMScheduleService` for read-only preview only

Exact responsibilities:

1. Read the validated values.
2. Load the existing PM configuration using the supplied ID.
3. Reject inactive PM configurations using a new feature-specific response/message.
4. Obtain a non-mutating preview count from the existing service.
5. Store one `Pending` queue row with the existing schedule reference and snapshot fields.
6. Create an audit entry for this new queue creation only.
7. Redirect back to the calendar or PM schedule detail using the new feature route and a new feature-specific flash message.

It must not call `generate()`.

#### `ReschedulePMGenerationScheduleAction`

Exact responsibilities:

1. Lock the queue row.
2. Confirm its persisted state is exactly `Pending`.
3. Update only `scheduled_date`, `remarks`, and any queue snapshot metadata that must be refreshed.
4. Add an audit entry containing old and new dates.
5. Return the new feature response.

It must not edit generated, processing, cancelled, or failed rows.

#### `CancelPMGenerationScheduleAction`

Exact responsibilities:

1. Lock the queue row.
2. Allow cancellation only while `Pending`.
3. Set `status` to `Cancelled`; do not delete the row.
4. Record the cancellation in audit logs.

#### `RunDuePMGenerationScheduleAction`

This is the central queue runner used only by the console command.

Exact responsibilities:

1. Lock one due `Pending` queue record.
2. Atomically mark it `Processing` before calling the generator.
3. Load the existing `PMSchedule`.
4. Call only `GeneratePMScheduleService::generate($pmSchedule)`.
5. On successful returned request numbers:
   - refresh the existing PM schedule
   - record `generated_at`, `generated_count`, `generated_division`, and `pm_cycle_id`
   - mark the queue row `Generated`
6. On an exception:
   - record the error in `failure_message`
   - mark the row `Failed`
   - create an audit entry for the new queue feature
   - do not suppress the command error
7. On an existing service cooldown result or an empty generation result:
   - do not create duplicate requests
   - preserve the exact existing service output in logs
   - record a feature-specific operational result for manual review

The action must not duplicate any asset eligibility, user grouping, PM request field, notification, or cycle logic.

#### `GetPMCalendarDataAction`

Read-only Action that composes calendar events from:

- future/past manual queue rows
- the related existing `PMSchedule`
- generated PM request/cycle data needed for visual status

Each event must contain:

- event ID
- event date
- calendar label/status
- schedule name
- estimated or actual asset/work-order count
- target or generated division
- generator name
- details URL
- whether it is editable

Calendar event data must use the same branch scope as the current super-admin PM pages.

#### `BuildPMDashboardCalendarSummaryAction`

Read-only Action used by the existing super-admin dashboard to produce:

- Today's PM
- Upcoming PM
- This Week's PM
- Overdue PM
- Completed PM

Each count and link must point to the new calendar page with a non-destructive filter parameter.

## Phase C - Scheduler Integration

The existing `pm:generate-scheduled` command must stay registered and keep its current recurring-cycle processing intact.

Add the new manual-queue processing as an additive, isolated step:

1. Run `GenerateDueManualPMSchedulesAction`.
2. Then run the existing command's current Phase 1 and Phase 2 logic unchanged.

Do not replace the command, change its current due queries, or change its schedule registration.

The existing daily cadence remains the source of truth. The server must continue to run Laravel's scheduler through the standard production cron entry:

```bash
php artisan schedule:run
```

Only the new queue records due on or before today and still `Pending` are processed.

## Phase D - Routes and Authorization

Add only new named routes under the existing `role:super_admin` PM group.

Suggested routes:

| Method | URI | Name | Purpose |
| --- | --- | --- | --- |
| GET | `/pm-calendar` | `pm-calendar.index` | Monthly calendar page |
| GET | `/pm-calendar/events` | `pm-calendar.events` | Calendar JSON data |
| GET | `/pm-calendar/{pmGenerationSchedule}` | `pm-calendar.show` | Manual queue detail |
| POST | `/pm-generation-schedules` | `pm-generation-schedules.store` | Schedule for later |
| PUT | `/pm-generation-schedules/{pmGenerationSchedule}` | `pm-generation-schedules.update` | Reschedule pending queue |
| POST | `/pm-generation-schedules/{pmGenerationSchedule}/cancel` | `pm-generation-schedules.cancel` | Cancel pending queue |

No existing route name, URI, controller method, middleware, or throttle may change.

## Phase E - UI Integration

### Existing PM generation page

Keep the current Generate Now button, request, JSON response, and behavior unchanged.

Add a separate "Schedule for Later" control:

1. It opens a small modal or dedicated form.
2. It asks for the future date and optional remarks.
3. It posts to the new queue route.
4. It does not call the existing Generate Now endpoint.
5. It displays only new feature messages.

### Calendar page - required visual direction

Initial release scope: a PM-only monthly calendar that follows the supplied reference layout while using the current CMMS shell, PM routes, role boundary, data fields, and status terminology.

Do not copy the screenshot's sample tasks, ICT data, FY label, hard-coded counts, or external application navigation. Every displayed count and event must come from the current CMMS PM records.

Use the existing `layouts.app` header/sidebar, current authenticated-user display, Font Awesome assets already used by the project, Blade, Vite, and vanilla JavaScript. Do not introduce a new calendar package unless separately approved.

#### Page layout

```text
Existing CMMS application header and navigation
PM status summary strip: Pending | Scheduled | Completed | Overdue | Cancelled

Calendar title and navigation                 Selected-date task panel
Month / selected date     Today / Prev / Next  - selected date
                                                 - PM tasks on that date
Monthly calendar grid                          - empty state if no PM task

PM status legend                               Upcoming PM - next 7 days
                                                 - event rows with date/status
```

The desktop layout is a two-column grid:

- Main calendar column: approximately two thirds of the available content width.
- Right-hand column: selected-date task panel above an upcoming-PM panel.
- Mobile layout: single column; selected-date and upcoming panels appear below the monthly grid.
- The existing responsive CSS conventions must be reused. No global layout selectors may be changed.

#### Header and summary strip

Use the current CMMS page header. Add a PM Calendar page title only inside the normal page content/header region.

The calendar summary strip must contain live PM-only counts:

- Pending: new `pm_generation_schedules` records still Pending and not overdue.
- Scheduled: existing generated PM requests with the current system statuses `Scheduled`, `Ongoing`, or `Awaiting Signature`.
- Completed: existing generated PM requests with status `Completed`.
- Overdue: Pending manual-generation records whose `scheduled_date` is before today.
- Cancelled: cancelled manual-generation records.

Do not include ICT counts, ICT events, or All Types/ICT filters in the first implementation. Those would require a separate combined-calendar feature and could change non-PM visibility rules.

#### Calendar controls

At the top of the main panel provide:

- Current month and year, for example `August 2026`.
- A small selected-date subtitle, for example `Today - August 4, 2026`.
- Today button.
- Previous-month button.
- Next-month button.

Navigation changes the displayed month only. It must not mutate any PM schedule, cycle, request, or queue record.

#### Monthly grid

The grid must contain seven Sunday-to-Saturday columns and enough week rows for the selected month.

Each date cell displays:

- Day number.
- A small task count when PM events exist.
- Up to a safe maximum number of compact event chips; remaining events are shown through a `+N more` affordance or the selected-date panel.
- Current-day highlight using the existing CMMS blue theme.
- Selected-day outline/highlight.

Each chip must use real CMMS data and a compact label:

- Pending manual queue: `PM - {schedule name}`
- Generated work order: `PM - {request number or schedule name}`
- Never expose an ICT label in this PM-only calendar.

Clicking a date selects it and refreshes the right-hand selected-date panel. Clicking an event opens either:

- the new manual queue detail page for a pending/cancelled/failed queue event; or
- the existing PM schedule or PM work-order detail page for a generated event.

#### Color and status legend

Use status colors that visually resemble the supplied reference while preserving current PM terminology:

| Calendar state | Chip treatment | Source |
| --- | --- | --- |
| Pending | dark CMMS navy | pending manual-generation queue |
| Scheduled / active | CMMS blue | existing PM work order status |
| Completed | green | existing PM work order/cycle completion |
| Overdue | red outline or red accent | derived overdue pending queue |
| Cancelled | neutral gray | cancelled manual queue |

The status must never be inferred from a new PM business rule. It is only a calendar presentation of existing work-order states and the new manual queue state.

#### Selected-date panel

The upper right panel must show:

- `TASKS` label and selected formatted date.
- Exact item count.
- Event rows for each PM item on the selected date.
- Schedule name, PM status, division/office, estimated or actual count, generated-by name, and a view-details link.
- Existing-CMMS empty state: `No preventive maintenance tasks scheduled.`

Pending queue rows display reschedule and cancel controls only when their persisted status is exactly `Pending` and the current user remains an authorized super admin.

#### Upcoming PM panel

The lower right panel must show the next seven days of PM events, ordered by date and then the existing PM ordering where applicable.

Each row includes:

- schedule/work-order title
- event date
- division or office
- PM status badge
- link to existing/new details page

The list contains PM-only records. It does not create, assign, or modify a work order.

#### Accessibility and empty states

- Use semantic buttons with labels for month navigation.
- Retain keyboard focus when month/date selection changes.
- Do not rely only on color; show status text/badges.
- Provide a readable event list below the grid for narrow screens and assistive technology.
- Keep an explicit empty state for a month, selected day, and upcoming panel with no PM events.

Weekly and daily views remain optional follow-up work after the monthly view, generated queue, and existing PM work-order links are verified.

### Dashboard

Add a compact calendar summary widget to the existing super-admin dashboard only. This matches current PM ownership and does not change other dashboard role visibility.

Each summary link must preserve the calendar filter in the URL:

- today
- upcoming
- week
- overdue
- completed

## Phase F - Audit and Notifications

### Audit logging

Use the existing audit-log system only for new queue operations:

- Manual PM generation scheduled
- Manual PM generation rescheduled
- Manual PM generation cancelled
- Scheduled PM generation executed
- Scheduled PM generation failed

Do not modify existing PM audit entries.

Because console execution has no HTTP authenticated user, the scheduled-run audit record must explicitly use the queue record's `generated_by` user ID and safe system request metadata. Do not rely on an HTTP-only audit helper inside the console runner.

### Notifications

Do not add a notification when a future queue row is merely saved.

When the due queue row runs, the existing `GeneratePMScheduleService` notifications remain authoritative. This preserves the current recipient logic and avoids duplicate notifications.

## Phase G - Tests

Add targeted tests before enabling the UI:

1. A super admin can create a future manual queue row.
2. A date today or in the past is rejected.
3. A duplicate PM configuration/date queue row is rejected.
4. A non-super-admin cannot access the new routes.
5. A Pending queue row can be rescheduled.
6. Generated, Processing, Cancelled, and Failed queue rows cannot be rescheduled.
7. A due queue row invokes the existing generation service exactly once.
8. A generated queue row stores generated time/count/cycle information.
9. Existing service cooldown behavior does not create duplicate PM requests.
10. Scheduler processing does not alter existing recurring PM command behavior.
11. Calendar data returns the required event fields and correct derived statuses.
12. Dashboard summary counts match calendar event data.

Every implementation phase must also run:

```bash
php artisan test
php artisan route:list
```

Manual verification is required after each completed phase.

## Rollout Sequence

1. Database/model/tests only.
2. Queue create and reschedule Actions/tests.
3. Daily command integration/tests.
4. Calendar read API/tests.
5. Calendar Blade/JavaScript UI.
6. Existing PM page "Schedule for Later" UI.
7. Super-admin dashboard summary.
8. Final manual browser and database comparison.
9. Small Git commit after every endpoint or isolated feature slice.

## Completion Criteria

The enhancement is complete only when:

- Generate Now behaves exactly as it did before.
- Existing automatic PM generation behaves exactly as it did before.
- A future PM generation can be queued, displayed, rescheduled while pending, cancelled while pending, and generated by the daily scheduler.
- The generated PM requests come only from the existing `GeneratePMScheduleService`.
- Calendar and dashboard values display the correct queue and existing PM-work-order state.
- Existing and new tests pass.
- No existing routes, request keys, database columns, permissions, notifications, or PM workflows regress.


## Right-Side Calendar Panel Specification (Reference Layout)

This section supersedes any earlier PM-only wording about the right-hand panels. The calendar must use the reference layout: one calendar grid on the left and three stacked cards on the right. All cards are presentation-only and use the combined PM and ICT event source and current-user visibility defined above.

### Desktop and mobile placement

- Desktop: the calendar grid occupies the main left column; the right column contains the cards in this exact order: selected-event detail, selected-day tasks, then upcoming next 7 days.
- Mobile: the same three cards appear below the calendar grid in the same order.
- The cards must use the current CMMS shell, colors, typography, spacing, responsive conventions and existing Font Awesome assets. Do not copy hard-coded sample data from the reference image.

### 1. Selected-event detail card

This is the top card. It is hidden until the user clicks a PM or ICT event chip, then shows the selected record without modifying it.

- Header: existing PM work-order/schedule identifier or ICT request number, title, and close button.
- Badges: visible PM or ICT type badge; existing status badge; existing priority badge only when the current record already has one.
- Details grid: existing date, time only if stored, assignee/requester/generated-by user where available, and existing office/division/location where available.
- Primary action: View Work Order for a PM work order, View PM Schedule for a manual queue/configuration record, or View ICT Request for an ICT record. It must use the existing route.
- Secondary Edit action: render only if the existing role, policy and route already allow edit. The calendar must not create any new edit authority.
- Closing the card clears the selected event only; it must not update a status, create PM records, assign personnel, send notifications, or write audit logs.

### 2. Selected-day TASKS card

This is the middle card. It is refreshed whenever the user selects a date or changes All Types, PM, or ICT.

- Header shows TASKS, the formatted selected date and the exact visible item count.
- Every row represents one visible PM or ICT event for that date and has a PM or ICT badge.
- A row shows the existing identifier, title, time only when stored, existing person field where available, and existing office/location field where available.
- Clicking a row selects that event and opens the selected-event detail card. It must use the same target/permission checks as a grid chip.
- With no visible event, show the filter-aware empty state: No maintenance tasks scheduled.

### 3. UPCOMING - NEXT 7 DAYS card

This is the bottom card. It lists visible PM and ICT events for the next seven calendar days, applying the same active type filter and existing date mapping as the main grid.

- Each row has a PM or ICT badge, existing title, date/time only when stored, and current record location/person data where available.
- Order uses date first, then the current underlying PM or ICT ordering. Do not invent a new query order.
- Clicking a row uses the same PM or ICT detail target as the relevant grid chip.
- The card is read-only: it cannot generate, reschedule, cancel, update, assign or otherwise mutate PM or ICT work.

### Shared right-panel safety rules

- All Types must show both PM and ICT records; PM and ICT filters must affect all three right-side cards as well as the grid.
- PM and ICT must have distinct type colors, but status text/badges must remain visible and come from existing record states.
- Do not synthesize times, priority, assignees, locations, request numbers, statuses or task counts when the current record does not provide them.
- Pending PM queue reschedule/cancel controls remain available only for persisted Pending rows and existing authorized super-admin users. ICT entries never gain schedule-edit controls from this calendar.
- No right-side panel may bypass the existing viewMaintenance or viewIct policy behavior.

---

## Implementation Status — August 4, 2026

This section records the **actual implementation state** versus the original plan. The combined calendar, manual PM queue, data-source fixes, and UX redesign described below have been implemented and committed (`33cb176`).

### Implemented features

#### Maintenance Calendar page (`/maintenance/calendar` and `/pm-schedules/calendar`)

- **Template-inspired layout** — the calendar grid occupies the left column and three stacked cards (Selected Event Detail, Day Tasks, Monthly Summary, Upcoming Next 7 Days) occupy the right column.
- **Combined PM/ICT view** — `All Types`, `PM`, and `ICT` filter buttons drive the grid, day-task list, and upcoming list together. PM and ICT chips use distinct colors (`#0038A8` for PM, `#10b981` for ICT).
- **Sub-nav strip** with live visibility-filtered counts for PM, ICT, Done, and Overdue.
- **Month/Year picker** — two `<select>` dropdowns let the user jump directly to any month/year (years from current−2 to current+3), replacing slow per-month navigation. Previous/Next arrows and a Today button remain.
- **Bigger calendar grid** — layout changed from `2fr 1fr` to `3fr 1fr`, and day-cell height increased from `100px` to `120px` for more readable event chips.
- **Compact event chips** — chip text is capped at 22 characters with an ellipsis (`…`) and a hover tooltip shows the full title. Chip CSS uses `text-overflow: ellipsis`, `white-space: nowrap`, `overflow: hidden`, and reduced font/padding to prevent the calendar cells from breaking.
- **Month dashboard summary card** — shows PM Tasks, ICT Tasks, Completed, and Overdue counts for the currently displayed month.
- **Calendar data Action (`GetMaintenanceCalendarDataAction`)**:
  - Queries **four** event sources: `pm_generation_schedules` queue rows, `pm_division_schedules` next-scheduled dates, existing PM work orders (`requests.type = Preventive Maintenance`), and existing ICT requests.
  - Supports month/year and filter parameters sent by the frontend.
  - Uses the existing `viewMaintenance` and `viewIct` policies so each role only receives events it can already view.
  - Falls back from `service_schedule_date` → `maintenance_date`/`date_received` → `created_at` exactly as the original plan requires.
- **PM Division Schedule as an event source** — after a PM cycle completes, `pm_division_schedules.next_scheduled_at` rows now appear on the calendar as scheduled PM events. The title uses the **short division code** (RID, AD, FMD, COA, CMD, VAD, WRED, OED) so the chip stays compact, while the full division name is preserved in the event's `office` field for the detail panel. The schedule name is added to `title` of the selected-day task list.
- **Short division names** in calendar chips — added `shortDivisionName()` mapping in `GetMaintenanceCalendarDataAction` to prevent long division labels from breaking the calendar grid.

#### PM manual scheduling queue (`pm_generation_schedules`)

- The `PMGenerationSchedule` model, migration, form requests (`StorePMGenerationScheduleRequest`, `UpdatePMGenerationScheduleRequest`), and Actions (`CreatePMGenerationScheduleAction`, `ReschedulePMGenerationScheduleAction`, `CancelPMGenerationScheduleAction`) are present.
- `PMScheduleController` exposes `scheduleLater`, `reschedulePMGeneration`, and `cancelPMGeneration` endpoints.
- The daily `pm:generate-scheduled` command remains untouched and continues to process both the automatic recurring cycle and the new manual queue as an additive step.
- **Bug fix**: `PMGenerationSchedule` previously used the `SoftDeletes` trait even though the `pm_generation_schedules` table has no `deleted_at` column. This caused a SQL error that silently broke the calendar data endpoint. Remove `SoftDeletes` from the model.

#### Data-source fixes (calendar now shows existing ICT/PM data)

- Removed the `created_at` month filter from the PM work-order and ICT queries. Previously, requests created in an earlier month but scheduled for the current month did not appear. The Action now fetches all matching requests and filters in PHP by the actual event date (`service_schedule_date`/`maintenance_date`/`date_received`).
- Grouped the PM queue `orWhere('status', 'Pending')` inside a proper `where(...)` closure so the queue query no longer fetches every pending row regardless of date.

#### PM Schedule detail page improvements

- Buttons (`Edit Schedule`, `View Calendar`, `Schedule for Later`) are now grouped inside the card header via a `card-actions` flex container.
- Added the modal CSS inline to `pm-schedules/show.blade.php` so the Schedule-for-Later modal is actually styled on that page (it previously depended on `maintenance-calendar.css`).
- Improved button hover/active styling to match the system's government-blue theme.

#### Topbar title fix

- `resources/views/maintenance-calendar/index.blade.php` now sets `@section('page-title', 'Maintenance Calendar')`, so the topbar no longer defaults to "Dashboard".

### Deviations from the original plan

1. **Scope** — the original plan (above) described a PM-only first release and optional combine with ICT later. The implemented calendar is already a combined PM/ICT calendar. This is an intentional, approved expansion, not a regression.
2. **Right-side panels** — the original plan listed three right-side cards. The implementation adds a fourth "Monthly Summary" card and a single "Add" task card used for the Schedule modal.
3. **Manual queue UI path** — the original plan expected the "Schedule for Later" control on the PM generation page. The control was initially added there and is present in the committed `show.blade.php`, but the user requested that the redundant manual-queue entry UI be removed. A follow-up change will remove the Schedule-for-Later button/modal from the PM schedule page and make the calendar's "Add" button the single manual scheduling entry point.
4. **Schedule modal** — the committed calendar includes a PM/ICT type-toggle scheduling modal that creates an event locally. The user requested that the modal become **PM-only** (remove the type toggle and Location field), that ICT be **view/assign-only** (assign IT IT dropdown), and that PM scheduling POST to the server instead of only creating a local event. Those changes are planned but not yet applied.

### Phase 1: Core Calendar Cleanup — ✅ COMPLETE (August 5, 2026)

The following Phase 1 items are now **implemented, tested, and committed**:

| # | Item | Commit(s) |
|---|------|-----------|
| 1 | Remove "Schedule for Later" button + modal + JS from `pm-schedules/show.blade.php` | `d20c8d2` |
| 2 | Make calendar Schedule modal PM-only (remove type toggle + Location) | `d20c8d2` |
| 3 | Modal submit POSTs to server (creates `pm_generation_schedules` row) | `d20c8d2` |
| 4 | ICT = view/assign only (IT personnel dropdown + assign button) | `4887620`, `cd84dcd` |
| 5 | ICT assign hides when already assigned; super admin can self-assign | `cd84dcd` |
| 6 | Toast notifications + assigned personnel name in success message | `b08f095` |
| 7 | Color-based status chips (completed/ongoing/overdue) for PM & ICT | `a1d2aa8`, `d879a1a` |
| 8 | Layout stability: `table-layout: fixed`, chip text constraints, day-cell overflow hidden | `a1d2aa8` |
| 9 | Right panel updates on date switch; month nav resets selected date | `a1d2aa8` |
| 10 | Past dates cannot be edited/added (add button hidden) | `a415356` |
| 11 | Convert calendar from HTML `<table>` to **CSS Grid** (definitive fix for cell expansion) | `a8a8e21` |

### Pending / next steps

The following items remain for upcoming phases:

1. **Consolidate Maintenance Calendar layout** with other super admin modules (card body structure: `polish-card`, `card-header-accent`, `card-body-content`, consistent header/subnav).
2. **Add automated tests** for the combined-calendar data Action and the ICT assignment flow.
3. **Phase 2: Consolidate Create PM Schedule → Calendar** — remove standalone `pm-schedules/create.blade.php`, update calendar modal fields to match Create PM Schedule (Schedule Name, Target Division, Frequency), POST to `pm-schedules.store`, preserve one-active-per-branch check.
4. **PM-only Upcoming 7 Days panel** — the "Upcoming — Next 7 Days" panel should show PM events only (ICT has no future schedule date).
5. **Sidebar navigation** — add "Maintenance Calendar" link in the Super Admin Modules section of `app.blade.php`.

### Files introduced or changed in this implementation (commit `33cb176`)

| File | Purpose |
| --- | --- |
| `app/Models/PMGenerationSchedule.php` | Manual PM queue model (no SoftDeletes) |
| `database/migrations/2026_08_04_100000_create_pm_generation_schedules_table.php` | Queue table |
| `app/Http/Requests/StorePMGenerationScheduleRequest.php` | Queue create validation |
| `app/Http/Requests/UpdatePMGenerationScheduleRequest.php` | Queue reschedule validation |
| `app/Actions/PMGenerationSchedule/CreatePMGenerationScheduleAction.php` | Create queue row |
| `app/Actions/PMGenerationSchedule/ReschedulePMGenerationScheduleAction.php` | Reschedule pending queue |
| `app/Actions/PMGenerationSchedule/CancelPMGenerationScheduleAction.php` | Cancel pending queue |
| `app/Actions/PMGenerationSchedule/GetMaintenanceCalendarDataAction.php` | Combined calendar JSON data |
| `resources/views/maintenance-calendar/index.blade.php` | Calendar page (combined PM/ICT) |
| `resources/css/maintenance-calendar.css` | Calendar styling (compact chips, month/year selects, bigger grid) |
| `resources/js/maintenance-calendar.js` | Calendar behavior (month/year picker, chip truncation, local schedule modal) |
| `resources/views/pm-schedules/show.blade.php` | PM schedule detail page (grouped buttons, inline modal CSS) |
| `app/Console/Commands/GenerateScheduledPM.php` | Additive manual-queue processing in the daily command |
| `app/Http/Controllers/Maintenance/MaintenanceController.php` | `calendar` + `calendarEvents` endpoints |
| `app/Http/Controllers/Maintenance/PMScheduleController.php` | `scheduleLater`, `reschedule`, `cancel` endpoints |
| `routes/web.php` | Calendar + queue routes |
| `vite.config.js` | Vite entry for calendar CSS/JS |

---

## Phased Implementation Plan (Long-Term Roadmap)

This section defines the **step-by-step phased roadmap** for the remaining work. Each phase is designed to be independently testable, reversible, and committed separately. The goal is long-term stability — not a quick fix.

### Phase 1: Core Calendar Cleanup (Foundation — Safety First)

**Goal:** Fix the inherited anti-patterns before building on top of them.

1. **Remove "Schedule for Later" button + modal + JS from `resources/views/pm-schedules/show.blade.php`**
   - The button is redundant because the calendar "Add" button becomes the single manual scheduling entry point.
   - Remove `btn-schedule`, `#scheduleModal`, and the related JS event listeners/submit handler.
2. **Make the calendar Schedule modal PM-only**
   - Remove the PM/ICT type toggle.
   - Remove the Location field.
   - Keep: Task Title, Scheduled Date, Time, Assignee, Priority.
3. **Modal submit → POST to server**
   - Change `handleModalSubmit()` in `resources/js/maintenance-calendar.js` to POST to the `pm-schedules.schedule-later` route instead of only creating a local event.
   - This creates a real `pm_generation_schedules` row.
4. **ICT → view/assign only (no create in calendar)**
   - Clicking an ICT chip opens the event detail panel with:
     - Title: requestor name + short division code (e.g., `Juan Dela Cruz — RID`)
     - Date/time as submitted (actual request date)
     - IT personnel `<select>` dropdown (same data as `assign-panel.blade.php`)
     - Assign button that posts to the existing assign route
     - View Request link

**Why first:** This phase fixes the inherited anti-patterns. If not fixed, all subsequent changes stack on a wrong base.

---

### Phase 2: Consolidate Create PM Schedule → Calendar

**Goal:** One entry point for all PM scheduling — the calendar "Add" button.

1. **Remove `resources/views/pm-schedules/create.blade.php`** — delete the standalone page.
2. **Update calendar modal fields** to match the Create PM Schedule form:
   - Schedule Name (text input)
   - Target Division (dropdown: All Divisions, RID, AD, FMD, COA, CMD, VAD, WRED, OED)
   - Frequency (dropdown: Monthly, Quarterly, Semi-annual, Annual)
3. **POST to `pm-schedules.store`** — reuse the same `StorePMScheduleAction`.
4. **Preserve the "one active schedule per branch" check** in `StorePMScheduleAction`.
5. **Redirect to the PM schedule show page** after creation.

**Why second:** The modal must be cleaned up in Phase 1 before its fields are changed to become the PM schedule config form. Once consolidated, there is no duplicate UI.

---

### Phase 3: ICT Assignment Flow (Complete)

**Goal:** Complete the ICT experience in the calendar.

1. **ICT event detail panel:**
   - Title: requestor name + short division (e.g., `Juan Dela Cruz — RID`)
   - Date/time: actual submission date
   - Status badge
2. **IT personnel dropdown** — same data as `assign-panel.blade.php`.
3. **Assign button** → POST to the existing assign route (`ict.assign-it`).
4. **View Request link** → existing ICT request detail.

**Why third:** This does not affect the PM flow. After PM is consolidated in Phase 2, it is safe to focus on ICT.

---

### Phase 4: Data Integrity & Edge Cases

**Goal:** Ensure nothing breaks in various scenarios.

1. **Duplicate schedule detection** — unique `[schedule_name]` or `[pm_schedule_id, scheduled_date]`.
2. **Active schedule limit** — one active schedule per branch (`StorePMScheduleAction` check).
3. **PM generation cooldown** — do not run if `next_scheduled_at` is still in the future.
4. **Asset changes mid-cycle** — handle new assets added while a cycle is active.
5. **Queue status transitions** — Pending → Processing → Generated/Failed/Cancelled handled correctly.

**Why fourth:** Once the UI flow is stable, harden the business logic.

---

### Phase 5: Automated Tests

**Goal:** Lock behavior so it does not regress.

1. **Combined calendar data Action tests:**
   - ICT request appears on its request date.
   - ICT with `service_schedule_date` appears on that date.
   - PM + ICT appear together (All Types).
   - PM/ICT filters return matching type only.
   - End user cannot see another user's ICT event.
2. **PM schedule creation via calendar** — fields, validation, one-active-per-branch.
3. **ICT assignment flow** — assign works, re-assign works.
4. **Manual queue due dates** — due triggers generation, overdue shows correctly.

**Why fifth:** Once features are stable, lock them with tests.

---

### Phase 6: Documentation, Commit & Push

**Goal:** Record everything and deploy.

1. **Update `docs/PM_CALENDAR_IMPLEMENTATION_PLAN.md`** — final state.
2. **Commit each phase** (small commits per phase).
3. **Push to remote.**
4. **Optional: Dashboard widget integration** (super-admin summary widget from the plan).

---

### Priority Recommendation

**Start with Phase 1** because:
- It fixes the inherited anti-patterns.
- It does not affect the PM generation logic.
- Each step is small — easy to test and revert.
- All subsequent phases depend on it.

