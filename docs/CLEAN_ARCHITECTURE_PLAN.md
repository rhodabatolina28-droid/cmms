# Clean Architecture Implementation Plan

## Overview
This document outlines the implementation of Clean Architecture components for the CMMS V.1.8 application.

## Current State

### Already Implemented Components:
| Component | Status | Files |
|-----------|--------|-------|
| **Enums** | ✅ Partial | `AssetStatus.php` |
| **Observers** | ✅ Partial | `InventoryAssetObserver.php` |
| **Services** | ✅ Partial | 6 service files |
| **Support** | ✅ Partial | 3 support files |
| **Policies** | ✅ Partial | 3 policy files |

## Target Architecture

### 1. Actions/ (Use Cases)
Business logic extracted from controllers into dedicated action classes.

**Directory:** `app/Actions/`

**Files to Create:**
- `Requests/CreateIctRequestAction.php`
- `Requests/UpdateIctRequestAction.php`
- `Requests/AssignItAction.php`
- `Requests/ReviewRequestAction.php`
- `Requests/CreateMaintenanceAction.php`
- `Requests/UpdateMaintenanceAction.php`
- `Inventory/CreateAssetAction.php`
- `Inventory/UpdateAssetAction.php`
- `Inventory/ImportCsvAction.php`
- `Inventory/ConfirmDisposalAction.php`
- `PMSchedule/GenerateScheduleAction.php`
- `PMSchedule/ToggleStatusAction.php`
- `PMSchedule/AdvanceCycleAction.php`
- `Requisition/CreateRequisitionAction.php`
- `Requisition/ReviewRequisitionAction.php`
- `User/ManagePersonnelAction.php`
- `User/ManageUsersAction.php`

### 2. Enums/
PHP Enums for type-safe constants.

**Directory:** `app/Enums/`

**Files to Create:**
- `RequestStatus.php`
- `RequestType.php`
- `UserRole.php`
- `AssetStatus.php` (already exists)
- `RequisitionStatus.php`
- `PMScheduleStatus.php`

### 3. Observers/
Model event observers for automatic behavior.

**Directory:** `app/Observers/`

**Files to Create:**
- `RequestObserver.php`
- `UserObserver.php`
- `PMScheduleObserver.php`
- `RequisitionObserver.php`
- `InventoryAssetObserver.php` (already exists)

### 4. Notifications/
Custom notification classes.

**Directory:** `app/Notifications/`

**Files to Create:**
- `IctRequestCreated.php`
- `IctRequestAssigned.php`
- `IctRequestCompleted.php`
- `MaintenanceRequestCreated.php`
- `MaintenanceRequestAssigned.php`
- `RequisitionSubmitted.php`
- `RequisitionApproved.php`
- `RequisitionRejected.php`
- `AssetDisposalNotification.php`
- `PMScheduleGenerated.php`

### 5. Policies/
Authorization policies (already partially implemented).

**Directory:** `app/Policies/`

**Files to Create:**
- `RequestPolicy.php` (already exists)
- `RequisitionPolicy.php` (already exists)
- `UserPolicy.php` (already exists)
- `InventoryAssetPolicy.php` (already exists)
- `PMSchedulePolicy.php`

### 6. Jobs/
Queueable background jobs.

**Directory:** `app/Jobs/`

**Files to Create:**
- `GeneratePMScheduleJob.php`
- `SendNotificationJob.php`
- `ProcessCsvImportJob.php`
- `UpdateAssetStatusJob.php`
- `SendCsmReminderJob.php`

### 7. Resources/
API Resources for consistent JSON responses.

**Directory:** `app/Http/Resources/`

**Files to Create:**
- `RequestResource.php`
- `RequestCollection.php`
- `AssetResource.php`
- `AssetCollection.php`
- `UserResource.php`
- `UserCollection.php`
- `RequisitionResource.php`
- `RequisitionCollection.php`
- `PMScheduleResource.php`
- `PMScheduleCollection.php`

### 8. Traits/
Reusable code for models and controllers.

**Directory:** `app/Traits/`

**Files to Create:**
- `HasPermissions.php`
- `BelongsToBranch.php`
- `HasAssetStatus.php`
- `TracksHistory.php`
- `HasNotifications.php`
- `ValidatesRequest.php`

### 9. Helpers/
Helper classes (already partially in Support).

**Directory:** `app/Helpers/`

**Files to Create:**
- `RequestHelpers.php` (already in Support)
- `DateHelpers.php`
- `StringHelpers.php`
- `FileHelpers.php`
- `QrCodeHelpers.php`

### 10. ViewModels/
View data preparation classes.

**Directory:** `app/ViewModels/`

**Files to Create:**
- `DashboardViewModel.php`
- `RequestFormViewModel.php`
- `AssetListViewModel.php`
- `PMScheduleViewModel.php`
- `RequisitionViewModel.php`

## Implementation Priority

### Phase 1: Actions (High Priority)
Extract business logic from controllers to make them thin.

### Phase 2: Enums (High Priority)
Replace string constants with type-safe enums.

### Phase 3: Notifications (Medium Priority)
Standardize notification format and make queueable.

### Phase 4: Jobs (Medium Priority)
Move heavy operations to background processing.

### Phase 5: Resources (Low Priority)
API response consistency.

### Phase 6: Traits (Low Priority)
Code reuse and reduce duplication.

### Phase 7: ViewModels (Low Priority)
View logic separation.

## Benefits

1. **Testability** - Each component can be tested in isolation
2. **Maintainability** - Clear separation of concerns
3. **Scalability** - Easy to add new features
4. **Reusability** - Components can be reused
5. **Readability** - Code is easier to understand

## Next Steps

1. Create directory structure
2. Implement Actions for each controller
3. Add Enums for type safety
4. Create Notifications
5. Set up Jobs
6. Add Resources
7. Implement Traits
8. Create ViewModels