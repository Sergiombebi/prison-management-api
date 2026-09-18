<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangerMotDePasseRequest;
use App\Http\Requests\UpdateProfilRequest;
use App\Http\Resources\ProfilResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfilController extends Controller
{
    public function update(UpdateProfilRequest $request)
    {
        $utilisateur = $request->user();
        $utilisateur->update($request->validated());

        return new ProfilResource($utilisateur->fresh());
    }

    public function changerMotDePasse(ChangerMotDePasseRequest $request)
    {
        $utilisateur = $request->user();
        $utilisateur->update(['password' => Hash::make($request->validated('password'))]);

        // On révoque les autres sessions (comme pour une réinitialisation par un admin),
        // mais pas le jeton courant : contrairement à un admin qui réinitialise le mot de
        // passe de quelqu'un d'autre, ici l'utilisateur vient de s'authentifier lui-même
        // avec son mot de passe actuel - pas de raison de le déconnecter.
        $utilisateur->tokens()->where('id', '!=', $utilisateur->currentAccessToken()->id)->delete();

        return response()->json([
            'message' => 'Mot de passe modifié.',
        ]);
    }
}
