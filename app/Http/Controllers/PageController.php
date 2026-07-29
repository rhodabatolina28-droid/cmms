<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    /**
     * Show the landing page.
     */
    public function landing()
    {
        return view('landing');
    }

    /**
     * Show the logout success page.
     */
    public function loggedOut()
    {
        return view('auth.logout-success');
    }

    /**
     * Redirect old manual maintenance creation to PM Schedules.
     */
    public function maintenanceCreateRedirect()
    {
        return redirect()->route('pm-schedules.index');
    }

    /**
     * Redirect old manual maintenance store to PM Schedules.
     */
    public function maintenanceStoreRedirect()
    {
        return redirect()->route('pm-schedules.index');
    }
}