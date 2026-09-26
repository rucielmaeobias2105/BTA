<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Customer Flow 1 — Register.
 */
class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'panelTitle' => 'Join Our<br>Beauty Family',
            'panelScript' => 'start glowing today.',
        ]);
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
        $user->notify(new WelcomeNotification);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('status', 'Welcome to '.config('app.name').', '.$user->first_name.'!');
    }
}
