<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Association;
use App\Models\Utilisateur;
use Carbon\Carbon;

class AssociationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer les utilisateurs avec le rôle association
        $associationUsers = Utilisateur::role('association')->get();

        $associations = [
            [
                'asso_nom' => 'Aide Alimentaire Genève',
                'asso_adresse' => '15 Rue des Pâquis',
                'asso_npa' => '1201',
                'asso_localite' => 'Genève',
                'asso_canton' => 'Genève',
                'asso_latitude' => 46.210856,
                'asso_longitude' => 6.148601,
                'asso_ide' => 'CHE-456.789.012',
                'asso_zewo' => 'ZEWO-12345',
                'asso_description' => 'Association caritative venant en aide aux personnes défavorisées en leur fournissant des repas et des denrées alimentaires.',
                'asso_site_web' => 'https://aidegeneve.example.com',
                'asso_valide' => true,
            ],
            [
                'asso_nom' => 'Partage Solidaire',
                'asso_adresse' => '30 Avenue de la Paix',
                'asso_npa' => '1202',
                'asso_localite' => 'Genève',
                'asso_canton' => 'Genève',
                'asso_latitude' => 46.220654,
                'asso_longitude' => 6.139752,
                'asso_ide' => 'CHE-567.890.123',
                'asso_zewo' => 'ZEWO-23456',
                'asso_description' => 'Association luttant contre le gaspillage alimentaire et pour la solidarité sociale en redistribuant les surplus alimentaires.',
                'asso_site_web' => 'https://partagesolidaire.example.com',
                'asso_valide' => true,
            ],
            [
                'asso_nom' => 'SOS Jeunes',
                'asso_adresse' => '8 Rue du Rhône',
                'asso_npa' => '1204',
                'asso_localite' => 'Genève',
                'asso_canton' => 'Genève',
                'asso_latitude' => 46.202345,
                'asso_longitude' => 6.146789,
                'asso_ide' => 'CHE-678.901.234',
                'asso_zewo' => 'ZEWO-34567',
                'asso_description' => 'Association venant en aide aux jeunes en difficulté par l\'aide alimentaire, l\'hébergement et l\'insertion professionnelle.',
                'asso_site_web' => 'https://sosjeunes.example.com',
                'asso_valide' => false, // En attente de validation
            ],
        ];

        foreach ($associations as $index => $associationData) {
            if (isset($associationUsers[$index])) {
                $associationData['asso_util_id'] = $associationUsers[$index]->util_id;
                Association::create($associationData);
            }
        }
    }
}