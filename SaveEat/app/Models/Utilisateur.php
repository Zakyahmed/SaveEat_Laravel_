<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Utilisateur extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles;

    protected $table = 'sea_utilisateur';
    protected $primaryKey = 'util_id';

    protected $fillable = [
        'util_email',
        'util_mdp',
        'util_nom',
        'util_prenom',
        'util_telephone',
        'util_date_inscription',
        'util_image_profil',
        'util_username',
        'util_derniere_connexion',
    ];

    protected $hidden = [
        'util_mdp',
        'remember_token',
    ];

    protected $dates = [
        'util_date_inscription',
        'util_derniere_connexion',
        'email_verified_at',
    ];

    protected $casts = [
        'util_date_inscription' => 'date',
        'util_derniere_connexion' => 'datetime',
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Pour l'authentification Laravel
    public function getAuthPassword()
    {
        return $this->util_mdp;
    }

    // Relations
    public function restaurants()
    {
        return $this->hasMany(Restaurant::class, 'rest_util_id', 'util_id');
    }

    public function associations()
    {
        return $this->hasMany(Association::class, 'asso_util_id', 'util_id');
    }

    public function justificatifs()
    {
        return $this->hasMany(Justificatif::class, 'just_util_id', 'util_id');
    }

    // Déterminer le type d'utilisateur
    public function getUserType()
    {
        if ($this->hasRole('admin')) {
            return 'admin';
        } elseif ($this->restaurants()->exists()) {
            return 'restaurant';
        } elseif ($this->associations()->exists()) {
            return 'association';
        }
        
        return 'utilisateur';
    }
}