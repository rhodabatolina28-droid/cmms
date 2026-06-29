<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CsmSurvey extends Model
{
    use HasFactory;

    protected $table = 'csm_surveys';

    protected $fillable = [
        'request_id',
        'age',
        'sex',
        'cc1',
        'cc2',
        'cc3',
        'sqd1',
        'sqd2',
        'sqd3',
        'sqd4',
        'sqd5',
        'sqd6',
        'sqd7',
        'sqd8',
        'sqd9',
        'suggestions',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }
}
