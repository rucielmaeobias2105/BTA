<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin Flow 9 — Registered Users Management.
 * View / search / deactivate / delete only. No new-entity input fields.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $term = $request->input('search');
        $status = $request->input('status');

        $users = User::query()
            ->when($term, function ($q, $term) {
                $like = '%'.str_replace('%', '\%', $term).'%';

                $q->where(fn ($inner) => $inner
                    ->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]));
            })
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount('appointments')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => ['search' => $term, 'status' => $status],
            'activeCount' => User::query()->where('is_active', true)->count(),
            'inactiveCount' => User::query()->where('is_active', false)->count(),
        ]);
    }

    public function show(User $user): View
    {
        $user->load(['appointments' => fn ($q) => $q->with('serviceLines')->latest(), 'reviews']);

        $stats = [
            'total' => $user->appointments()->count(),
            'completed' => $user->appointments()->where('status', AppointmentStatus::Completed)->count(),
            'cancelled' => $user->appointments()->where('status', AppointmentStatus::Cancelled)->count(),
            'total_spent' => (float) $user->appointments()
                ->whereIn('status', [
                    AppointmentStatus::Confirmed->value,
                    AppointmentStatus::InProgress->value,
                    AppointmentStatus::Completed->value,
                ])
                ->sum('total_amount'),
        ];

        return view('admin.users.show', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    /**
     * Deactivate / reactivate — preferred over deletion.
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        $user->is_active = ! $user->is_active;
        $user->save();

        return back()->with('status', $user->full_name.($user->is_active ? ' reactivated.' : ' deactivated.'));
    }

    /**
     * Soft delete. Appointments are retained for reporting.
     */
    public function destroy(User $user): RedirectResponse
    {
        // auth('admin') resolves to the guard, so the user has to be pulled
        // off it explicitly before reading the id.
        if ($user->id === auth('admin')->user()?->id) {
            return back()->withErrors(['user' => 'You cannot delete your own admin-linked account.']);
        }

        $name = $user->full_name;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', "\"{$name}\" has been deleted.");
    }
}
