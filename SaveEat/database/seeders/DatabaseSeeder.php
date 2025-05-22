<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Les rôles et permissions sont déjà créés via la migration
        // Les seeder doivent être exécutés dans cet ordre à cause des dépendances
        $this->call([
            UtilisateurSeeder::class,
            RestaurantSeeder::class,
            AssociationSeeder::class,
            InvenduSeeder::class,
            ReservationSeeder::class,
            JustificatifSeeder::class,
        ]);
    }
}