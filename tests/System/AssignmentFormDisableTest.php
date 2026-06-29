<?php

namespace Tests\System;

use Tests\TestCase;
use App\Models\User;
use App\Models\Request as RequestModel;
use App\Models\PreventiveMaintenance;
use App\Models\RepairRequest;
use App\Support\RequestAuthorization;
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
        $canEdit = RequestAuthorization::canEditMaintenanceTechnician($this->superAdmin, $request);
        $this->assertTrue($canEdit, 'Super Admin should edit unassigned PM');

        // Check form flags
        $flags = RequestAuthorization::maintenanceFormFlags($this->superAdmin, $request);
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
        $canEdit = RequestAuthorization::canEditMaintenanceTechnician($this->superAdmin, $request);
        $this->assertFalse($canEdit, 'Super Admin should NOT edit assigned PM');

        // Check form flags
        $flags = RequestAuthorization::maintenanceFormFlags($this->superAdmin, $request);
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
        $canEdit = RequestAuthorization::canEditMaintenanceTechnician($this->itUser1, $request);
        $this->assertTrue($canEdit, 'Assigned IT User should edit PM');

        // Check form flags
        $flags = RequestAuthorization::maintenanceFormFlags($this->itUser1, $request);
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
        $canEdit = RequestAuthorization::canEditMaintenanceTechnician($this->itUser2, $request);
        $this->assertFalse($canEdit, 'Non-assigned IT User should NOT edit PM');

        // Check form flags
        $flags = RequestAuthorization::maintenanceFormFlags($this->itUser2, $request);
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
        $canEdit = RequestAuthorization::canEditIctTechnicianSections($this->superAdmin, $request);
        $this->assertTrue($canEdit, 'Super Admin should edit unassigned ICT');

        // Check form flags
        $flags = RequestAuthorization::ictFormFlags($this->superAdmin, $request);
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
        $canEdit = RequestAuthorization::canEditIctTechnicianSections($this->superAdmin, $request);
        $this->assertFalse($canEdit, 'Super Admin should NOT edit assigned ICT');

        // Check form flags
        $flags = RequestAuthorization::ictFormFlags($this->superAdmin, $request);
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
        $canEdit = RequestAuthorization::canEditIctTechnicianSections($this->itUser1, $request);
        $this->assertTrue($canEdit, 'Assigned IT User should edit ICT');

        // Check form flags
        $flags = RequestAuthorization::ictFormFlags($this->itUser1, $request);
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
        $pmCanAssign = RequestAuthorization::canAssignTicket($this->superAdmin, $pmRequest);
        $ictCanAssign = RequestAuthorization::canAssignTicket($this->superAdmin, $ictRequest);

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
        $canAssign = RequestAuthorization::canAssignTicket($this->itUser1, $pmRequest);
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
        $superAdminCanUpdate = RequestAuthorization::canUpdateMaintenanceTicket($this->superAdmin, $pmRequest);
        $this->assertFalse($superAdminCanUpdate, 'Super Admin should NOT update assigned PM');

        // Assigned IT should update
        $itCanUpdate = RequestAuthorization::canUpdateMaintenanceTicket($this->itUser1, $pmRequest);
        $this->assertTrue($itCanUpdate, 'Assigned IT should update PM');

        // Non-assigned IT should NOT update
        $otherItCanUpdate = RequestAuthorization::canUpdateMaintenanceTicket($this->itUser2, $pmRequest);
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
        $superAdminCanUpdate = RequestAuthorization::canUpdateIctTicket($this->superAdmin, $ictRequest);
        $this->assertFalse($superAdminCanUpdate, 'Super Admin should NOT update assigned ICT');

        // Assigned IT should update
        $itCanUpdate = RequestAuthorization::canUpdateIctTicket($this->itUser1, $ictRequest);
        $this->assertTrue($itCanUpdate, 'Assigned IT should update ICT');

        // Non-assigned IT should NOT update
        $otherItCanUpdate = RequestAuthorization::canUpdateIctTicket($this->itUser2, $ictRequest);
        $this->assertFalse($otherItCanUpdate, 'Non-assigned IT should NOT update ICT');
    }
}
