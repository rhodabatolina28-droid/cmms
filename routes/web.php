<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ICT\ICTRequestController;
use App\Http\Controllers\Maintenance\MaintenanceController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Admin\SuperAdminController;
use App\Http\Controllers\Requisition\RequisitionController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\PageController;

Route::get('/', [PageController::class, 'landing']);

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/logged-out', [PageController::class, 'loggedOut'])->name('logged-out');

// QR Scan Redirect — public route (handles guests and auth)
Route::get('/r/{id}', [ScanController::class, 'redirect'])->where('id', '[0-9]+')->name('qr.redirect');

// Protected Routes (Authenticated Users Only)
Route::middleware(['auth', 'active', 'require.survey'])->group(function () {
    
    // Admin Dashboard
    Route::get('/dashboard/admin', [DashboardController::class, 'adminDashboard'])
        ->middleware('role:admin')
        ->name('dashboard.admin');

    // Super Admin Dashboard
    Route::get('/dashboard/super-admin', [DashboardController::class, 'superAdminDashboard'])
        ->middleware('role:super_admin')
        ->name('dashboard.super-admin');

    // User Dashboard
    Route::get('/dashboard/user', [DashboardController::class, 'userDashboard'])
        ->middleware('role:user')
        ->name('dashboard.user');

    // IT Personnel Dashboard
    Route::get('/dashboard/it', [DashboardController::class, 'itDashboard'])
        ->middleware('role:it')
        ->name('dashboard.it');


    // ICT Requests
    Route::middleware('role:user,it,admin,super_admin')->group(function () {
        Route::get('/requests/ict', [ICTRequestController::class, 'index'])->name('ict.index');
        Route::get('/requests/ict/create', [ICTRequestController::class, 'create'])->name('ict.create');
        Route::post('/requests/ict', [ICTRequestController::class, 'store'])->name('ict.store')->middleware('throttle:30,1');
        Route::get('/requests/ict/{id}', [ICTRequestController::class, 'show'])->name('ict.show');
        Route::get('/requests/ict/{id}/edit', [ICTRequestController::class, 'edit'])->name('ict.edit');
        Route::get('/requests/ict/{id}/ticket', [ICTRequestController::class, 'ticket'])->name('ict.ticket');
        Route::put('/requests/ict/{id}', [ICTRequestController::class, 'update'])->name('ict.update')->middleware('throttle:30,1');
        Route::post('/requests/ict/{id}/assign-it', [ICTRequestController::class, 'assignIt'])->name('ict.assign-it')->middleware('throttle:30,1');
        Route::get('/requests/ict/{id}/pdf', [ICTRequestController::class, 'downloadPdf'])->name('ict.pdf');
        
        // IT Disposal Recommendation
        Route::post('/requests/ict/{id}/recommend-disposal', [ICTRequestController::class, 'recommendDisposal'])->name('ict.recommend-disposal')->middleware('throttle:30,1');
        Route::get('/requests/ict/{id}/disposal-tag', [ICTRequestController::class, 'disposalTag'])->name('ict.disposal-tag');
    });

    // ICT Destroy & Review — Super Admin only (moved outside permissive group for early rejection)
    Route::delete('/requests/ict/{id}', [ICTRequestController::class, 'destroy'])->name('ict.destroy')->middleware('role:super_admin', 'throttle:30,1');
    Route::post('/requests/ict/{id}/review', [ICTRequestController::class, 'review'])->name('ict.review')->middleware('role:admin', 'throttle:30,1');

    // Maintenance Requests (all roles — controller handles internal role logic)
    Route::get('/requests/maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index')->middleware('role:user,it,admin,super_admin');
    // PM creation is now handled by the scheduler — redirect old manual creation to PM Schedules
    Route::get('/requests/maintenance/create', [PageController::class, 'maintenanceCreateRedirect'])->name('maintenance.create')->middleware('role:it,super_admin');
    Route::post('/requests/maintenance', [PageController::class, 'maintenanceStoreRedirect'])->name('maintenance.store')->middleware('role:it,super_admin', 'throttle:30,1');
    Route::get('/requests/maintenance/{id}', [MaintenanceController::class, 'show'])->name('maintenance.show')->middleware('role:user,it,admin,super_admin');
    Route::get('/requests/maintenance/{id}/edit', [MaintenanceController::class, 'edit'])->name('maintenance.edit')->middleware('role:user,it,admin,super_admin');
    Route::put('/requests/maintenance/{id}', [MaintenanceController::class, 'update'])->name('maintenance.update')->middleware('role:user,it,admin,super_admin', 'throttle:30,1');
    Route::get('/requests/maintenance/{id}/disposal-tag', [MaintenanceController::class, 'disposalTag'])->name('maintenance.disposal-tag')->middleware('role:it,super_admin');
    Route::delete('/requests/maintenance/{id}', [MaintenanceController::class, 'destroy'])->name('maintenance.destroy')->middleware('role:super_admin', 'throttle:30,1');
    Route::get('/requests/maintenance/{id}/pdf', [MaintenanceController::class, 'downloadPdf'])
        ->middleware('role:user,it,admin,super_admin')
        ->name('maintenance.pdf');
    Route::post('/requests/maintenance/{id}/assign', [MaintenanceController::class, 'assignIt'])
        ->middleware('role:super_admin')
        ->middleware('throttle:30,1')
        ->name('maintenance.assign');
    Route::get('/requests/maintenance/scheduled/all', [MaintenanceController::class, 'scheduled'])
        ->middleware('role:it,super_admin')
        ->name('maintenance.scheduled');
    Route::get('/requests/maintenance/{id}/conduct', [MaintenanceController::class, 'start'])
        ->middleware('role:it,super_admin')
        ->name('maintenance.start');


    // Phase 3 — Parts requisitions (IT or Super Admin acting as IT → Supply)
    Route::get('/requests/ict/{id}/requisition', [RequisitionController::class, 'createForTicket'])
        ->middleware('role:it,super_admin')
        ->name('requisitions.create');
    Route::post('/requests/ict/{id}/requisitions', [RequisitionController::class, 'store'])
        ->middleware('role:it,super_admin', 'throttle:30,1')
        ->name('requisitions.store');
    Route::get('/requisitions', [RequisitionController::class, 'index'])
        ->middleware('role:it,admin,super_admin')
        ->name('requisitions.index');
    Route::get('/requisitions/{id}', [RequisitionController::class, 'show'])
        ->middleware('role:it,admin,super_admin')
        ->name('requisitions.show');
    Route::post('/requisitions/{id}/review', [RequisitionController::class, 'review'])
        ->middleware('role:admin', 'throttle:30,1')
        ->name('requisitions.review');

    // Parts & Consumables Inventory (Supply Office) - Removed

    // ==========================================
    // ADMIN, SUPER ADMIN & SUPPLY — INVENTORY
    // ==========================================
    Route::middleware('role:admin')->group(function () {
        Route::get('/inventory/data', [InventoryController::class, 'getAssets'])->name('inventory.data');
        Route::get('/inventory/users', [InventoryController::class, 'getUsers'])->name('inventory.users');
        // Static routes BEFORE parameterized to avoid {assetId} catching "attachments"
        Route::get('/inventory/attachments/{attachmentId}/download', [InventoryController::class, 'downloadAttachment'])
            ->name('inventory.attachments.download');
        Route::delete('/inventory/attachments/{attachmentId}', [InventoryController::class, 'deleteAttachment'])
            ->name('inventory.attachments.delete')->middleware('throttle:30,1');
        Route::get('/inventory/search-assets', [InventoryController::class, 'searchAssets'])->name('inventory.search-assets');
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store')->middleware('throttle:30,1');
        Route::post('/inventory/import/preview', [InventoryController::class, 'previewImport'])->name('inventory.import.preview')->middleware('throttle:10,1');
        Route::post('/inventory/import/commit', [InventoryController::class, 'commitImport'])->name('inventory.import.commit')->middleware('throttle:5,1');
        Route::put('/inventory/{inventory}', [InventoryController::class, 'update'])->name('inventory.update')->middleware('throttle:30,1');
        Route::delete('/inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy')->middleware('throttle:30,1');
        // Parameterized routes after statics
        Route::get('/inventory/{assetId}/history', [InventoryController::class, 'getHistory'])
            ->name('inventory.history');
        Route::get('/inventory/{assetId}/detail', [InventoryController::class, 'detail'])
            ->name('inventory.detail');
        Route::post('/inventory/{assetId}/attachments', [InventoryController::class, 'uploadAttachment'])
            ->name('inventory.attachments.upload')->middleware('throttle:30,1');

        // Inventory Export
        Route::get('/inventory/export', [InventoryController::class, 'export'])
            ->name('inventory.export');

        // Confirm disposal — Supply Officer only, replaces old IIRUP multi-step flow
        Route::post('/inventory/{assetId}/confirm-scrapped', [InventoryController::class, 'confirmScrapped'])
            ->name('inventory.confirm-scrapped')->middleware('throttle:30,1');

        // QR Sticker Print (single)
        Route::get('/inventory/qr-sticker/{assetId}', [InventoryController::class, 'qrSticker'])
            ->name('inventory.qr-sticker');

        // QR Sticker Batch Print (all selected assets)
        Route::get('/inventory/qr-batch', [InventoryController::class, 'qrBatchPrint'])
            ->name('inventory.qr-batch');


        // Asset Disposal routes removed — IIRUP module replaced by simplified confirm-scrapped flow
    });

    // Physical Count - Accessible by Admin (Supply) only
    Route::middleware('role:admin')->prefix('physical-count')->name('physical-count.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Inventory\PhysicalCountController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\Inventory\PhysicalCountController::class, 'store'])->name('store')->middleware('throttle:30,1');
        Route::get('/{id}', [\App\Http\Controllers\Inventory\PhysicalCountController::class, 'show'])->name('show');
        Route::post('/{sessionId}/search', [\App\Http\Controllers\Inventory\PhysicalCountController::class, 'searchAsset'])->name('search')->middleware('throttle:30,1');
        Route::post('/{sessionId}/mark', [\App\Http\Controllers\Inventory\PhysicalCountController::class, 'markAsset'])->name('mark')->middleware('throttle:30,1');
        Route::post('/{id}/complete', [\App\Http\Controllers\Inventory\PhysicalCountController::class, 'complete'])->name('complete')->middleware('throttle:30,1');
        Route::get('/{sessionId}/export', [\App\Http\Controllers\Inventory\PhysicalCountController::class, 'export'])->name('export');
        Route::get('/{sessionId}/print', [\App\Http\Controllers\Inventory\PhysicalCountController::class, 'printReport'])->name('print');
    });

    // ==========================================
    Route::post('/admin/requests/update-status', [ICTRequestController::class, 'updateStatus'])
        ->middleware('role:admin', 'throttle:30,1')
        ->name('admin.requests.update-status');

    // Inventory Reports (admin + super_admin)
    Route::get('/inventory/reports', [\App\Http\Controllers\Inventory\InventoryReportController::class, 'index'])
        ->middleware('role:admin,super_admin')
        ->name('inventory.reports');

    // ADMIN & SUPER ADMIN ONLY ROUTES
    // ==========================================
    Route::middleware('role:admin,super_admin')->group(function () {
        // Personnel Management
        Route::get('/personnel', [\App\Http\Controllers\Admin\PersonnelController::class, 'index'])->name('personnel.index');
        Route::get('/personnel/{id}', [\App\Http\Controllers\Admin\PersonnelController::class, 'show'])->name('personnel.show');
        Route::post('/personnel/{id}/toggle', [\App\Http\Controllers\Admin\PersonnelController::class, 'toggleStatus'])->name('personnel.toggle')->middleware('throttle:30,1');
        Route::post('/personnel', [\App\Http\Controllers\Admin\PersonnelController::class, 'store'])->middleware('throttle:30,60')->name('personnel.store');
    });

    // ==========================================
    // SHARED ROUTES (All Authenticated Users)
    // ==========================================
    // Profile Routes
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update')->middleware('throttle:10,1');

    // Notification Routes
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'getNotifications'])->name('notifications.get');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read')->middleware('throttle:60,1');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all')->middleware('throttle:30,1');

    // Asset Routes
    Route::get('/my-assets', [\App\Http\Controllers\AssetController::class, 'myAssets'])->name('profile.assets');

    // Asset API (authenticated users) — using /assets/ instead of /api/assets/ to avoid api.php catch-all
    Route::get('/assets/{id}/profile', [InventoryController::class, 'apiProfile'])->name('api.asset.profile')->middleware('role:user,it,admin,super_admin');
    
    // CSM Survey Routes (end-users only)
    Route::get('/survey/{requestId}', [\App\Http\Controllers\CsmController::class, 'create'])->middleware('role:user')->name('csm.create');
    Route::post('/survey', [\App\Http\Controllers\CsmController::class, 'store'])->middleware('role:user', 'throttle:10,1')->name('csm.store');
    
    // PM Schedules - Super Admin ONLY
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/pm-schedules', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'index'])->name('pm-schedules.index');
        Route::get('/pm-schedules/create', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'create'])->name('pm-schedules.create');
        Route::post('/pm-schedules', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'store'])->name('pm-schedules.store')->middleware('throttle:60,1');
        Route::get('/pm-schedules/{pm_schedule}', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'show'])->name('pm-schedules.show')->where('pm_schedule', '[0-9]+');
        Route::get('/pm-schedules/{pm_schedule}/edit', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'edit'])->name('pm-schedules.edit')->where('pm_schedule', '[0-9]+');
        Route::put('/pm-schedules/{pm_schedule}', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'update'])->name('pm-schedules.update')->middleware('throttle:30,1')->where('pm_schedule', '[0-9]+');
        Route::patch('/pm-schedules/{pm_schedule}/toggle', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'toggleStatus'])->name('pm-schedules.toggle')->middleware('throttle:30,1')->where('pm_schedule', '[0-9]+');
        Route::delete('/pm-schedules/{pm_schedule}', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'destroy'])->name('pm-schedules.destroy')->middleware('throttle:30,1')->where('pm_schedule', '[0-9]+');
        Route::get('/pm-schedules/{pm_schedule}/preview', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'preview'])->name('pm-schedules.preview')->where('pm_schedule', '[0-9]+');
        Route::post('/pm-schedules/{pm_schedule}/generate', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'generate'])->middleware('throttle:30,60')->name('pm-schedules.generate')->where('pm_schedule', '[0-9]+');
        Route::get('/pm-schedules/{pm_schedule}/history', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'history'])->name('pm-schedules.history')->where('pm_schedule', '[0-9]+');
        Route::get('/pm-schedules/{pm_schedule}/queue', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'queueStatus'])->name('pm-schedules.queue')->where('pm_schedule', '[0-9]+');
        Route::post('/pm-schedules/repair-all', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'repairBrokenRecords'])->name('pm-schedules.repair');
        Route::get('/pm-schedules/orders', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'orders'])->name('pm-schedules.orders');
        Route::get('/pm-schedules/orders/data', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'ordersData'])->name('pm-schedules.orders.data');
        Route::post('/pm-schedules/force-run', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'forceRun'])->name('pm-schedules.force-run')->middleware('throttle:10,1');
        Route::post('/pm-schedules/{pm_schedule}/pause', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'pauseCycle'])->name('pm-schedules.pause')->where('pm_schedule', '[0-9]+');
        Route::post('/pm-schedules/{pm_schedule}/resume', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'resumeCycle'])->name('pm-schedules.resume')->where('pm_schedule', '[0-9]+');
        Route::post('/pm-schedules/{pm_schedule}/stop', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'stopCycle'])->name('pm-schedules.stop')->where('pm_schedule', '[0-9]+');
        Route::post('/pm-schedules/advance', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'advanceCycle'])->name('pm-schedules.advance');
        Route::delete('/pm-schedules', [\App\Http\Controllers\Maintenance\PMScheduleController::class, 'destroyAll'])->name('pm-schedules.destroy-all')->middleware('throttle:10,1');
    });

    // PM Tasks - IT and Super Admin can view
    Route::get('/maintenance/pm-tasks', [\App\Http\Controllers\Maintenance\MaintenanceController::class, 'pmTasks'])->name('pm.tasks')->middleware('role:it,super_admin');

    // Super Admin Specific Routes
    Route::middleware('role:super_admin')->prefix('super-admin')->group(function () {
        Route::get('/users', [SuperAdminController::class, 'users'])->name('super_admin.users');
        Route::get('/users/data', [SuperAdminController::class, 'usersData'])->name('super_admin.users.data');
        Route::get('/requests/data', [SuperAdminController::class, 'requestsData'])->name('super_admin.requests.data');
        Route::post('/users', [SuperAdminController::class, 'storeUser'])->middleware('throttle:30,60')->name('super_admin.users.store');
        Route::put('/users/{id}', [SuperAdminController::class, 'updateUser'])->middleware('throttle:30,60')->name('super_admin.users.update');
        Route::post('/users/{id}/toggle', [SuperAdminController::class, 'toggleUserStatus'])->middleware('throttle:30,60')->name('super_admin.users.toggle');
        Route::post('/users/{id}/reset-password', [SuperAdminController::class, 'resetPassword'])->middleware('throttle:30,60')->name('super_admin.users.reset_password');
        Route::delete('/users/{id}', [SuperAdminController::class, 'deleteUser'])->middleware('throttle:30,60')->name('super_admin.users.delete');
        Route::get('/audit-logs', [SuperAdminController::class, 'auditLogs'])->name('super_admin.audit_logs');
        Route::get('/audit-logs/data', [SuperAdminController::class, 'auditLogsData'])->name('super_admin.audit_logs.data');
        Route::post('/audit-logs/archive', [SuperAdminController::class, 'archiveLogs'])->name('super_admin.audit_logs.archive')->middleware('throttle:10,1');

        // Super Admin — READ-ONLY inventory oversight (no write access)
        Route::get('/inventory', [InventoryController::class, 'superAdminIndex'])->name('super_admin.inventory');
        Route::get('/inventory/data', [InventoryController::class, 'superAdminGetAssets'])->name('super_admin.inventory.data');
        // Static routes BEFORE parameterized routes to avoid {assetId} catching "attachments"
        Route::get('/inventory/attachments/{attachmentId}/download', [InventoryController::class, 'downloadAttachment'])->name('super_admin.inventory.attachment.download');
        // Parameterized routes after statics
        Route::get('/inventory/{assetId}/history', [InventoryController::class, 'getHistory'])->name('super_admin.inventory.history');
        Route::get('/inventory/{assetId}/detail', [InventoryController::class, 'superAdminDetail'])->name('super_admin.inventory.detail');
        Route::get('/inventory/export', [InventoryController::class, 'export'])->name('super_admin.inventory.export');
        // Super Admin disposal view removed — simplified flow handled by supply officer via confirm-scrapped
    });
});
