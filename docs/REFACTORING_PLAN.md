# Refactoring Plan: Laravel-First Code Structure

## Status: COMPLETE ✓ (with bug fixes applied)

### Phase 1: Model Scopes ✓
- [x] `Request` model - 4 scopes (ictVisibleToUser, pmVisibleToUser, pmTasksForUser, scheduledPmForUser)
- [x] `InventoryAsset` model - 3 scopes (visibleToSupplyAdmin, visibleToSuperAdmin, visibleToDivisionAdmin)

### Phase 2: Policy Classes ✓
- [x] `RequestPolicy` - view, create, update, assign, review, sign acceptance, quick update, maintenance view/update
- [x] `InventoryAssetPolicy` - view, create, update, confirm disposal, view history, upload attachment
- [x] `RequisitionPolicy` - view, create, update, review
- [x] Registered in `AppServiceProvider` via `Gate::policy()`

### Phase 3: Controller Refactoring ✓
| Controller | Methods Refactored | Pattern Used |
|-----------|-------------------|-------------|
| ICTRequestController | index, create, updateStatus | Scopes, Policy |
| MaintenanceController | index, pmTasks, scheduled, create | Scopes |
| InventoryController | getAssets, superAdminGetAssets, searchAssets, export | Scopes |
| DashboardController | adminDashboard | Scopes |
| ScanController | redirect | Helper methods |
| RequisitionController | createForTicket, show | Helper methods |

### Result: 14 controller methods + 12 structural files refactored
- **Before:** Inline `$user->role === '...'` chains in controllers
- **After:** Model scopes, policies, and User helper methods

### Bug Fixes Applied (July 2026)
- [x] Fixed `Request::scopePmVisibleToUser` - query builder chaining issue
- [x] Fixed `Request::scopePmTasksForUser` - query builder chaining issue  
- [x] Fixed `Request::scopeScheduledPmForUser` - query builder chaining issue
- [x] Fixed `InventoryAsset::scopeVisibleToSupplyAdmin` - query builder chaining issue
- [x] Fixed `InventoryAsset::scopeVisibleToSuperAdmin` - query builder chaining issue

### Git Checkpoint: `git tag pre-refactor`
- Rollback: `git checkout pre-refactor -- .`

### Phase 4: Remaining Controllers Refactoring ✓ COMPLETE
- [x] `AssetController` - Asset management methods (already using scopes)
- [x] `CsmController` - CSM survey methods (already using helper methods)
- [x] `PMScheduleController` - PM schedule management (already using helper methods and scopes)
- [x] `SuperAdminController` - Super admin operations (already using helper methods)
- [x] `PhysicalCountController` - Physical inventory count (already using helper methods)

### Final Status: ALL PHASES COMPLETE ✓
All controllers now follow Laravel best practices:
- Eloquent ORM for database access
- Helper methods for role checking
- Model scopes for query optimization
- Policies for authorization
- Services for complex workflows

### Key Rules Preserved
- ✅ NO logic/business rule changes
- ✅ NO database schema changes
- ✅ NO route/URL changes
- ✅ NO view/Blade changes
- ✅ NO JavaScript/frontend changes
