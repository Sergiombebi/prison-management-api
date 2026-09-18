<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReinitialiserMotDePasseRequest;
use App\Http\Requests\StoreUtilisateurRequest;
use App\Http\Requests\UpdateUtilisateurRequest;
use App\Http\Resources\UtilisateurResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UtilisateurController extends Controller
{
    public function index(Request $request)
    {
        $utilisateurs = User::query()
            ->orderBy('nom')
            ->paginate($this->perPage($request));

        return UtilisateurResource::collection($utilisateurs);
    }

    public function store(StoreUtilisateurRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        $data['est_actif'] = true;

        $utilisateur = User::create($data);

        return (new UtilisateurResource($utilisateur))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateUtilisateurRequest $request, User $utilisateur)
    {
        $utilisateur->update($request->validated());

        return new UtilisateurResource($utilisateur->fresh());
    }

    /**
     * Un administrateur ne peut pas se désactiver lui-même - ça couperait immédiatement
     * son propre accès, sans personne d'autre forcément connecté pour le réactiver.
     */
    public function desactiver(Request $request, User $utilisateur)
    {
        if ($utilisateur->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'utilisateur' => ['Vous ne pouvez pas désactiver votre propre compte.'],
            ]);
        }

        $utilisateur->update(['est_actif' => false]);

        return new UtilisateurResource($utilisateur->fresh());
    }

    public function restaurer(User $utilisateur)
    {
        $utilisateur->update(['est_actif' => true]);

        return new UtilisateurResource($utilisateur->fresh());
    }

    public function reinitialiserMotDePasse(ReinitialiserMotDePasseRequest $request, User $utilisateur)
    {
        $utilisateur->update(['password' => Hash::make($request->validated('password'))]);

        // Un changement de mot de passe invalide tous les jetons en cours - y compris
        // ceux d'une session éventuellement compromise, ce qui est le but d'une réinitialisation.
        $utilisateur->tokens()->delete();

        return response()->json([
            'message' => 'Mot de passe réinitialisé.',
        ]);
    }
}
