<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_requests', function (Blueprint $table) {
            $table->id();
            $table->string('service_request_no')->unique()->nullable(); // Link to requests table
            
            // END-USER INFORMATION
            $table->string('end_user_last_name');
            $table->string('end_user_first_name');
            $table->string('end_user_middle_name')->nullable();
            $table->enum('end_user_sex', ['MALE', 'FEMALE']);
            $table->string('division_office');
            $table->string('end_user_email');
            $table->string('employee_no');
            $table->text('repair_description');
            $table->string('end_user_signature')->nullable(); // File path
            $table->string('end_user_printed_name')->nullable();
            $table->date('end_user_date')->nullable();
            
            // IT PERSONNEL RECEIVED INFORMATION
            $table->string('it_received_last_name')->nullable();
            $table->string('it_received_first_name')->nullable();
            $table->string('it_received_middle_name')->nullable();
            $table->text('initial_diagnosis')->nullable();
            $table->string('repair_type')->nullable(); // Comma-separated
            $table->text('it_remarks')->nullable();
            
            // IT PERSONNEL TRACKING
            $table->string('rid')->nullable();
            $table->date('date_received')->nullable();
            $table->date('service_schedule_date')->nullable();
            $table->string('property_no')->nullable();
            $table->string('article_serial_no')->nullable();
            $table->string('office_date_acquired')->nullable();
            
            // SERVICE PROVIDER
            $table->date('service_date')->nullable();
            $table->date('pullout_date')->nullable();
            $table->string('company_name')->nullable();
            $table->string('company_phone')->nullable();
            $table->string('company_email')->nullable();
            $table->text('company_address')->nullable();
            $table->string('technician_last_name')->nullable();
            $table->string('technician_first_name')->nullable();
            $table->string('technician_middle_name')->nullable();
            $table->string('technician_signature')->nullable(); // File path
            $table->string('technician_printed_name')->nullable();
            $table->date('technician_date')->nullable();
            $table->text('action_taken')->nullable();
            
            // AFTER REPAIR
            $table->string('after_repair_status')->nullable();
            $table->date('after_service_date')->nullable();
            $table->text('findings_remarks')->nullable();
            $table->string('it_personnel_signature')->nullable(); // File path
            $table->string('it_personnel_printed_name')->nullable();
            $table->date('it_personnel_date')->nullable();
            
            // END-USER ACCEPTANCE
            $table->string('end_user_acceptance_signature')->nullable(); // File path
            $table->string('end_user_acceptance_printed_name')->nullable();
            $table->date('end_user_acceptance_date')->nullable();
            
            $table->timestamps();
            
            $table->index('service_request_no');
            $table->index('end_user_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_requests');
    }
};
