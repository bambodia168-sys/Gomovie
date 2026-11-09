<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\PlayerPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class PlayerSettingsController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'super_admin'], true), 403);
    }

    public function edit(Request $request): View
    {
        $this->authorizeAdmin($request);
        return view('admin.player-settings', [
            'preferences' => PlayerPreferences::current(),
            'accents' => PlayerPreferences::ACCENTS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'accent' => ['required', Rule::in(array_keys(PlayerPreferences::ACCENTS))],
            'density' => ['required', Rule::in(['comfortable', 'compact'])],
            'seek_seconds' => ['required', 'integer', Rule::in([5, 10, 15, 30])],
            'auto_hide_seconds' => ['required', 'integer', Rule::in([0, 3, 4, 5, 8])],
            'show_volume' => ['required', 'boolean'],
            'show_speed' => ['required', 'boolean'],
            'show_captions' => ['required', 'boolean'],
            'show_pip' => ['required', 'boolean'],
            'show_fullscreen' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            DB::table('player_preferences')->updateOrInsert(
                ['id' => 1],
                [...$data, 'updated_at' => now(), 'created_at' => now()]
            );
            DB::table('admin_audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'player.preferences.updated',
                'resource_type' => 'player_preferences',
                'resource_id' => 1,
                'details' => json_encode($data, JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.player-settings.edit')
            ->with('status', 'Player appearance and controls saved.');
    }
}
