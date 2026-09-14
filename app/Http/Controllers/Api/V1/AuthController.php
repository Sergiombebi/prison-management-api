<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $identifiant = $request->validated('identifiant');
        $password = $request->validated('password');

        $user = User::query()
            ->where('username', $identifiant)
            ->orWhere('email', $identifiant)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'identifiant' => ["Identifiants invalides."],
            ]);
        }

        if (! $user->est_actif) {
            throw ValidationException::withMessages([
                'identifiant' => ['Ce compte est désactivé.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }
}
