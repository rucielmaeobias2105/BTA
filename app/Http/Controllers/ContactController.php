<?php

namespace App\Http\Controllers;

use App\Enums\InquiryTopic;
use App\Http\Requests\Customer\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Customer Flow 12 — Contact Us. Messages are stored for the admin inbox and
 * emailed to the salon.
 */
class ContactController extends Controller
{
    public function create(): View
    {
        return view('contact', [
            'topics' => InquiryTopic::options(),
            'settings' => \App\Models\SalonSetting::current(),
        ]);
    }

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $message = ContactMessage::create($request->validated());

        // Best-effort email to the salon; the DB row is the source of truth.
        try {
            \Illuminate\Support\Facades\Mail::raw(
                "New {$message->topic->label()} from {$message->name} <{$message->email}>\n\n{$message->message}",
                function ($mail) use ($message) {
                    $mail->to(config('mail.from.address'))
                        ->subject('[Contact] '.$message->topic->label().' — '.$message->name);
                }
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()
            ->route('contact.create')
            ->with('status', 'Thank you! Your message has been received and we will get back to you shortly.');
    }
}
