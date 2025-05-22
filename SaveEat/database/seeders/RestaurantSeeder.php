<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Restaurant;
use App\Models\Utilisateur;
use Carbon\Carbon;

class RestaurantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer les utilisateurs avec le rôle restaurant
        $restaurantUsers = Utilisateur::role('restaurant')->get();

        $restaurants = [
            [
                'rest_nom' => 'Le Petit Bistrot',
                'rest_adresse' => '10 Rue du Commerce',
                'rest_npa' => '1204',
                'rest_localite' => 'Genève',
                'rest_canton' => 'Genève',
                'rest_latitude' => 46.201756,
                'rest_longitude' => 6.146601,
                'rest_ide' => 'CHE-123.456.789',
                'rest_description' => 'Restaurant traditionnel proposant une cuisine française de qualité à base de produits frais.',
                'rest_site_web' => 'https://petitbistrot.example.com',
                'rest_valide' => true,
            ],
            [
                'rest_nom' => 'Pasta Mia',
                'rest_adresse' => '25 Rue de Lausanne',
                'rest_npa' => '1201',
                'rest_localite' => 'Genève',
                'rest_canton' => 'Genève',
                'rest_latitude' => 46.213987,
                'rest_longitude' => 6.143502,
                'rest_ide' => 'CHE-234.567.890',
                'rest_description' => 'Restaurant italien proposant des pâtes fraîches faites maison et des pizzas au feu de bois.',
                'rest_site_web' => 'https://pastamia.example.com',
                'rest_valide' => true,
            ],
            [
                'rest_nom' => 'Le Sushi Bar',
                'rest_adresse' => '5 Boulevard Helvétique',
                'rest_npa' => '1207',
                'rest_localite' => 'Genève',
                'rest_canton' => 'Genève',
                'rest_latitude' => 46.199238,
                'rest_longitude' => 6.156434,
                'rest_ide' => 'CHE-345.678.901',
                'rest_description' => 'Restaurant japonais authentique avec un large choix de sushis, sashimis et plats chauds.',
                'rest_site_web' => 'https://sushibar.example.com',
                'rest_valide' => false, // En attente de validation
            ],
        ];

        foreach ($restaurants as $index => $restaurantData) {
            if (isset($restaurantUsers[$index])) {
                $restaurantData['rest_util_id'] = $restaurantUsers[$index]->util_id;
                Restaurant::create($restaurantData);
            }
        }
    }
}