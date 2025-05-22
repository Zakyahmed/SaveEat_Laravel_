<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Invendu;
use App\Models\Restaurant;
use Carbon\Carbon;

class InvenduSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer les restaurants validés
        $restaurants = Restaurant::where('rest_valide', true)->get();
        
        if ($restaurants->isEmpty()) {
            $this->command->info('Aucun restaurant validé trouvé pour créer des invendus.');
            return;
        }

        $invendus = [
            // Restaurant 1 (Le Petit Bistrot)
            [
                'inv_titre' => 'Quiches et salades',
                'inv_description' => 'Lot de 5 parts de quiches (lorraine et légumes) et 3 salades composées.',
                'inv_quantite' => 5.0,
                'inv_unite' => 'portions',
                'inv_date_disponibilite' => Carbon::now()->addHours(2),
                'inv_date_limite' => Carbon::now()->addHours(24),
                'inv_statut' => 'disponible',
                'inv_urgent' => true,
                'inv_allergenes' => 'Gluten, lactose, œufs',
                'inv_temperature' => 'Réfrigéré',
                'restaurant_index' => 0, // Index du restaurant dans la collection
            ],
            [
                'inv_titre' => 'Desserts variés',
                'inv_description' => 'Assortiment de 10 pâtisseries (tartes, éclairs, mousses).',
                'inv_quantite' => 10.0,
                'inv_unite' => 'portions',
                'inv_date_disponibilite' => Carbon::now()->addHours(4),
                'inv_date_limite' => Carbon::now()->addHours(48),
                'inv_statut' => 'disponible',
                'inv_urgent' => false,
                'inv_allergenes' => 'Gluten, lactose, œufs, fruits à coque',
                'inv_temperature' => 'Réfrigéré',
                'restaurant_index' => 0,
            ],
            
            // Restaurant 2 (Pasta Mia)
            [
                'inv_titre' => 'Pâtes à la bolognaise',
                'inv_description' => 'Environ 3 kg de pâtes à la sauce bolognaise maison.',
                'inv_quantite' => 3.0,
                'inv_unite' => 'kg',
                'inv_date_disponibilite' => Carbon::now()->addHours(1),
                'inv_date_limite' => Carbon::now()->addHours(12),
                'inv_statut' => 'disponible',
                'inv_urgent' => true,
                'inv_allergenes' => 'Gluten, lactose',
                'inv_temperature' => 'Chaud, à maintenir ou refroidir',
                'restaurant_index' => 1,
            ],
            [
                'inv_titre' => 'Tiramisu',
                'inv_description' => 'Environ 2 kg de tiramisu.',
                'inv_quantite' => 2.0,
                'inv_unite' => 'kg',
                'inv_date_disponibilite' => Carbon::now()->addHours(3),
                'inv_date_limite' => Carbon::now()->addHours(24),
                'inv_statut' => 'disponible',
                'inv_urgent' => false,
                'inv_allergenes' => 'Lactose, œufs, gluten, peut contenir des traces d\'alcool',
                'inv_temperature' => 'Réfrigéré',
                'restaurant_index' => 1,
            ],
            [
                'inv_titre' => 'Pain et focaccia',
                'inv_description' => 'Assortiment de pains et focaccias du jour.',
                'inv_quantite' => 2.5,
                'inv_unite' => 'kg',
                'inv_date_disponibilite' => Carbon::now(),
                'inv_date_limite' => Carbon::now()->addHours(36),
                'inv_statut' => 'disponible',
                'inv_urgent' => false,
                'inv_allergenes' => 'Gluten',
                'inv_temperature' => 'Température ambiante',
                'restaurant_index' => 1,
            ],
            
            // Invendu déjà réservé
            [
                'inv_titre' => 'Plateau de fromages italiens',
                'inv_description' => 'Sélection de fromages italiens (parmesan, gorgonzola, ricotta).',
                'inv_quantite' => 1.0,
                'inv_unite' => 'plateau',
                'inv_date_disponibilite' => Carbon::now()->subHours(6),
                'inv_date_limite' => Carbon::now()->addHours(12),
                'inv_statut' => 'reserve',
                'inv_urgent' => false,
                'inv_allergenes' => 'Lactose',
                'inv_temperature' => 'Réfrigéré',
                'restaurant_index' => 1,
            ],
            
            // Invendu déjà distribué
            [
                'inv_titre' => 'Pizzas assorties',
                'inv_description' => '6 pizzas variées (margherita, 4 fromages, végétarienne).',
                'inv_quantite' => 6.0,
                'inv_unite' => 'pizzas',
                'inv_date_disponibilite' => Carbon::now()->subDays(2),
                'inv_date_limite' => Carbon::now()->subDays(1),
                'inv_statut' => 'distribue',
                'inv_urgent' => false,
                'inv_allergenes' => 'Gluten, lactose',
                'inv_temperature' => 'Température ambiante',
                'restaurant_index' => 1,
            ],
        ];

        foreach ($invendus as $invenduData) {
            $restaurantIndex = $invenduData['restaurant_index'];
            unset($invenduData['restaurant_index']);
            
            if (isset($restaurants[$restaurantIndex])) {
                $invenduData['inv_rest_id'] = $restaurants[$restaurantIndex]->rest_id;
                Invendu::create($invenduData);
            }
        }
    }
}