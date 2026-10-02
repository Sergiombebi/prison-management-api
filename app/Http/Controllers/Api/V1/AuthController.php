<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\ProfilResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Hash factice, comparé quand l'identifiant n'existe pas : Hash::check() est volontairement
    // lent (bcrypt), donc sans cette comparaison de remplacement un identifiant inexistant
    // répondrait beaucoup plus vite qu'un mot de passe erroné sur un compte réel - une fuite de
    // temps qui permettrait de deviner quels comptes existent avant même de les attaquer.
    private const HASH_FACTICE = '$2y$12$Za94TZFWB/hCA4QFXA36n.fPgj7VPVirsE96GUpoXjVzWPaSS0Ahu';

    public function login(LoginRequest $request)
    {
        $identifiant = $request->validated('identifiant');
        $password = $request->validated('password');

        $user = User::query()
            ->where('username', $identifiant)
            ->orWhere('email', $identifiant)
            ->first();

        if (! Hash::check($password, $user->password ?? self::HASH_FACTICE) || ! $user) {
            throw ValidationException::withMessages([
                'identifiant' => ["Identifiants invalides."],
            ]);
        }

        if (! $user->est_actif) {
            throw ValidationException::withMessages([
                'identifiant' => ['Ce compte est désactivé.'],
            ]);
        }

        $user->update(['last_login_at' => now()]);

        // Même durée que le cookie de session côté frontend (lib/session.ts) : au-delà, le
        // jeton cesse de fonctionner tout seul, même s'il a fuité hors du navigateur.
        $token = $user->createToken('api', ['*'], now()->addHours(10))->plainTextToken;

        return response()->json([
            'user' => new ProfilResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request)
    {
        return new ProfilResource($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }
}
