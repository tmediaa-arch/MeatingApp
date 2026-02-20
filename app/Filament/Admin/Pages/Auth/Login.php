<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Custom Login — ورود با ایمیل یا نام کاربری
 */
class Login extends BaseLogin
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('login')
                    ->label('ایمیل یا نام کاربری')
                    ->required()
                    ->autocomplete('username')
                    ->autofocus()
                    ->extraInputAttributes(['tabindex' => 1]),

                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();

        $login    = trim($data['login'] ?? '');
        $password = $data['password'] ?? '';
        $remember = (bool) ($data['remember'] ?? false);

        // تشخیص email یا username
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (!Auth::attempt([$field => $login, 'password' => $password], $remember)) {
            $this->throwFailureValidationException();
        }

        $user = Auth::user();

        // بررسی canAccessPanel
        if (! $user->canAccessPanel(filament()->getCurrentPanel())) {
            Auth::logout();
            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => 'مشخصات وارد شده صحیح نیست.',
        ]);
    }
}