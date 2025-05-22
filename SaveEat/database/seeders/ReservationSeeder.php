<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Reservation;
use App\Models\Invendu;
use App\Models\Association;
use Carbon\Carbon;

class ReservationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer les associations validées
        $associations = Association::where('asso_valide', true)->get();
        
        if ($associations->isEmpty()) {
            $this->command->info('Aucune association validée trouvée pour créer des réservations.');
            return;
        }
        
        // Récupérer l'invendu déjà marqué comme réservé
        $invenduReserve = Invendu::where('inv_statut', 'reserve')->first();
        
        // Récupérer l'invendu déjà marqué comme distribué
        $invenduDistribue = Invendu::where('inv_statut', 'distribue')->first();
        
        // Récupérer des invendus disponibles
        $invendusDisponibles = Invendu::where('inv_statut', 'disponible')
            ->limit(2)
            ->get();
        
        $reservations = [];
        
        // Création d'une réservation pour l'invendu déjà réservé
        if ($invenduReserve) {
            $reservations[] = [
                'res_date' => Carbon::now()->subHours(5),
                'res_date_collecte' => Carbon::now()->addHour(),
                'res_statut' => 'accepte',
                'res_commentaire' => 'Nous passerons récupérer à l\'heure prévue.',
                'res_inv_id' => $invenduReserve->inv_id,
                'res_asso_id' => $associations[0]->asso_id,
            ];
        }
        
        // Création d'une réservation pour l'invendu déjà distribué
        if ($invenduDistribue) {
            $reservations[] = [
                'res_date' => Carbon::now()->subDays(2)->subHours(6),
                'res_date_collecte' => Carbon::now()->subDays(2),
                'res_statut' => 'termine',
                'res_commentaire' => 'Récupération effectuée, merci pour votre don!',
                'res_inv_id' => $invenduDistribue->inv_id,
                'res_asso_id' => $associations[1]->asso_id,
            ];
        }
        
        // Création de nouvelles réservations pour les invendus disponibles
        $statuts = ['en_attente', 'accepte'];
        
        foreach ($invendusDisponibles as $index => $invendu) {
            if (isset($associations[$index % count($associations)])) {
                $reservations[] = [
                    'res_date' => Carbon::now(),
                    'res_date_collecte' => Carbon::parse($invendu->inv_date_disponibilite)->addHours(2),
                    'res_statut' => $statuts[$index % count($statuts)],
                    'res_commentaire' => 'Nous avons besoin de ces invendus pour notre distribution du soir.',
                    'res_inv_id' => $invendu->inv_id,
                    'res_asso_id' => $associations[$index % count($associations)]->asso_id,
                ];
                
                // Mettre à jour le statut de l'invendu
                $invendu->inv_statut = 'reserve';
                $invendu->save();
            }
        }
        
        // Créer les réservations
        foreach ($reservations as $reservationData) {
            Reservation::create($reservationData);
        }
    }
}