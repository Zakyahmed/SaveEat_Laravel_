<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invendu;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class InvenduController extends Controller
{
    /**
     * Récupérer tous les invendus disponibles (filtrable)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invendu::with('restaurant');
        
        // Par défaut, ne montrer que les invendus disponibles
        $query->where('inv_date_limite', '>', now())
              ->where('inv_statut', 'disponible');
        
        // Ne montrer que les invendus des restaurants validés aux utilisateurs normaux
        if (!$request->user()->hasRole('admin')) {
            $query->whereHas('restaurant', function($q) {
                $q->where('rest_valide', 1);
            });
        }
        
        // Filtres optionnels
        if ($request->has('restaurant_id')) {
            $query->where('inv_rest_id', $request->restaurant_id);
        }
        
        if ($request->has('urgent') && $request->urgent) {
            $query->where('inv_urgent', true);
        }
        
        if ($request->has('date_min')) {
            $query->where('inv_date_disponibilite', '>=', $request->date_min);
        }
        
        if ($request->has('date_max')) {
            $query->where('inv_date_limite', '<=', $request->date_max);
        }
        
        // Filtrage par localité du restaurant
        if ($request->has('localite')) {
            $query->whereHas('restaurant', function($q) use ($request) {
                $q->where('rest_localite', 'like', '%' . $request->localite . '%');
            });
        }
        
        if ($request->has('npa')) {
            $query->whereHas('restaurant', function($q) use ($request) {
                $q->where('rest_npa', $request->npa);
            });
        }
        
        // Tri
        $sortBy = $request->input('sort_by', 'inv_date_limite');
        $sortDir = $request->input('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $invendus = $query->paginate($perPage);
        
        return response()->json($invendus);
    }
    
    /**
     * Récupérer un invendu spécifique
     */
    public function show($id): JsonResponse
    {
        $invendu = Invendu::with('restaurant')->findOrFail($id);
        
        // Vérifier si l'utilisateur peut voir cet invendu
        $user = auth()->user();
        $isOwner = $invendu->restaurant->rest_util_id == $user->util_id;
        
        if (!$isOwner && !$user->hasRole('admin') && !$invendu->restaurant->rest_valide) {
            return response()->json(['message' => 'Invendu non accessible'], 403);
        }
        
        return response()->json($invendu);
    }
    
    /**
     * Récupérer les invendus du restaurant de l'utilisateur
     */
    public function myInvendus(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurant = $user->restaurants()->first();
        
        if (!$restaurant) {
            return response()->json(['message' => 'Vous n\'avez pas de restaurant'], 404);
        }
        
        $query = Invendu::where('inv_rest_id', $restaurant->rest_id);
        
        // Filtres optionnels
        if ($request->has('statut')) {
            $query->where('inv_statut', $request->statut);
        }
        
        if ($request->has('date_min')) {
            $query->where('inv_date_disponibilite', '>=', $request->date_min);
        }
        
        if ($request->has('date_max')) {
            $query->where('inv_date_limite', '<=', $request->date_max);
        }
        
        // Tri
        $sortBy = $request->input('sort_by', 'inv_date_limite');
        $sortDir = $request->input('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $invendus = $query->paginate($perPage);
        
        return response()->json($invendus);
    }
    
    /**
     * Créer un nouvel invendu
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $restaurant = $user->restaurants()->first();
        
        if (!$restaurant) {
            return response()->json(['message' => 'Vous n\'avez pas de restaurant'], 403);
        }
        
        $validated = $request->validate([
            'titre' => 'required|string|max:100',
            'description' => 'nullable|string',
            'quantite' => 'required|numeric|min:0.1',
            'unite' => 'required|string|max:20',
            'date_disponibilite' => 'required|date|after_or_equal:now',
            'date_limite' => 'required|date|after:date_disponibilite',
            'allergenes' => 'nullable|string',
            'temperature' => 'nullable|string|max:50',
            'urgent' => 'boolean',
        ]);
        
        $invendu = new Invendu();
        $invendu->inv_titre = $validated['titre'];
        $invendu->inv_description = $validated['description'] ?? null;
        $invendu->inv_quantite = $validated['quantite'];
        $invendu->inv_unite = $validated['unite'];
        $invendu->inv_date_disponibilite = $validated['date_disponibilite'];
        $invendu->inv_date_limite = $validated['date_limite'];
        $invendu->inv_allergenes = $validated['allergenes'] ?? null;
        $invendu->inv_temperature = $validated['temperature'] ?? null;
        $invendu->inv_urgent = $validated['urgent'] ?? false;
        $invendu->inv_statut = 'disponible';
        $invendu->inv_rest_id = $restaurant->rest_id;
        $invendu->save();
        
        return response()->json(['message' => 'Invendu créé avec succès', 'invendu' => $invendu], 201);
    }
    
    /**
     * Mettre à jour un invendu existant
     */
    public function update(Request $request, $id):JsonResponse  
    {
        $user = $request->user();
        $invendu = Invendu::findOrFail($id);
        $restaurant = $user->restaurants()->first();
        
        // Vérification des permissions
        if (!$restaurant || $invendu->inv_rest_id != $restaurant->rest_id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Vérifier si l'invendu peut être modifié
        if ($invendu->inv_statut != 'disponible' && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Cet invendu ne peut plus être modifié'], 422);
        }
        
        $validated = $request->validate([
            'titre' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'quantite' => 'sometimes|numeric|min:0.1',
            'unite' => 'sometimes|string|max:20',
            'date_disponibilite' => 'sometimes|date',
            'date_limite' => 'sometimes|date|after:date_disponibilite',
            'allergenes' => 'nullable|string',
            'temperature' => 'nullable|string|max:50',
            'urgent' => 'boolean',
            'statut' => 'sometimes|in:disponible,reserve,annule,expire',
        ]);
        
        // Mise à jour des champs
        if (isset($validated['titre'])) $invendu->inv_titre = $validated['titre'];
        if (isset($validated['description'])) $invendu->inv_description = $validated['description'];
        if (isset($validated['quantite'])) $invendu->inv_quantite = $validated['quantite'];
        if (isset($validated['unite'])) $invendu->inv_unite = $validated['unite'];
        if (isset($validated['date_disponibilite'])) $invendu->inv_date_disponibilite = $validated['date_disponibilite'];
        if (isset($validated['date_limite'])) $invendu->inv_date_limite = $validated['date_limite'];
        if (isset($validated['allergenes'])) $invendu->inv_allergenes = $validated['allergenes'];
        if (isset($validated['temperature'])) $invendu->inv_temperature = $validated['temperature'];
        if (isset($validated['urgent'])) $invendu->inv_urgent = $validated['urgent'];
        if (isset($validated['statut'])) $invendu->inv_statut = $validated['statut'];
        
        $invendu->save();
        
        return response()->json(['message' => 'Invendu mis à jour avec succès', 'invendu' => $invendu]);
    }
    
    /**
     * Supprimer un invendu
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $invendu = Invendu::findOrFail($id);
        $restaurant = $user->restaurants()->first();
        
        // Vérification des permissions
        if (!$restaurant || $invendu->inv_rest_id != $restaurant->rest_id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Vérifier si l'invendu peut être supprimé
        if ($invendu->inv_statut == 'reserve' && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Cet invendu est réservé et ne peut pas être supprimé'], 422);
        }
        
        // Supprimer l'invendu
        $invendu->delete();
        
        return response()->json(['message' => 'Invendu supprimé avec succès']);
    }
    
    /**
     * Rechercher des invendus (suite)
     */
    public function search(Request $request): JsonResponse
    {
        $query = Invendu::with('restaurant');
        
        // Par défaut, ne montrer que les invendus disponibles et des restaurants validés
        $query->where('inv_date_limite', '>', now())
              ->where('inv_statut', 'disponible')
              ->whereHas('restaurant', function($q) {
                  $q->where('rest_valide', 1);
              });
        
        // Recherche par titre/description
        if ($request->has('q')) {
            $searchTerm = $request->q;
            $query->where(function($q) use ($searchTerm) {
                $q->where('inv_titre', 'like', "%{$searchTerm}%")
                  ->orWhere('inv_description', 'like', "%{$searchTerm}%");
            });
        }
        
        // Filtres par localisation du restaurant
        if ($request->has('npa')) {
            $query->whereHas('restaurant', function($q) use ($request) {
                $q->where('rest_npa', $request->npa);
            });
        }
        
        if ($request->has('localite')) {
            $query->whereHas('restaurant', function($q) use ($request) {
                $q->where('rest_localite', 'like', "%{$request->localite}%");
            });
        }
        
        if ($request->has('canton')) {
            $query->whereHas('restaurant', function($q) use ($request) {
                $q->where('rest_canton', $request->canton);
            });
        }
        
        // Filtre par urgence
        if ($request->has('urgent') && $request->urgent) {
            $query->where('inv_urgent', true);
        }
        
        // Filtres par date
        if ($request->has('disponible_apres')) {
            $query->where('inv_date_disponibilite', '>=', $request->disponible_apres);
        }
        
        if ($request->has('disponible_avant')) {
            $query->where('inv_date_disponibilite', '<=', $request->disponible_avant);
        }
        
        // Distance si lat/long fournies
        if ($request->has('lat') && $request->has('long') && $request->has('distance')) {
            $lat = $request->lat;
            $long = $request->long;
            $distance = $request->distance; // en km
            
            $query->whereHas('restaurant', function($q) use ($lat, $long, $distance) {
                $q->selectRaw(
                    "(6371 * acos(cos(radians(?)) * cos(radians(rest_latitude)) * cos(radians(rest_longitude) - radians(?)) + sin(radians(?)) * sin(radians(rest_latitude)))) AS distance", 
                    [$lat, $long, $lat]
                )
                ->having('distance', '<=', $distance);
            });
        }
        
        // Tri par défaut: par date limite (les plus proches en premier)
        $sortBy = $request->input('sort_by', 'inv_date_limite');
        $sortDir = $request->input('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $invendus = $query->paginate($perPage);
        
        return response()->json($invendus);
    }
}