<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Association extends Model
{
    use HasFactory;

    /**
     * La table associée au modèle.
     */
    protected $table = 'sea_association';
    
    /**
     * La clé primaire associée à la table.
     */
    protected $primaryKey = 'asso_id';
    
    /**
     * Les attributs qui sont assignables en masse.
     */
    protected $fillable = [
        'asso_nom',
        'asso_adresse',
        'asso_npa',
        'asso_localite',
        'asso_canton',
        'asso_latitude',
        'asso_longitude',
        'asso_ide',
        'asso_zewo',
        'asso_description',
        'asso_site_web',
        'asso_valide',
        'asso_util_id',
    ];
    

    protected $casts = [
        'asso_valide' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    /**
     * Relation avec l'utilisateur propriétaire
     */
    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'asso_util_id', 'util_id');
    }
    
    /**
     * Relation avec les réservations
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'res_asso_id', 'asso_id');
    }
}