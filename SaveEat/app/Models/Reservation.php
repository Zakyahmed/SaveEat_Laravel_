<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    /**
     * La table associée au modèle.
     */
    protected $table = 'sea_reservation';
    
    /**
     * La clé primaire associée à la table.
     */
    protected $primaryKey = 'res_id';
    
    /**
     * Les attributs qui sont assignables en masse.
     */
    protected $fillable = [
        'res_date',
        'res_date_collecte',
        'res_statut',
        'res_commentaire',
        'res_inv_id',
        'res_asso_id',
    ];
    
    /**
     * Les attributs qui doivent être convertis en dates.
     */
    protected $dates = [
        'res_date',
        'res_date_collecte',
    ];
    

    protected $casts = [
        'res_date' => 'datetime',
        'res_date_collecte' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    /**
     * Relation avec l'invendu
     */
    public function invendu()
    {
        return $this->belongsTo(Invendu::class, 'res_inv_id', 'inv_id');
    }
    
    /**
     * Relation avec l'association
     */
    public function association()
    {
        return $this->belongsTo(Association::class, 'res_asso_id', 'asso_id');
    }
}