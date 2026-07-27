@php $pageTitle = 'PM Work Orders'; @endphp
@extends('layouts.app')
@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('styles')
<style nonce="{{ $cspNonce }}">
    .orders-card { background:white; border-radius:14px; border:1px solid #e2e8f0; padding:24px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05); }
    .orders-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:20px; }
    .orders-title { font-size:20px; font-weight:800; color:#1e293b; margin:0; }
    .back-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; background:#f1f5f9; color:#475569; border-radius:8px; text-decoration:none; font-size:12px; font-weight:700; }
    .back-btn:hover { background:#e2e8f0; }
    .filter-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
    .filter-btn { padding:6px 14px; border-radius:20px; font-size:11px; font-weight:700; text-decoration:none; border:2px solid #e2e8f0; color:#64748b; background:white; cursor:pointer; transition:all 0.2s; }
    .filter-btn:hover { border-color:#0038A8; color:#0038A8; }
    .filter-btn.active { border-color:#0038A8; color:#0038A8; background:#eff6ff; }
    .table-wrap { overflow-x:auto; border-radius:10px; border:1px solid #e2e8f0; }
    .table-orders { width:100%; border-collapse:collapse; min-width:700px; }
    .thead-row { background:linear-gradient(135deg,#f8fafc,#f1f5f9); }
    .th { padding:12px 14px; font-size:10px; font-weight:700; text-transform:uppercase; color:#475569; text-align:left; border-bottom:2px solid #0038A8; }
    .tr { border-bottom:1px solid #f1f5f9; transition:background 0.15s; }
    .tr:hover { background:#f8fafc; }
    .td { padding:11px 14px; font-size:13px; color:#1e293b; }
    .td-num a { color:#0038A8; font-weight:700; text-decoration:none; font-size:12px; }
    .status-pill { display:inline-block; padding:3px 10px; border-radius:20px; font-size:9px; font-weight:800; text-transform:uppercase; }
    .pill-scheduled { background:#fef3c7; color:#92400e; }
    .pill-ongoing { background:#dbeafe; color:#1e40af; }
    .pill-completed { background:#dcfce7; color:#166534; }
    .pill-default { background:#f1f5f9; color:#64748b; }
    .empty-state { text-align:center; padding:40px; color:#94a3b8; }
    .action-btn { display:inline-flex; align-items:center; gap:5px; padding:6px 12px; background:#0038A8; color:white; border-radius:6px; font-size:10px; font-weight:700; text-decoration:none; }
    .action-btn:hover { background:#002d8c; }
    .paginator { margin-top:16px; }
    .count-badge { background:#0038A8; color:white; border-radius:20px; padding:2px 8px; font-size:11px; font-weight:700; margin-left:8px; }
</style>
@endsection

@section('content')
<div class="page-wrap">
    <div class="orders-card">

        <div class="orders-header">
            <div>
                <h1 class="orders-title">
                    PM Work Orders
                    <span id="ordersCount" class="count-badge">--</span>
                </h1>
                <p style="margin:4px 0 0;font-size:12px;color:#64748b;">All auto-generated preventive maintenance work orders</p>
            </div>
            <a href="{{ route('pm-schedules.index') }}" class="back-btn">
                <i class="fa-solid fa-arrow-left"></i> Back to PM Schedules
            </a>
        </div>

        {{-- Status Filter --}}
        <div class="filter-bar" id="filterBar">
            <button data-status="all" class="filter-btn active">All</button>
            <button data-status="Scheduled" class="filter-btn">To Do</button>
            <button data-status="Ongoing" class="filter-btn">Ongoing</button>
            <button data-status="Completed" class="filter-btn">Completed</button>
        </div>

        <div class="table-wrap">
            <table class="table-orders">
                <thead>
                    <tr class="thead-row">
                        <th class="th">PM #</th>
                        <th class="th">Employee</th>
                        <th class="th">Division</th>
                        <th class="th">Assigned To</th>
                        <th class="th">Date Generated</th>
                        <th class="th">Status</th>
                        <th class="th" style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <tr><td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <div id="ordersPagination" class="paginator"></div>
    </div>
</div>
@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
const ORDERS_DATA_URL = '{{ route("pm-schedules.orders.data") }}';
const CURRENT_USER_ID = {{ Auth::user()->id }};
let ordersCurrentPage = 1;
let ordersLastPage = 1;
let ordersCurrentStatus = 'all';

async function loadOrders(page) {
    page = page || 1;
    const params = new URLSearchParams();
    params.set('status', ordersCurrentStatus);
    params.set('page', page);
    params.set('per_page', 20);

    const tbody = document.getElementById('ordersTableBody');
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:#94a3b8;"><i class="fa-solid fa-circle-notch fa-spin"></i> Loading...</td></tr>';

    try {
        const response = await fetch(ORDERS_DATA_URL + '?' + params.toString(), {
            credentials: 'include',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();

        if (result.success) {
            ordersCurrentPage = result.current_page;
            ordersLastPage    = result.last_page;
            renderOrdersTable(result.orders);
            renderOrdersPagination(result.total);
        } else {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:#ef4444;">Failed to load orders.</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:24px;color:#ef4444;">Error loading orders.</td></tr>';
    }
}

function renderOrdersTable(orders) {
    const tbody = document.getElementById('ordersTableBody');
    document.getElementById('ordersCount').textContent = '...';

    if (!orders.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><i class="fa-solid fa-clipboard-list" style="font-size:40px;margin-bottom:12px;opacity:0.4;"></i><p style="font-size:14px;font-weight:600;">No work orders found</p></td></tr>';
        return;
    }

    // Sort by status (To Do first)
    const sorted = [...orders].sort((a, b) => {
        const orderMap = { 'Scheduled': 0, 'Ongoing': 1, 'Awaiting Signature': 2, 'Completed': 3 };
        return (orderMap[a.status] ?? 99) - (orderMap[b.status] ?? 99);
    });

    tbody.innerHTML = sorted.map(order => {
        const created = new Date(order.created_at);
        const dateStr = created.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

        let pillClass = 'pill-default';
        let pillLabel = order.status;
        if (order.status === 'Scheduled') { pillClass = 'pill-scheduled'; pillLabel = 'To Do'; }
        else if (order.status === 'Ongoing') { pillClass = 'pill-ongoing'; pillLabel = 'Ongoing'; }
        else if (order.status === 'Completed') { pillClass = 'pill-completed'; pillLabel = 'Completed'; }

        const assignedName = order.assigned_to ? order.assigned_to.full_name : '—';

        return `<tr class="tr">
            <td class="td td-num"><a href="/requests/maintenance/${order.id}/edit">${order.request_number}</a></td>
            <td class="td">${order.requestor_name || '—'}</td>
            <td class="td" style="color:#475569;font-size:12px;">${order.office || '—'}</td>
            <td class="td">${assignedName}</td>
            <td class="td" style="font-size:12px;color:#64748b;">${dateStr}</td>
            <td class="td"><span class="status-pill ${pillClass}">${pillLabel}</span></td>
            <td class="td" style="text-align:center;">
                ${order.status === 'Scheduled' && order.assigned_to && order.assigned_to.id == CURRENT_USER_ID
                    ? `<a href="/requests/maintenance/${order.id}/start" class="action-btn">
                         <i class="fa-solid fa-play"></i> Start
                       </a>`
                    : `<a href="/requests/maintenance/${order.id}/edit" class="action-btn">
                         <i class="fa-solid fa-arrow-right"></i> View
                       </a>`}
            </td>
        </tr>`;
    }).join('');
}

function renderOrdersPagination(total) {
    const container = document.getElementById('ordersPagination');
    document.getElementById('ordersCount').textContent = total;

    const totalPages = ordersLastPage;
    if (totalPages <= 1) { container.innerHTML = ''; return; }

    const btnStyle = (active, disabled) =>
        `padding:5px 10px;border:1px solid ${active ? '#0038A8' : '#cbd5e1'};border-radius:4px;` +
        `background:${active ? '#0038A8' : disabled ? '#f1f5f9' : 'white'};` +
        `color:${active ? 'white' : disabled ? '#94a3b8' : '#1e293b'};` +
        `cursor:${disabled ? 'default' : 'pointer'};font-size:12px;font-weight:700;`;

    let html = `<div style="display:flex;align-items:center;justify-content:space-between;margin-top:16px;">`;
    html += `<span style="font-size:12px;color:#64748b;">${total} order${total !== 1 ? 's' : ''}</span>`;
    html += `<div style="display:flex;gap:4px;">`;

    html += `<button onclick="loadOrders(${ordersCurrentPage - 1})" style="${btnStyle(false, ordersCurrentPage <= 1)}" ${ordersCurrentPage <= 1 ? 'disabled' : ''}>&lsaquo; Prev</button>`;

    let start = Math.max(1, ordersCurrentPage - 2);
    let end   = Math.min(totalPages, ordersCurrentPage + 2);
    if (start > 1) {
        html += `<button onclick="loadOrders(1)" style="${btnStyle(false, false)}">1</button>`;
        if (start > 2) html += `<span style="padding:5px 4px;color:#94a3b8;font-size:12px;">&hellip;</span>`;
    }
    for (let i = start; i <= end; i++) {
        html += `<button onclick="loadOrders(${i})" style="${btnStyle(i === ordersCurrentPage, false)}">${i}</button>`;
    }
    if (end < totalPages) {
        if (end < totalPages - 1) html += `<span style="padding:5px 4px;color:#94a3b8;font-size:12px;">&hellip;</span>`;
        html += `<button onclick="loadOrders(${totalPages})" style="${btnStyle(false, false)}">${totalPages}</button>`;
    }

    html += `<button onclick="loadOrders(${ordersCurrentPage + 1})" style="${btnStyle(false, ordersCurrentPage >= totalPages)}" ${ordersCurrentPage >= totalPages ? 'disabled' : ''}>Next &rsaquo;</button>`;
    html += `</div></div>`;
    container.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', function() {
    loadOrders(1);

    // Filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            ordersCurrentStatus = this.dataset.status;
            loadOrders(1);
        });
    });
});
</script>
@endsection