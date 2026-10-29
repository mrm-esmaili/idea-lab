<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemStatus;

class SystemStatusController extends Controller
{
    public function __invoke(SystemStatus $status)
    {
        $checks = collect($status->checks());

        return view('admin.system.index', [
            'groups' => $checks->groupBy('group'),
            'counts' => $checks->countBy('status'),
        ]);
    }
}
