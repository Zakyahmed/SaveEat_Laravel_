<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

class UtilisateurSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // L'admin est déjà créé dans la migration de rôles, on vérifie juste
        $admin = Utilisateur::where('util_email', 'admin@saveeat.com')->first();
        if (!$admin) {
            $admin = Utilisateur::create([
                'util_email' => 'admin@saveeat.com',
                'util_mdp' => Hash::make('admin123'),
                'util_nom' => 'Admin',
                'util_prenom' => 'SaveEat',
                'util_date_inscription' => Carbon::now()->format('Y-m-d'),
                'util_derniere_connexion' => Carbon::now(),
            ]);
            $admin->assignRole('admin');
        }

        // Utilisateurs pour restaurants
        $restaurantUsers = [
            [
                'util_email' => 'resto1@example.com',
                'util_mdp' => Hash::make('password'),
                'util_nom' => 'Dupont',
                'util_prenom' => 'Jean',
                'util_telephone' => '0223456789',
                'util_date_inscription' => Carbon::now()->subDays(30)->format('Y-m-d'),
                'util_username' => 'resto_jean',
                'util_derniere_connexion' => Carbon::now()->subDays(2),
            ],
            [
                'util_email' => 'resto2@example.com',
                'util_mdp' => Hash::make('password'),
                'util_nom' => 'Martin',
                'util_prenom' => 'Sophie',
                'util_telephone' => '0223456790',
                'util_date_inscription' => Carbon::now()->subDays(25)->format('Y-m-d'),
                'util_username' => 'resto_sophie',
                'util_derniere_connexion' => Carbon::now()->subDays(1),
            ],
            [
                'util_email' => 'resto3@example.com',
                'util_mdp' => Hash::make('password'),
                'util_nom' => 'Blanc',
                'util_prenom' => 'Michel',
                'util_telephone' => '0223456791',
                'util_date_inscription' => Carbon::now()->subDays(20)->format('Y-m-d'),
                'util_username' => 'resto_michel',
                'util_derniere_connexion' => Carbon::now()->subDays(3),
            ],
        ];

        foreach ($restaurantUsers as $userData) {
            $user = Utilisateur::create($userData);
            $user->assignRole('restaurant');
        }

        // Utilisateurs pour associations
        $associationUsers = [
            [
                'util_email' => 'asso1@example.com',
                'util_mdp' => Hash::make('password'),
                'util_nom' => 'Durand',
                'util_prenom' => 'Marie',
                'util_telephone' => '0223456792',
                'util_date_inscription' => Carbon::now()->subDays(15)->format('Y-m-d'),
                'util_username' => 'asso_marie',
                'util_derniere_connexion' => Carbon::now()->subHours(12),
            ],
            [
                'util_email' => 'asso2@example.com',
                'util_mdp' => Hash::make('password'),
                'util_nom' => 'Robert',
                'util_prenom' => 'Paul',
                'util_telephone' => '0223456793',
                'util_date_inscription' => Carbon::now()->subDays(10)->format('Y-m-d'),
                'util_username' => 'asso_paul',
                'util_derniere_connexion' => Carbon::now()->subDays(1),
            ],
            [
                'util_email' => 'asso3@example.com',
                'util_mdp' => Hash::make('password'),
                'util_nom' => 'Legrand',
                'util_prenom' => 'Julie',
                'util_telephone' => '0223456794',
                'util_date_inscription' => Carbon::now()->subDays(5)->format('Y-m-d'),
                'util_username' => 'asso_julie',
                'util_derniere_connexion' => Carbon::now()->subHours(6),
            ],
        ];

        foreach ($associationUsers as $userData) {
            $user = Utilisateur::create($userData);
            $user->assignRole(roles: 'association');
        }
    }
}