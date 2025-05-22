<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invendu extends Model
{
    use HasFactory;

    /**
     * La table associée au modèle.
     */
    protected $table = 'sea_invendu';
    
    /**
     * La clé primaire associée à la table.
     */
    protected $primaryKey = 'inv_id';
    
    /**
     * Les attributs qui sont assignables en masse.
     */
    protected $fillable = [
        'inv_titre',
        'inv_description',
        'inv_quantite',
        'inv_unite',
        'inv_date_disponibilite',
        'inv_date_limite',
        'inv_statut',
        'inv_urgent',
        'inv_allergenes',
        'inv_temperature',
        'inv_rest_id',
    ];
    
    /**
     * Les attributs qui doivent être convertis en dates.
     */
    protected $dates = [
        'inv_date_disponibilite',
        'inv_date_limite',
    ];
    

    protected $casts = [
        'inv_urgent' => 'boolean',
        'inv_date_disponibilite' => 'datetime',
        'inv_date_limite' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    /**
     * Relation avec le restaurant
     */
    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'inv_rest_id', 'rest_id');
    }
    
    /**
     * Relation avec les réservations
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'res_inv_id', 'inv_id');
    }
}