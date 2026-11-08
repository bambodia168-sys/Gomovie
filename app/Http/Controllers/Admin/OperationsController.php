<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Operations\OperationalSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class OperationsController extends Controller
{
    public function index(Request $request, OperationalSnapshot $inspector): View
    {
        abort_unless(in_array($request->user()->role,['admin','super_admin'],true),403);

        return view('admin.operations', [
            'snapshot'=>$inspector->inspect(),
            'hostAcceptance'=>app(\App\Services\Operations\HostAcceptanceRegistry::class)->report(),
            'recentEvents'=>DB::table('admin_audit_events')
                ->select('action','resource_type','created_at')
                ->orderByDesc('id')->limit(15)->get(),
        ]);
    }
}
