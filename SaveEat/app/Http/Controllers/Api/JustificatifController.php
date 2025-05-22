<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Justificatif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;


class JustificatifController extends Controller
{
   /**
 * Récupérer l'état des justificatifs de l'utilisateur connecté
 */
public function getStatus(Request $request): JsonResponse
{
    $user = $request->user();
    
    $justificatifs = Justificatif::where('just_util_id', $user->util_id)
        ->orderBy('just_date_envoi', 'desc')
        ->get();
    
    // Format optimisé pour le frontend
    $formattedJustificatifs = $justificatifs->map(function($justificatif) {
        return [
            'id' => $justificatif->just_id,
            'nom_fichier' => $justificatif->just_nom_fichier,
            'date_envoi' => $justificatif->just_date_envoi,
            'statut' => $justificatif->just_statut,
            'commentaire' => $justificatif->just_commentaire,
            'type' => $justificatif->just_type
        ];
    });
    
    return response()->json([
        'justificatifs' => $formattedJustificatifs,
        'has_pending' => $justificatifs->where('just_statut', 'en_attente')->count() > 0,
        'has_accepted' => $justificatifs->where('just_statut', 'accepte')->count() > 0,
        'last_update' => $justificatifs->max('updated_at')
    ]);
}

    /**
     * Afficher un justificatif spécifique
     */
    public function show($id): JsonResponse
    {
        $user = auth()->user();
        $justificatif = Justificatif::findOrFail($id);
        
        // Vérifier que l'utilisateur a le droit de voir ce justificatif
        if ($justificatif->just_util_id != $user->util_id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        return response()->json($justificatif);
    }
    
    /**
     * Télécharger le fichier justificatif
     */
    public function download($id): StreamedResponse
    {
        $user = auth()->user();
        $justificatif = Justificatif::findOrFail($id);
        
        // Vérifier que l'utilisateur a le droit de télécharger ce justificatif
        if ($justificatif->just_util_id != $user->util_id && !$user->hasRole('admin')) {
            abort(403, 'Non autorisé');
        }
        
        $filePath = $justificatif->just_chemin_fichier;
        
        if (!Storage::exists($filePath)) {
            abort(404, 'Fichier non trouvé');
        }
        
        return Storage::download($filePath, $justificatif->just_nom_fichier);
    }

    /**
     * Envoyer un nouveau justificatif
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $request->validate([
            'fichier' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB max
            'type' => 'required|in:identite,association,restaurant,autre',
            'commentaire' => 'nullable|string|max:500',
        ]);
        
        // Validation de sécurité supplémentaire
        $file = $request->file('fichier');
        
        // Vérifier que le fichier est bien un PDF ou une image
        $mimeType = $file->getMimeType();
        $allowedMimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/jpg'
        ];
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            return response()->json([
                'message' => 'Le type de fichier n\'est pas autorisé',
                'error' => 'format_invalide'
            ], 422);
        }
        
        // Générer un nom de fichier sécurisé
        $fileName = $file->getClientOriginalName();
        $fileExtension = $file->getClientOriginalExtension();
        $uniqueName = Str::uuid() . '.' . $fileExtension;
        
        // Créer le dossier si inexistant
        $userFolder = 'justificatifs/' . $user->util_id;
        if (!Storage::exists($userFolder)) {
            Storage::makeDirectory($userFolder);
        }
        
        $path = $file->storeAs($userFolder, $uniqueName);
        
        $justificatif = new Justificatif();
        $justificatif->just_nom_fichier = $fileName;
        $justificatif->just_chemin_fichier = $path;
        $justificatif->just_date_envoi = now();
        $justificatif->just_statut = 'en_attente';
        $justificatif->just_commentaire = $request->commentaire;
        $justificatif->just_type = $request->type; // S'assurer que le type est bien défini
        $justificatif->just_util_id = $user->util_id;
        $justificatif->save();
        
        // Journaliser l'activité
        \Log::info('Nouveau justificatif ajouté', [
            'user_id' => $user->util_id,
            'type' => $request->type,
            'justificatif_id' => $justificatif->just_id
        ]);
        
        return response()->json([
            'message' => 'Justificatif envoyé avec succès',
            'justificatif' => [
                'id' => $justificatif->just_id,
                'nom_fichier' => $justificatif->just_nom_fichier,
                'date_envoi' => $justificatif->just_date_envoi,
                'statut' => $justificatif->just_statut,
                'type' => $justificatif->just_type
            ]
        ], 201);
    }
    
    /**
     * Supprimer un justificatif
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $justificatif = Justificatif::findOrFail($id);
        
        // Vérifier que l'utilisateur a le droit de supprimer ce justificatif
        if ($justificatif->just_util_id != $user->util_id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // On ne peut supprimer que les justificatifs en attente ou refusés
        if (!in_array($justificatif->just_statut, ['en_attente', 'refuse']) && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Ce justificatif ne peut pas être supprimé'], 422);
        }
        
        // Supprimer le fichier physique
        Storage::delete($justificatif->just_chemin_fichier);
        
        // Supprimer l'enregistrement
        $justificatif->delete();
        
        return response()->json(['message' => 'Justificatif supprimé avec succès']);
    }
}