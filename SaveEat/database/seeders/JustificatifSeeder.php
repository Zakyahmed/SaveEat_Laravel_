<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Justificatif;
use App\Models\Utilisateur;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JustificatifSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer tous les utilisateurs sauf l'admin
        $utilisateurs = Utilisateur::whereDoesntHave('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();
        
        if ($utilisateurs->isEmpty()) {
            $this->command->info('Aucun utilisateur trouvé pour créer des justificatifs.');
            return;
        }

        // Créer le répertoire de stockage des justificatifs si nécessaire
        $storagePath = storage_path('app/justificatifs');
        if (!File::exists($storagePath)) {
            File::makeDirectory($storagePath, 0755, true);
        }

        $statuts = ['en_attente', 'accepte', 'refuse'];
        $types = ['identite', 'restaurant', 'association'];

        foreach ($utilisateurs as $index => $utilisateur) {
            // Créer un répertoire pour chaque utilisateur
            $userPath = 'justificatifs/' . $utilisateur->util_id;
            if (!Storage::exists($userPath)) {
                Storage::makeDirectory($userPath);
            }

            // Générer un nom de fichier
            $fileName = 'justificatif_' . Str::random(10) . '.pdf';
            $filePath = $userPath . '/' . $fileName;

            // Créer un fichier factice (on ne peut pas réellement créer de fichier ici)
            // Dans un environnement réel, vous pourriez copier un fichier de test
            // Storage::put($filePath, 'Contenu du justificatif de test');

            $statusIndex = $index % count($statuts);
            $typeIndex = $index % count($types);

            // Commentaire différent selon le statut
            $commentaire = null;
            if ($statuts[$statusIndex] === 'accepte') {
                $commentaire = 'Justificatif validé, merci.';
            } elseif ($statuts[$statusIndex] === 'refuse') {
                $commentaire = 'Document illisible, veuillez renvoyer un scan de meilleure qualité.';
            }

            Justificatif::create([
                'just_nom_fichier' => $fileName,
                'just_chemin_fichier' => $filePath,
                'just_date_envoi' => Carbon::now()->subDays(rand(1, 20)),
                'just_statut' => $statuts[$statusIndex],
                'just_commentaire' => $commentaire,
                'just_util_id' => $utilisateur->util_id,
            ]);
        }
    }
}