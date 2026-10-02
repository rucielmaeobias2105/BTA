<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use App\Support\SendsNotificationsQuietly;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Customer Flow 1 — Register.
 */
class RegisteredUserController extends Controller
{
    use SendsNotificationsQuietly;

    public function create(): View
    {
        // No panelTitle/panelScript here on purpose. The customer sign-in
        // screens deliberately share one brand panel, so login and register
        // fall through to the shared auth layout defaults and cannot drift
        // apart. The password-recovery screens still pass their own copy,
        // because each one is a different step in a single flow.
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            'first_name' => $request->string('first_name')->toString(),
            'last_name' => $request->string('last_name')->toString(),
            'email' => $request->string('email')->toString(),
            'username' => User::deriveUsername($request->string('email')->toString()),
            'contact_number' => $request->string('contact_number')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
            'is_active' => true,
        ]);

        event(new Registered($user));

        // Best-effort. The account exists and is about to be signed in; a wrong
        // SMTP password must not turn a successful registration into a 500 the
        // customer reads as "registration failed".
        $this->notifyQuietly($user, new WelcomeNotification, 'customer registration');

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')
            ->with('status', 'Welcome to '.config('app.name').', '.$user->first_name.'!');
    }
}
