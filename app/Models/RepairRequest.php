<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RepairRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'repair_requests';

    protected $fillable = [
        'service_request_no',
        'end_user_last_name',
        'end_user_first_name',
        'end_user_middle_name',
        'end_user_sex',
        'division_office',
        'end_user_email',
        'employee_no',
        'repair_description',
        'end_user_signature',
        'end_user_printed_name',
        'end_user_date',
        'it_received_last_name',
        'it_received_first_name',
        'it_received_middle_name',
        'initial_diagnosis',
        'repair_type',
        'it_remarks',
        'rid',
        'date_received',
        'service_schedule_date',
        'property_no',
        'article_serial_no',
        'office_date_acquired',
        'service_date',
        'pullout_date',
        'company_name',
        'company_phone',
        'company_email',
        'company_address',
        'technician_last_name',
        'technician_first_name',
        'technician_middle_name',
        'technician_signature',
        'technician_printed_name',
        'technician_date',
        'action_taken',
        'after_repair_status',
        'cost',
        'after_service_date',
        'findings_remarks',
        'it_personnel_signature',
        'it_personnel_printed_name',
        'it_personnel_date',
        'end_user_acceptance_signature',
        'end_user_acceptance_printed_name',
        'end_user_acceptance_date',
    ];

    protected $casts = [
        'date_received' => 'date',
        'service_schedule_date' => 'date',
        'service_date' => 'date',
        'pullout_date' => 'date',
        'technician_date' => 'date',
        'after_service_date' => 'date',
        'cost' => 'decimal:2',
        'it_personnel_date' => 'date',
        'end_user_acceptance_date' => 'date',
    ];

    // Relationships
    public function request()
    {
        return $this->hasOne(Request::class, 'detail_id');
    }

    // Helpers
    public function getFullUserName()
    {
        return trim("{$this->end_user_first_name} {$this->end_user_last_name}");
    }

    public function getFullTechnicianName()
    {
        return trim("{$this->technician_first_name} {$this->technician_last_name}");
    }
}
