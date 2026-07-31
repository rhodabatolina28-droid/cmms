<?php

namespace Tests\System;

use Tests\TestCase;
use App\Models\User;
use App\Models\Request as RequestModel;
use App\Models\PreventiveMaintenance;
use App\Models\RepairRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AssignmentFormDisableTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $itUser1;
    protected $itUser2;
    protected $endUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'full_name' => 'Super Admin']);
        $this->itUser1 = User::factory()->create(['role' => 'it', 'full_name' => 'IT User 1']);
        $this->itUser2 = User::factory()->create(['role' => 'it', 'full_name' => 'IT User 2']);
        $this->endUser = User::factory()->create(['role' => 'user', 'full_name' => 'End User']);
    }

    // ============ PREVENTIVE MAINTENANCE TESTS ============

    /**
     * Test: Super Admin can edit PM form when UNASSIGNED
     */
    public function test_super_admin_can_edit_pm_when_unassigned()
    {
        // Create unassigned PM request
        $request = RequestModel::factory()->create([
            'type' => 'Preventive Maintenance',
            'user_id' => $this->endUser->id,
            'assigned_to' => null,
        ]);

        PreventiveMaintenance::factory()->create(['request_id' => $request->id]);

        // Check authorization
        $canEdit = $this->superAdmin->can('editMaintenanceTechnician', $request);
        $this->assertTrue($canEdit, 'Super Admin should edit unassigned PM');

        // Check form flags
        $flags = \App\Support\RequestHelpers::maintenanceFormFlags($this->superAdmin, $request);
        $this->assertTrue($flags['isAdmin'], 'isAdmin flag should be true for unassigned PM');
        $this->assertTrue($flags['canEditTechnician'], 'canEditTechnician should be true');
    }

    /**
     * Test: Super Admin CANNOT edit PM form when ASSIGNED to someone else
     */
    public function test_super_admin_cannot_edit_pm_when_assigned()
    {
        // Create PM request assigned to IT User 1
        $request = RequestModel::factory()->create([
            'type' => 'Preventive Maintenance',
            'user_id' => $this->endUser->id,
            'assigned_to' => $this->itUser1->id,
        ]);

        PreventiveMaintenance::factory()->create(['request_id' => $request->id]);

        // Check authorization
        $canEdit = $this->superAdmin->can('editMaintenanceTechnician', $request);
        $this->assertFalse($canEdit, 'Super Admin should NOT edit assigned PM');

        // Check form flags
        $flags = \App\Support\RequestHelpers::maintenanceFormFlags($this->superAdmin, $request);
        $this->assertFalse($flags['isAdmin'], 'isAdmin flag should be false for assigned PM');
        $this->assertFalse($flags['canEditTechnician'], 'canEditTechnician should be false');
    }

    /**
     * Test: Assigned IT User CAN edit PM form
     */
    public function test_assigned_it_can_edit_pm()
    {
        // Create PM request assigned to IT User 1
        $request = RequestModel::factory()->create([
            'type' => 'Preventive Maintenance',
            'user_id' => $this->endUser->id,
            'assigned_to' => $this->itUser1->id,
        ]);

        PreventiveMaintenance::factory()->create(['request_id' => $request->id]);

        // Check authorization
        $canEdit = $this->itUser1->can('editMaintenanceTechnician', $request);
        $this->assertTrue($canEdit, 'Assigned IT User should edit PM');

        // Check form flags
        $flags = \App\Support\RequestHelpers::maintenanceFormFlags($this->itUser1, $request);
        $this->assertTrue($flags['isAdmin'], 'isAdmin flag should be true for assigned IT');
        $this->assertTrue($flags['canEditTechnician'], 'canEditTechnician should be true');
    }

    /**
     * Test: Non-assigned IT User CANNOT edit PM form
     */
    public function test_non_assigned_it_cannot_edit_pm()
    {
        // Create PM request assigned to IT User 1
        $request = RequestModel::factory()->create([
            'type' => 'Preventive Maintenance',
            'user_id' => $this->endUser->id,
            'assigned_to' => $this->itUser1->id,
        ]);

        PreventiveMaintenance::factory()->create(['request_id' => $request->id]);

        // Check authorization - IT User 2 should NOT edit
        $canEdit = $this->itUser2->can('editMaintenanceTechnician', $request);
        $this->assertFalse($canEdit, 'Non-assigned IT User should NOT edit PM');

        // Check form flags
        $flags = \App\Support\RequestHelpers::maintenanceFormFlags($this->itUser2, $request);
        $this->assertFalse($flags['isAdmin'], 'isAdmin flag should be false for non-assigned IT');
    }

    // ============ ICT REQUEST TESTS ============

    /**
     * Test: Super Admin can edit ICT form when UNASSIGNED
     */
    public function test_super_admin_can_edit_ict_when_unassigned()
    {
        // Create unassigned ICT request
        $request = RequestModel::factory()->create([
            'type' => 'ICT',
            'user_id' => $this->endUser->id,
            'assigned_to' => null,
        ]);

        RepairRequest::factory()->create(['request_id' => $request->id]);

        // Check authorization
        $canEdit = $this->superAdmin->can('editIctTechnician', $request);
        $this->assertTrue($canEdit, 'Super Admin should edit unassigned ICT');

        // Check form flags
        $flags = \App\Support\RequestHelpers::ictFormFlags($this->superAdmin, $request);
        $this->assertTrue($flags['isAdmin'], 'isAdmin flag should be true for unassigned ICT');
        $this->assertTrue($flags['canEditTechnician'], 'canEditTechnician should be true');
    }

    /**
     * Test: Super Admin CANNOT edit ICT form when ASSIGNED to someone else
     */
    public function test_super_admin_cannot_edit_ict_when_assigned()
    {
        // Create ICT request assigned to IT User 1
        $request = RequestModel::factory()->create([
            'type' => 'ICT',
            'user_id' => $this->endUser->id,
            'assigned_to' => $this->itUser1->id,
        ]);

        RepairRequest::factory()->create(['request_id' => $request->id]);

        // Check authorization
        $canEdit = $this->superAdmin->can('editIctTechnician', $request);
        $this->assertFalse($canEdit, 'Super Admin should NOT edit assigned ICT');

        // Check form flags
        $flags = \App\Support\RequestHelpers::ictFormFlags($this->superAdmin, $request);
        $this->assertFalse($flags['isAdmin'], 'isAdmin flag should be false for assigned ICT');
        $this->assertFalse($flags['canEditTechnician'], 'canEditTechnician should be false');
    }

    /**
     * Test: Assigned IT User CAN edit ICT form
     */
    public function test_assigned_it_can_edit_ict()
    {
        // Create ICT request assigned to IT User 1
        $request = RequestModel::factory()->create([
            'type' => 'ICT',
            'user_id' => $this->endUser->id,
            'assigned_to' => $this->itUser1->id,
        ]);

        RepairRequest::factory()->create(['request_id' => $request->id]);

        // Check authorization
        $canEdit = $this->itUser1->can('editIctTechnician', $request);
        $this->assertTrue($canEdit, 'Assigned IT User should edit ICT');

        // Check form flags
        $flags = \App\Support\RequestHelpers::ictFormFlags($this->itUser1, $request);
        $this->assertTrue($flags['isAdmin'], 'isAdmin flag should be true for assigned IT');
        $this->assertTrue($flags['canEditTechnician'], 'canEditTechnician should be true');
    }

    // ============ ASSIGNMENT TESTS ============

    /**
     * Test: Super Admin CAN assign tickets
     */
    public function test_super_admin_can_assign_tickets()
    {
        $pmRequest = RequestModel::factory()->create([
            'type' => 'Preventive Maintenance',
            'user_id' => $this->endUser->id,
            'assigned_to' => null,
        ]);

        $ictRequest = RequestModel::factory()->create([
            'type' => 'ICT',
            'user_id' => $this->endUser->id,
            'assigned_to' => null,
        ]);

        // Check authorization for assignment
        $pmCanAssign = $this->superAdmin->can('assignTicket', $pmRequest);
        $ictCanAssign = $this->superAdmin->can('assignTicket', $ictRequest);

        $this->assertTrue($pmCanAssign, 'Super Admin should assign PM tickets');
        $this->assertTrue($ictCanAssign, 'Super Admin should assign ICT tickets');
    }

    /**
     * Test: IT User CANNOT assign tickets
     */
    public function test_it_cannot_assign_tickets()
    {
        $pmRequest = RequestModel::factory()->create([
            'type' => 'Preventive Maintenance',
            'user_id' => $this->endUser->id,
            'assigned_to' => null,
        ]);

        // IT user should NOT be able to assign
        $canAssign = $this->itUser1->can('assignTicket', $pmRequest);
        $this->assertFalse($canAssign, 'IT User should NOT assign tickets');
    }

    // ============ UPDATE TESTS ============

    /**
     * Test: canUpdateMaintenanceTicket respects assignment rules
     */
    public function test_can_update_maintenance_respects_assignment()
    {
        $pmRequest = RequestModel::factory()->create([
            'type' => 'Preventive Maintenance',
            'user_id' => $this->endUser->id,
            'assigned_to' => $this->itUser1->id,
        ]);

        // Super Admin should NOT update assigned PM
        $superAdminCanUpdate = $this->superAdmin->can('updateMaintenance', $pmRequest);
        $this->assertFalse($superAdminCanUpdate, 'Super Admin should NOT update assigned PM');

        // Assigned IT should update
        $itCanUpdate = $this->itUser1->can('updateMaintenance', $pmRequest);
        $this->assertTrue($itCanUpdate, 'Assigned IT should update PM');

        // Non-assigned IT should NOT update
        $otherItCanUpdate = $this->itUser2->can('updateMaintenance', $pmRequest);
        $this->assertFalse($otherItCanUpdate, 'Non-assigned IT should NOT update PM');
    }

    /**
     * Test: canUpdateIctTicket respects assignment rules
     */
    public function test_can_update_ict_respects_assignment()
    {
        $ictRequest = RequestModel::factory()->create([
            'type' => 'ICT',
            'user_id' => $this->endUser->id,
            'assigned_to' => $this->itUser1->id,
        ]);

        // Super Admin should NOT update assigned ICT
        $superAdminCanUpdate = $this->superAdmin->can('updateIct', $ictRequest);
        $this->assertFalse($superAdminCanUpdate, 'Super Admin should NOT update assigned ICT');

        // Assigned IT should update
        $itCanUpdate = $this->itUser1->can('updateIct', $ictRequest);
        $this->assertTrue($itCanUpdate, 'Assigned IT should update ICT');

        // Non-assigned IT should NOT update
        $otherItCanUpdate = $this->itUser2->can('updateIct', $ictRequest);
        $this->assertFalse($otherItCanUpdate, 'Non-assigned IT should NOT update ICT');
    }
}
