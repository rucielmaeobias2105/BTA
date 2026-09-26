<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Customer Flow 9 — Notifications.
     * Read-only: system-generated content, customers can only mark as read.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $filter = $request->string('filter')->toString();
        $filter = in_array($filter, ['all', 'unread'], true) ? $filter : 'all';

        $notifications = $user->notifications()
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->paginate(12)
            ->withQueryString();

        return view('customer.notifications.index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => $user->unreadNotifications()->count(),
            'totalCount' => $user->notifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);

        $notification->markAsRead();

        $target = $notification->data['url'] ?? route('notifications.index');

        return back()->with('status', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }
}
