@extends('layouts.app')

@section('title', 'ICT Repair Requests | NCMB ICT System')
@section('page-title', 'ICT Repair Requests')

@section('styles')
    <style nonce="{{ $cspNonce }}">
        .requests-container {
            width: 100%;
            margin-top: -10px;
            animation: fadeInSlide 0.4s ease-out;
        }

        .polish-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .card-header-accent {
            background: #f8fafc;
            padding: 20px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body-content {
            padding: 25px 30px;
        }

        .filter-ribbon {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .ribbon-input {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            transition: border-color 0.2s;
        }

        .ribbon-input:focus {
            border-color: #0038A8;
            box-shadow: 0 0 0 3px rgba(0, 56, 168, 0.05);
        }

        .gov-table-premium {
            width: 100%;
            border-collapse: collapse;
        }

        .gov-table-premium th {
            background: #f1f5f9;
            padding: 12px 15px;
            font-size: 11px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }

        .gov-table-premium td {
            padding: 15px;
            font-size: 13px;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
        }

        .gov-table-premium tr.tr-hover-row { transition: all 0.2s; position: relative; }
        .gov-table-premium tr.tr-hover-row:hover { background: #f8fafc !important; transform: scale(1.002); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .gov-table-premium tr.tr-hover-row:hover td:first-child { box-shadow: inset 4px 0 0 #0038A8; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }

        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .sp-pending { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; box-shadow: 0 2px 4px rgba(234, 88, 12, 0.15); }
        .sp-ongoing { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; box-shadow: 0 2px 4px rgba(29, 78, 216, 0.15); }
        .sp-completed { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15); }
        .sp-rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.15); }
        .sp-approved { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; box-shadow: 0 2px 4px rgba(22, 101, 52, 0.1); }

        .btn-view-modern {
            padding: 6px 16px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 20px;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-view-modern:hover {
            background: #1d4ed8;
            color: white;
            border-color: #1d4ed8;
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(29, 78, 216, 0.2);
        }
        .req-header-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
        .req-header-sub { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .req-header-actions { display: flex; gap: 10px; }
        .btn-ict-create { background: #0038A8; color: white; text-decoration: none; padding: 12px 20px; border-radius: 4px; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 8px; min-height: 44px; }
        .search-wrap { position: relative; flex: 1; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; }
        .search-input { width: 100%; padding-left: 35px; }
        .filter-select { width: 180px; }
        .table-wrap { overflow-x: auto; }
        .td-center { text-align: center !important; vertical-align: middle; }
        .th-status { width: 140px; text-align: center !important; }
        .th-action { width: 130px; text-align: center !important; }
        .td-id { font-weight: 700; color: #0038A8; }
        .td-type { font-weight: 700; font-size: 12px; }
        .td-desc { color: #64748b; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .td-date { color: #64748b; font-size: 12px; }
        .td-remarks { color: #94a3b8; font-size: 12px; font-style: italic; }
        .empty-row { text-align: center; padding: 60px; color: #94a3b8; }
        .empty-icon-big { font-size: 40px; display: block; margin-bottom: 15px; opacity: 0.2; }
        .pagination-wrap { margin-top: 20px; }
        @media (max-width: 767px) {
            .card-header-accent { flex-direction: column !important; gap: 10px !important; }
            .filter-ribbon { flex-direction: column !important; gap: 10px !important; }
            .table-wrap, .scroll-x { overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; }
            input, select, textarea { min-height: 48px !important; font-size: 15px !important; }
            .btn, button:not(#sidebarToggle):not(#notifBell):not(.swal2-confirm):not(.swal2-cancel) { min-height: 48px !important; width: 100% !important; font-size: 14px !important; }
            .td-desc { max-width: 140px !important; }
        }
    </style>
@endsection

@section('content')
<div class="requests-container">
    
    <div class="polish-card">
        <!-- HEADER STRIP -->
        <div class="card-header-accent">
            <div>
                <h3 class="req-header-title">My Request Repository</h3>
                <p class="req-header-sub">Track and manage all ICT support requests submitted by your account.</p>
            </div>
            @if(Auth::user()->role === 'user')
            <div class="req-header-actions">
                <a href="{{ route('ict.create') }}" class="btn-ict-create">
                    <i class="fa-solid fa-plus"></i> ICT Request
                </a>

            </div>
            @endif
        </div>

        <div class="card-body-content">
            <!-- FILTERS -->
            <div class="filter-ribbon">
                <div class="search-wrap">
                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                    <input type="text" id="searchRequest" placeholder="Search by request ID or description..." class="ribbon-input search-input">
                </div>
                <select id="filterStatus" class="ribbon-input filter-select">
                    <option value="">All Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Ongoing">Ongoing</option>
                    <option value="Completed">Completed</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <div class="table-wrap">
                <table class="gov-table-premium">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Description</th>
                            <th>Submission Date</th>
                            <th class="th-status">Current Status</th>
                            <th>Feedback/Remarks</th>
                            <th class="th-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                        <tr class="tr-hover-row">
                            <td class="td-id">{{ $req->display_number ?? $req->request_number }}</td>
                            <td class="td-desc" title="{{ $req->description }}">
                                {{ $req->description ?: 'N/A' }}
                            </td>
                            <td class="td-date">
                                {{ $req->created_at->format('M d, Y') }}
                            </td>
                            <td class="td-center">
                                <span class="status-pill sp-{{ strtolower($req->status) }}">
                                    {{ $req->status }}
                                </span>
                            </td>
                            <td class="td-remarks">
                                {{ $req->remarks ?: '---' }}
                            </td>
                            <td class="td-center">
                                <a href="{{ route('ict.edit', $req->id) }}" class="btn-view-modern">
                                    <i class="fa-solid fa-folder-open"></i> Open
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="empty-row">
                                <i class="fa-solid fa-clipboard-question empty-icon-big"></i>
                                No requests found in your repository.
                                @if(Auth::user()->role === 'user')
                                    <div style="margin-top:16px;">
                                        <a href="{{ route('ict.create') }}" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#0038A8;color:white;border-radius:6px;font-size:13px;font-weight:700;text-decoration:none;">
                                            <i class="fa-solid fa-plus"></i> Submit Your First Request
                                        </a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="pagination-wrap">
                    {{ $requests->links() }}
                </div>
            </div>
        </div>
    </div>

</div>

<script nonce="{{ $cspNonce }}">
function filterRequests() {
    const searchInput = document.getElementById('searchRequest').value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value;
    const tableRows = document.querySelectorAll('tbody tr');

    tableRows.forEach(row => {
        if (row.cells.length < 4) return;

        const requestId = row.cells[0].textContent.toLowerCase();
        const description = row.cells[1].textContent.toLowerCase();
        const status = row.cells[3].textContent.trim();

        const matchesSearch = requestId.includes(searchInput) || description.includes(searchInput);
        const matchesStatus = statusFilter === "" || status === statusFilter;

        row.style.display = (matchesSearch && matchesStatus) ? "" : "none";
    });
}
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('searchRequest').addEventListener('keyup', filterRequests);
    document.getElementById('filterStatus').addEventListener('change', filterRequests);
});
</script>
@endsection
