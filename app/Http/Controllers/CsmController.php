<?php

namespace App\Http\Controllers;

use App\Models\Request as RequestModel;
use App\Models\CsmSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Http\Requests\StoreCsmSurveyRequest;
use App\Actions\Csm\StoreCsmSurveyAction;
use App\Actions\Csm\ShowCsmSurveyFormAction;

class CsmController extends Controller
{
    public function create($requestId)
    {
        return (new ShowCsmSurveyFormAction)->execute($requestId, Auth::user());
    }

    public function store(StoreCsmSurveyRequest $request)
    {
        $user = Auth::user();

        return (new StoreCsmSurveyAction)->execute($request, $user);
    }
}
