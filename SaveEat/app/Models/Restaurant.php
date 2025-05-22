<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    use HasFactory;

    /**
     * La table associée au modèle.
     */
    protected $table = 'sea_restaurant';
    
    /**
     * La clé primaire associée à la table.
     */
    protected $primaryKey = 'rest_id';
    
    /**
     * Les attributs qui sont assignables en masse.
     */
    protected $fillable = [
        'rest_nom',
        'rest_adresse',
        'rest_npa',
        'rest_localite',
        'rest_canton',
        'rest_latitude',
        'rest_longitude',
        'rest_ide',
        'rest_description',
        'rest_site_web',
        'rest_valide',
        'rest_util_id',
    ];

    protected $casts = [
        'rest_valide' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    
    /**
     * Relation avec l'utilisateur propriétaire
     */
    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'rest_util_id', 'util_id');
    }
    
    /**
     * Relation avec les invendus
     */
    public function invendus()
    {
        return $this->hasMany(Invendu::class, 'inv_rest_id', 'rest_id');
    }
}