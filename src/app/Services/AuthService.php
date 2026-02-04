<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function login(string $nickname, string $password): array
    {
        $user = User::where('nickname', $nickname)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'nickname' => ['Invalid credentials'],
            ]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }

    public function register(string $nickname, string $email, string $password): array
    {
        $user = User::create([
            'nickname' => $nickname,
            'name'     => $nickname,
            'email'    => $email,
            'password' => Hash::make($password),
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }
}
