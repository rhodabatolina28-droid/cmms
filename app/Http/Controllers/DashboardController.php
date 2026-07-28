<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\AdminDashboardAction;
use App\Actions\Dashboard\ItDashboardAction;
use App\Actions\Dashboard\SuperAdminDashboardAction;
use App\Actions\Dashboard\UserDashboardAction;

class DashboardController extends Controller
{
    public function adminDashboard()
    {
        return (new AdminDashboardAction)->execute();
    }

    public function superAdminDashboard()
    {
        return (new SuperAdminDashboardAction)->execute();
    }

    public function userDashboard()
    {
        return (new UserDashboardAction)->execute();
    }

    public function itDashboard()
    {
        return (new ItDashboardAction)->execute();
    }
}
