<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreventiveMaintenance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'preventive_maintenance';

    protected $fillable = [
        'form_no',
        'technician_name',
        'technician_signature',
        'technician_printed_name',
        'technician_date',
        'problem_description',
        'diagnosis',
        'end_user_name',
        'end_user_date',
        'end_user_floor',
        'end_user_division',
        'end_user_signature',
        'end_user_printed_name',
        'end_user_signature_date',
        'for_disposal',
        'disposal_reason',
        'for_repair',
        'repair_parts',
        'desktop_brand',
        'desktop_model',
        'desktop_pno',
        'desktop_computer_name',
        'monitor1_pno',
        'monitor1_brand',
        'monitor1_model',
        'monitor2_pno',
        'monitor2_brand',
        'monitor2_model',
        'printer1_pno',
        'printer1_brand',
        'printer1_model',
        'printer1_type',
        'printer2_pno',
        'printer2_brand',
        'printer2_model',
        'printer2_type',
        'ups_pno',
        'ups_brand',
        'ups_model',
        'scanner_pno',
        'scanner_brand',
        'scanner_model',
        'laptop_pno',
        'laptop_brand',
        'laptop_model',
        'laptop_computer_name',
        'webcam_brand',
        'webcam_model',
        'webcam_pno',
        'speakers_brand',
        'speakers_model',
        'speakers_pno',
        'earphone_brand',
        'earphone_model',
        'earphone_brand_model',
        'other_equipment',
        'other_equipment_brand',
        'other_equipment_model_pno',
        'desktop_cpu',
        'desktop_ram',
        'desktop_gpu',
        'desktop_os',
        'desktop_hd1',
        'desktop_hd2',
        'desktop_office',
        'desktop_year_purchased',
        'laptop_cpu',
        'laptop_ram',
        'laptop_gpu',
        'laptop_os',
        'laptop_hd1',
        'laptop_hd2',
        'laptop_office',
        'laptop_year_purchased',
        'maintenance_tasks_json',
        'end_user_maintenance',
        'maintenance_date',
        'service_request_no',
        'rid',
        'date_received',
        'service_schedule_date',
    ];

    protected $casts = [
        'technician_date' => 'date',
        'end_user_date' => 'date',
        'end_user_signature_date' => 'date',
        'maintenance_date' => 'date',
        'date_received' => 'date',
        'service_schedule_date' => 'date',
        'maintenance_tasks_json' => 'array',
    ];

    // Relationships
    public function request()
    {
        return $this->hasOne(Request::class, 'detail_id');
    }

    // Helpers
    public function getMaintenanceTasks()
    {
        return $this->maintenance_tasks_json ?? [];
    }
}
