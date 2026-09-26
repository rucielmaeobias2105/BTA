<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Inbox for messages submitted through Customer Flow 12 (Contact Us).
 */
class ContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        $messages = ContactMessage::query()
            ->when($request->input('filter') === 'unread', fn ($q) => $q->unread())
            ->when($request->input('search'), function ($q, $term) {
                $like = '%'.str_replace('%', '\%', $term).'%';

                $q->where(fn ($inner) => $inner
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('message', 'like', $like));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.messages.index', [
            'messages' => $messages,
            'filters' => $request->only(['search', 'filter']),
            'unreadCount' => ContactMessage::query()->unread()->count(),
        ]);
    }

    public function update(Request $request, ContactMessage $message): RedirectResponse
    {
        $data = $request->validate([
            'admin_reply' => ['nullable', 'string', 'max:3000'],
        ]);

        $message->update([
            'is_read' => true,
            'admin_reply' => $data['admin_reply'] ?? null,
            'replied_at' => isset($data['admin_reply']) ? now() : $message->replied_at,
        ]);

        return back()->with('status', 'Reply saved.');
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return back()->with('status', 'Message deleted.');
    }
}
