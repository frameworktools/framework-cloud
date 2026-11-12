<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /admin/users: everyone with an account, newest first, with where they
 * are in setup and how much they use the server. Administrators only.
 */
class UsersController extends Controller
{
    public function index(): Response
    {
        // Last activity from the session table, when sessions are kept in the database
        $lastActive = config('session.driver') === 'database'
            ? DB::table(config('session.table', 'sessions'))->whereNotNull('user_id')->groupBy('user_id')->pluck(DB::raw('max(last_activity)'), 'user_id')
            : collect();

        $users = User::query()
            ->withCount('projects')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
                'verified' => $user->email_verified_at !== null,
                'setup_done' => $user->setup_completed_at !== null,
                'is_admin' => (bool) $user->is_admin,
                'can_publish' => (bool) $user->can_publish,
                'projects' => $user->projects_count,
                'last_active' => isset($lastActive[$user->id]) ? now()->setTimestamp((int) $lastActive[$user->id])->toIso8601String() : null,
            ]);

        return Inertia::render('Admin/Users', [
            'users' => $users,
            'summary' => [
                'total' => $users->count(),
                'week' => $users->filter(fn ($u) => $u['created_at'] && now()->subDays(7)->lte($u['created_at']))->count(),
                'verified' => $users->where('verified', true)->count(),
                'setup_done' => $users->where('setup_done', true)->count(),
            ],
        ]);
    }
}
