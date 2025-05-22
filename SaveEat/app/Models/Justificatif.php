<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Justificatif extends Model
{
    use HasFactory;

    /**
     * La table associée au modèle.
     */
    protected $table = 'sea_justificatif';
    
    /**
     * La clé primaire associée à la table.
     */
    protected $primaryKey = 'just_id';
    
    /**
     * Les attributs qui sont assignables en masse.
     */
    protected $fillable = [
        'just_nom_fichier',
        'just_chemin_fichier',
        'just_date_envoi',
        'just_statut',
        'just_commentaire',
        'just_util_id',
    ];

    protected $casts = [
        'just_date_envoi' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Les attributs qui doivent être convertis en dates.
     */
    protected $dates = [
        'just_date_envoi',
    ];
    
    /**
     * Relation avec l'utilisateur
     */
    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'just_util_id', 'util_id');
    }
}