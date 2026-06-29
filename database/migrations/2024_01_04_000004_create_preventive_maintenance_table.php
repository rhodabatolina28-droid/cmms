<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preventive_maintenance', function (Blueprint $table) {
            $table->id();
            $table->string('form_no')->unique()->nullable(); // Link to requests table
            
            // TECHNICIAN INFO
            $table->text('technician_name')->nullable();
            $table->text('technician_signature')->nullable();
            $table->text('technician_printed_name')->nullable();
            $table->date('technician_date')->nullable();
            $table->text('problem_description')->nullable();
            
            // TECHNICIAN ANALYSIS
            $table->text('diagnosis')->nullable();
            
            // END USER
            $table->string('end_user_name');
            $table->date('end_user_date')->nullable();
            $table->text('end_user_floor')->nullable();
            $table->text('end_user_division')->nullable();
            $table->text('end_user_signature')->nullable();
            $table->text('end_user_printed_name')->nullable();
            $table->date('end_user_signature_date')->nullable();
            
            // RECOMMENDATION
            $table->text('for_disposal')->nullable();
            $table->text('disposal_reason')->nullable();
            $table->text('for_repair')->nullable();
            $table->text('repair_parts')->nullable();
            
            // DEVICE INFO - Desktop
            $table->text('desktop_brand')->nullable();
            $table->text('desktop_model')->nullable();
            $table->text('desktop_pno')->nullable();
            $table->text('desktop_computer_name')->nullable();
            
            // Device Info - Monitors, Printers, etc.
            $table->text('monitor1_pno')->nullable();
            $table->text('monitor1_brand')->nullable();
            $table->text('monitor1_model')->nullable();
            $table->text('monitor2_pno')->nullable();
            $table->text('monitor2_brand')->nullable();
            $table->text('monitor2_model')->nullable();
            
            $table->text('printer1_pno')->nullable();
            $table->text('printer1_brand')->nullable();
            $table->text('printer1_model')->nullable();
            $table->text('printer1_type')->nullable();
            $table->text('printer2_pno')->nullable();
            $table->text('printer2_brand')->nullable();
            $table->text('printer2_model')->nullable();
            $table->text('printer2_type')->nullable();
            
            $table->text('ups_pno')->nullable();
            $table->text('ups_brand')->nullable();
            $table->text('ups_model')->nullable();
            
            $table->text('scanner_pno')->nullable();
            $table->text('scanner_brand')->nullable();
            $table->text('scanner_model')->nullable();
            
            $table->text('laptop_pno')->nullable();
            $table->text('laptop_brand')->nullable();
            $table->text('laptop_model')->nullable();
            $table->text('laptop_computer_name')->nullable();
            
            $table->text('webcam_brand')->nullable();
            $table->text('webcam_model')->nullable();
            $table->text('webcam_pno')->nullable();
            
            $table->text('speakers_brand')->nullable();
            $table->text('speakers_model')->nullable();
            $table->text('speakers_pno')->nullable();
            
            $table->text('earphone_brand')->nullable();
            $table->text('earphone_model')->nullable();
            $table->text('earphone_brand_model')->nullable();
            
            $table->text('other_equipment')->nullable();
            $table->text('other_equipment_brand')->nullable();
            $table->text('other_equipment_model_pno')->nullable();
            
            // SPECS
            $table->text('desktop_cpu')->nullable();
            $table->text('desktop_ram')->nullable();
            $table->text('desktop_gpu')->nullable();
            $table->text('desktop_os')->nullable();
            $table->text('desktop_hd1')->nullable();
            $table->text('desktop_hd2')->nullable();
            $table->text('desktop_office')->nullable();
            $table->text('desktop_year_purchased')->nullable();
            
            $table->text('laptop_cpu')->nullable();
            $table->text('laptop_ram')->nullable();
            $table->text('laptop_gpu')->nullable();
            $table->text('laptop_os')->nullable();
            $table->text('laptop_hd1')->nullable();
            $table->text('laptop_hd2')->nullable();
            $table->text('laptop_office')->nullable();
            $table->text('laptop_year_purchased')->nullable();
            
            // MAINTENANCE TASKS (JSON)
            $table->longText('maintenance_tasks_json')->nullable(); // Stores JSON of all tasks
            
            // TRACKING
            $table->text('end_user_maintenance')->nullable();
            $table->date('maintenance_date')->nullable();
            $table->text('service_request_no')->nullable();
            $table->text('rid')->nullable();
            $table->date('date_received')->nullable();
            $table->date('service_schedule_date')->nullable();
            
            $table->timestamps();
            
            $table->index('form_no');
            $table->index('end_user_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preventive_maintenance');
    }
};
