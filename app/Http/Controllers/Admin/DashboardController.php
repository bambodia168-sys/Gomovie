<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Series;
use App\Models\User;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'metrics' => [
                'movies' => Movie::count(),
                'series' => Series::count(),
                'episodes' => Episode::count(),
                'members' => User::count(),
                'published_movies' => Movie::where('status', 'published')->count(),
                'published_series' => Series::where('status', 'published')->count(),
                'ingests_pending' => DB::table('media_ingests')->where('status', 'quarantined')->count(),
            ],
            'recentMovies' => Movie::latest()->limit(8)->get(),
        ]);
    }
}
