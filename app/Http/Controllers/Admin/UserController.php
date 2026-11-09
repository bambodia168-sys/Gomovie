<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(in_array($request->user()->role, ['admin', 'super_admin'], true), 403);

        return view('admin.users', [
            'users' => User::query()->select(['id', 'name', 'email', 'role', 'is_active', 'email_verified_at', 'created_at'])
                ->when($request->filled('q'), function ($query) use ($request) {
                    $q=trim((string)$request->input('q'));
                    abort_if(mb_strlen($q)>100,422);
                    $query->where(fn ($userQuery) => $userQuery
                        ->where('name','like','%'.$q.'%')
                        ->orWhere('email','like','%'.$q.'%'));
                })
                ->orderByDesc('id')->paginate(10)->withQueryString(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->role === 'super_admin', 403);
        $data = $request->validate([
            'role' => ['required', Rule::in(['viewer', 'editor', 'admin'])],
            'is_active' => ['required', 'boolean'],
        ]);
        // Super-admin grants and changes are server-only. Never alter another super admin here.
        abort_if($user->role === 'super_admin' || $user->id === $request->user()->id, 403);
        DB::transaction(function () use ($user, $data, $request): void {
            $user->forceFill($data)->save();
            DB::table('admin_audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'user.role_or_status_updated',
                'resource_type' => 'user', 'resource_id' => $user->id,
                'details' => json_encode($data), 'ip_address' => $request->ip(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return back()->with('status', 'User permissions updated.');
    }
}
