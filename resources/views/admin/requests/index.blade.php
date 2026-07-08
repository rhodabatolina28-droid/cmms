@extends('layouts.app')

@section('title', 'Manage ' . (Auth::user()->department ? 'Department' : (Auth::user()->office ? 'Office' : 'Division')) . ' Requests | NCMB ICT System')
@section('page-title', 'Manage Requests')

@section('styles')
    <style nonce="{{ $cspNonce }}">
        .admin-request-container {
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

        .filter-ribbon {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            flex-wrap: wrap;
        }

        .ribbon-input {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            transition: all 0.2s;
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
            padding: 12px 15px;
            font-size: 13px;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
        }

        .gov-table-premium tr.tr-hover-row { transition: all 0.2s; position: relative; }
        .gov-table-premium tr.tr-hover-row:hover { background: #f8fafc !important; transform: scale(1.002); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .gov-table-premium tr.tr-hover-row:hover td:first-child { box-shadow: inset 4px 0 0 #0038A8; border-top-left-radius: 4px; border-bottom-left-radius: 4px; }

        /* Status Badges */
        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .sp-pending { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; box-shadow: 0 2px 4px rgba(194, 65, 12, 0.15); }
        .sp-ongoing { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; box-shadow: 0 2px 4px rgba(29, 78, 216, 0.15); }
        .sp-completed { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.15); }
        .sp-rejected { background: #fef2f2; color: #b91c1c; border: 1px solid #fee2e2; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.15); }

        .btn-action-modern {
            padding: 8px 14px;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-action-modern:hover {
            background: #0038A8;
            border-color: #0038A8;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 56, 168, 0.2);
        }
        
        .btn-action-modern:active {
            transform: scale(0.97);
        }

        /* Modal Overlays */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-card {
            background: white;
            border-radius: 15px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: modalPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .modal-header {
            padding: 20px 25px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body { padding: 25px; }

        .form-label-gov {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .form-input-gov {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
            outline: none;
        }

        .form-input-gov:focus {
            border-color: #0038A8;
            box-shadow: 0 0 0 3px rgba(0, 56, 168, 0.05);
        }
        .ad-title-icon { margin-right: 10px; color: #0038A8; }
        .ad-title { margin: 0; font-size: 18px; font-weight: 800; color: #1e293b; }
        .ad-em { font-weight: 800; color: #0038A8; }
        .ad-subtitle { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .ad-location-badge { font-size: 11px; font-weight: 800; color: #475569; background: #f1f5f9; padding: 4px 12px; border-radius: 99px; }
        .ad-body-pad { padding: 25px 30px; }
        .ad-search-wrap { position: relative; flex: 1; min-width: 300px; }
        .ad-search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; }
        .ad-search-input { width: 100%; padding-left: 35px; }
        .ad-filter-select { width: 180px; }
        .ad-filter-status { width: 160px; }
        .ad-table-wrap { overflow-x: auto; }
        .ad-td-id { font-weight: 800; color: #0038A8; }
        .ad-td-sub { font-size: 11px; color: #64748b; font-style: italic; }
        .ad-td-type { font-weight: 600; color: #475569; }
        .ad-td-name { color: #1e293b; font-weight: 700; }
        .ad-td-office { color: #475569; }
        .ad-td-date { color: #64748b; font-size: 12px; }
        .ad-td-center { text-align: center !important; vertical-align: middle; }
        .ad-review-icon-green { color: #047857; font-size: 14px; }
        .ad-review-icon-red { color: #b91c1c; font-size: 14px; }
        .ad-review-icon-gray { color: #cbd5e1; font-size: 14px; }
        .ad-assigned-name { font-weight: 700; color: #0038A8; }
        .ad-assigned-none { background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 99px; font-size: 10px; font-weight: 800; }
        .ad-assigned-na { color: #94a3b8; font-size: 12px; }
        .ad-action-cell { display: flex; gap: 6px; justify-content: center; }
        .ad-empty { padding: 60px; text-align: center; color: #94a3b8; }
        .ad-empty-icon { font-size: 40px; margin-bottom: 15px; display: block; opacity: 0.5; }
        .ad-empty-text { font-weight: 700; }
        .ad-pagination { margin-top: 20px; }
        .ad-modal-title { margin: 0; font-size: 16px; font-weight: 800; color: #1e293b; }
        .ad-modal-close { background: none; border: none; font-size: 20px; color: #94a3b8; cursor: pointer; }
        .ad-modal-hint { font-size: 12px; color: #475569; background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 6px; margin: 0 0 16px; line-height: 1.5; }
        .ad-modal-field { margin-bottom: 20px; }
        .ad-textarea-remarks { min-height: 120px; resize: none; }
        .ad-modal-footer { padding: 20px 25px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; }
        .ad-btn-discard { padding: 10px 20px; }
        .ad-btn-save { padding: 10px 25px; background: #0038A8; color: white; border: none; }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            /* Header */
            .card-header-accent { 
                flex-direction: column !important; 
                align-items: flex-start !important; 
                gap: 12px !important; 
                padding: 16px 20px !important; 
            }
            .ad-title { font-size: 16px !important; }
            .ad-subtitle { font-size: 11px !important; }
            .ad-location-badge { font-size: 10px !important; padding: 4px 10px !important; }

            /* Filter ribbon - stack vertically */
            .filter-ribbon { 
                flex-direction: column !important; 
                gap: 10px !important; 
                padding: 12px !important; 
            }
            .ad-search-wrap { width: 100% !important; min-width: 0 !important; }
            .ad-search-input { 
                font-size: 15px !important; 
                padding: 12px 12px !important; 
                padding-left: 38px !important; 
                min-height: 48px !important;
                border-radius: 8px !important;
            }
            .ad-filter-select, .ad-filter-status { 
                width: 100% !important; 
                font-size: 14px !important; 
                padding: 12px !important; 
                min-height: 48px !important;
                border-radius: 8px !important;
            }
            .ad-search-icon {
                font-size: 14px !important;
                left: 14px !important;
            }

            /* Table mobile */
            .gov-table-premium th, 
            .gov-table-premium td { 
                padding: 8px 10px !important; 
                font-size: 11px !important; 
            }
            .gov-table-premium th { 
                font-size: 10px !important; 
                letter-spacing: 0.3px !important; 
            }
            .ad-td-id { font-size: 12px !important; }
            .ad-td-sub { font-size: 10px !important; }
            .ad-td-type { font-size: 11px !important; }
            .ad-td-name { font-size: 11px !important; }
            .ad-td-office { font-size: 11px !important; }
            .ad-td-date { font-size: 10px !important; }

            /* Status pills */
            .status-pill { 
                font-size: 9px !important; 
                padding: 3px 8px !important; 
            }

            /* Action buttons - larger touch target */
            .btn-action-modern { 
                padding: 8px 12px !important; 
                font-size: 12px !important; 
                min-width: 44px !important; 
                min-height: 44px !important; 
            }

            /* Assigned cells */
            .ad-assigned-name { font-size: 11px !important; }
            .ad-assigned-none { font-size: 9px !important; padding: 3px 8px !important; }

            /* Review icons */
            .ad-review-icon-green, 
            .ad-review-icon-red, 
            .ad-review-icon-gray { 
                font-size: 16px !important; 
            }

            /* Row tap feedback */
            .gov-table-premium tr:active { background: #f1f5f9 !important; }

            /* Action icon buttons */
            .btn-action-modern i { font-size: 13px !important; }
            .ad-td-id { display: inline-block !important; max-width: 100px !important; white-space: nowrap !important; overflow: hidden !important; text-overflow: ellipsis !important; }

            /* Pagination */
            .ad-pagination { margin-top: 16px !important; }
            .ad-pagination nav { 
                display: flex !important; 
                flex-wrap: wrap !important; 
                gap: 4px !important; 
                justify-content: center !important; 
            }
            /* Touch targets & font sizes */
            input, select, textarea { min-height: 48px !important; font-size: 15px !important; }
            .btn-action-modern { min-height: 44px !important; min-width: 44px !important; }
            .filter-ribbon { flex-direction: column !important; gap: 10px !important; }
        }
    </style>
@endsection

@section('content')
<div class="admin-request-container">
    <div class="polish-card">
        <!-- HEADER STRIP -->
        <div class="card-header-accent">
            <div>
                <h3 class="ad-title">
                    <i class="fa-solid fa-clipboard-list ad-title-icon"></i>
                    {{ Auth::user()->department ? 'Department' : (Auth::user()->office ? 'Office' : 'Division') }} Request Management
                </h3>
                <p class="ad-subtitle">
                    Currently managing tickets for <span class="ad-em">{{ Auth::user()->department ?: (Auth::user()->office ?: Auth::user()->region) }}</span>.
                </p>
            </div>
            <div class="ad-location-badge">
                <i class="fa-solid fa-location-dot"></i> {{ Auth::user()->department ? 'DEPT' : (Auth::user()->office ?: Auth::user()->region) }}
            </div>
        </div>

        <div class="ad-body-pad">
            <!-- FILTER RIBBON -->
            <div class="filter-ribbon">
                <div class="ad-search-wrap">
                    <i class="fa-solid fa-magnifying-glass ad-search-icon"></i>
                    <input type="text" id="searchRequest" placeholder="Search by ID, requestor, or office..." class="ribbon-input ad-search-input">
                </div>

                <select id="filterStatus" class="ribbon-input ad-filter-status">
                    <option value="">All Status</option>
                    <option value="Pending">Pending</option>
                    <option value="Ongoing">Ongoing</option>
                    <option value="Completed">Completed</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <div class="ad-table-wrap">
                <table class="gov-table-premium">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Requestor</th>
                            <th>Date Filed</th>
                            <th class="ad-td-center">Status</th>
                            <th>IT Assigned</th>
                            <th class="ad-td-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="requestTable">
                        @forelse($requests as $req)
                        <tr class="tr-hover-row"
                            data-request-id="{{ $req->id }}"
                            data-request-type="{{ $req->type }}"
                            data-request-status="{{ $req->status }}"
                            data-request-remarks="{{ e($req->remarks ?? '') }}"
                            data-assigned-to="{{ $req->assigned_to ?? '' }}"
                        >
                            <td>
                                <div class="ad-td-id">{{ $req->display_number ?? $req->request_number }}</div>
                                <div class="ad-td-sub">#{{ $req->id }}</div>
                            </td>
                            <td class="ad-td-name">{{ $req->requestor_name }}</td>
                            <td class="ad-td-date">{{ $req->created_at->format('M d, Y') }}</td>
                            <td class="ad-td-center">
                                <span class="status-pill @if($req->status === 'Pending') sp-pending @elseif($req->status === 'Ongoing') sp-ongoing @elseif($req->status === 'Completed') sp-completed @elseif($req->status === 'Rejected') sp-rejected @endif">
                                    {{ $req->status }}
                                </span>
                            </td>
                            <td class="it-assigned-cell">
                                @if($req->assignedTo)
                                    <span class="ad-assigned-name">{{ $req->assignedTo->full_name }}</span>
                                @else
                                    <span class="ad-assigned-none">UNASSIGNED</span>
                                @endif
                            </td>
                            <td class="ad-td-center">
                                <a href="{{ route($req->getRoutePrefix() . '.show', $req->id) }}" class="btn-action-modern" title="View & Assign IT">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="ad-empty">
                                <i class="fa-solid fa-inbox ad-empty-icon"></i>
                                <span class="ad-empty-text">No requests recorded here.</span>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="ad-pagination">
                    {{ $requests->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script nonce="{{ $cspNonce }}">
function filterRequests() {
    const searchInput = document.getElementById('searchRequest').value.toLowerCase();
    const statusFilter = document.getElementById('filterStatus').value;
    const tableRows = document.querySelectorAll('#requestTable tr');

    tableRows.forEach(row => {
        if (row.cells.length < 4) return;

        const id = row.cells[0].textContent.toLowerCase();
        const requestor = row.cells[1].textContent.toLowerCase();
        const statusSpan = row.querySelector('.status-pill');
        const status = statusSpan ? statusSpan.textContent.trim() : "";

        const matchesSearch = id.includes(searchInput) || requestor.includes(searchInput);
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

