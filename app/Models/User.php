<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\RoleUtilisateur;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nom',
        'prenom',
        'username',
        'email',
        'password',
        'role',
        'permissions',
        'est_actif',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => RoleUtilisateur::class,
            'permissions' => 'array',
            'est_actif' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Les droits ne dépendent jamais du rôle (simple étiquette d'affichage), seulement
     * des permissions explicitement accordées à ce compte.
     */
    public function hasPermission(Permission|string $permission): bool
    {
        $valeur = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($valeur, $this->permissions ?? [], true);
    }
}
