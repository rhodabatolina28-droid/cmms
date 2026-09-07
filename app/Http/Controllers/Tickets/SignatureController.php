<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Request as RequestModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * D5b: serves ticket signatures from the PRIVATE disk (storage/app/private)
 * through an authed, policy-checked route. No /storage/ direct URLs.
 *
 * Access rule: identical to viewing the ticket itself —
 * ICT  → viewIct policy,  PM → viewMaintenance policy.
 */
class SignatureController extends Controller
{
    private const ALLOWED_FIELDS = [
        'technician_signature',
        'end_user_signature',
        'it_personnel_signature',
        'end_user_acceptance_signature',
    ];

    public function show(RequestModel $ticket, string $field)
    {
        abort_unless(in_array($field, self::ALLOWED_FIELDS, true), 404);

        $user = Auth::user();
        $canView = $ticket->type === 'ICT'
            ? $user->can('viewIct', $ticket)
            : $user->can('viewMaintenance', $ticket);
        abort_unless($canView, 403, 'Unauthorized access to this signature.');

        $detail = $ticket->type === 'ICT' ? $ticket->repairRequest : $ticket->maintenanceRequest;
        $path = $detail?->{$field};

        abort_if(empty($path), 404);
        abort_unless(str_starts_with($path, 'signatures/'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
