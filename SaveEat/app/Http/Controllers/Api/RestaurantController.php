<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class RestaurantController extends Controller
{
    /**
     * Récupérer tous les restaurants (filtrable)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Restaurant::query();
        
        // Ne montrer que les restaurants validés aux utilisateurs normaux
        if (!$request->user()->hasRole('admin')) {
            $query->where('rest_valide', 1);
        }
        
        // Filtres optionnels
        if ($request->has('localite')) {
            $query->where('rest_localite', 'like', '%' . $request->localite . '%');
        }
        
        if ($request->has('npa')) {
            $query->where('rest_npa', $request->npa);
        }
        
        if ($request->has('canton')) {
            $query->where('rest_canton', $request->canton);
        }
        
        // Tri
        $sortBy = $request->input('sort_by', 'rest_nom');
        $sortDir = $request->input('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $restaurants = $query->paginate($perPage);
        
        return response()->json($restaurants);
    }
    
    /**
     * Récupérer un restaurant spécifique
     */
    public function show($id): JsonResponse
    {
        $restaurant = Restaurant::findOrFail($id);
        
        // Vérifier si l'utilisateur peut voir ce restaurant
        if (!$restaurant->rest_valide && !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Restaurant non accessible'], 403);
        }
        
        return response()->json($restaurant);
    }
    
    /**
     * Récupérer le restaurant de l'utilisateur connecté
     */
    public function myRestaurant(Request $request): JsonResponse
    {
        $restaurant = $request->user()->restaurants()->first();
        
        if (!$restaurant) {
            return response()->json(['message' => 'Vous n\'avez pas de restaurant'], 404);
        }
        
        return response()->json($restaurant);
    }
    
    /**
     * Créer un nouveau restaurant
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Vérifier si l'utilisateur a déjà un restaurant
        if ($user->restaurants()->exists()) {
            return response()->json(['message' => 'Vous avez déjà un restaurant'], 422);
        }
        
        $validated = $request->validate([
            'nom' => 'required|string|max:100',
            'adresse' => 'required|string|max:200',
            'npa' => 'required|string|max:10',
            'localite' => 'required|string|max:100',
            'canton' => 'required|string|max:50',
            'ide' => 'required|string|max:15|unique:sea_restaurant,rest_ide',
            'description' => 'nullable|string',
            'site_web' => 'nullable|url|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);
        
        $restaurant = new Restaurant();
        $restaurant->rest_nom = $validated['nom'];
        $restaurant->rest_adresse = $validated['adresse'];
        $restaurant->rest_npa = $validated['npa'];
        $restaurant->rest_localite = $validated['localite'];
        $restaurant->rest_canton = $validated['canton'];
        $restaurant->rest_ide = $validated['ide'];
        $restaurant->rest_description = $validated['description'] ?? null;
        $restaurant->rest_site_web = $validated['site_web'] ?? null;
        $restaurant->rest_latitude = $validated['latitude'] ?? null;
        $restaurant->rest_longitude = $validated['longitude'] ?? null;
        $restaurant->rest_valide = 0; // Par défaut non validé
        $restaurant->rest_util_id = $user->util_id;
        $restaurant->save();
        
        // S'assurer que l'utilisateur a le rôle restaurant
        $user->assignRole('restaurant');
        
        return response()->json(['message' => 'Restaurant créé avec succès', 'restaurant' => $restaurant], 201);
    }
    
    /**
     * Mettre à jour un restaurant existant
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $restaurant = Restaurant::findOrFail($id);
        
        // Vérification des permissions
        if ($restaurant->rest_util_id != $user->util_id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        $validated = $request->validate([
            'nom' => 'sometimes|string|max:100',
            'adresse' => 'sometimes|string|max:200',
            'npa' => 'sometimes|string|max:10',
            'localite' => 'sometimes|string|max:100',
            'canton' => 'sometimes|string|max:50',
            'ide' => 'sometimes|string|max:15|unique:sea_restaurant,rest_ide,' . $id . ',rest_id',
            'description' => 'nullable|string',
            'site_web' => 'nullable|url|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);
        
        // Mise à jour des champs
        if (isset($validated['nom'])) $restaurant->rest_nom = $validated['nom'];
        if (isset($validated['adresse'])) $restaurant->rest_adresse = $validated['adresse'];
        if (isset($validated['npa'])) $restaurant->rest_npa = $validated['npa'];
        if (isset($validated['localite'])) $restaurant->rest_localite = $validated['localite'];
        if (isset($validated['canton'])) $restaurant->rest_canton = $validated['canton'];
        if (isset($validated['ide'])) $restaurant->rest_ide = $validated['ide'];
        if (isset($validated['description'])) $restaurant->rest_description = $validated['description'];
        if (isset($validated['site_web'])) $restaurant->rest_site_web = $validated['site_web'];
        if (isset($validated['latitude'])) $restaurant->rest_latitude = $validated['latitude'];
        if (isset($validated['longitude'])) $restaurant->rest_longitude = $validated['longitude'];
        
        // Si admin, possibilité de valider directement
        if ($user->hasRole('admin') && $request->has('valide')) {
            $restaurant->rest_valide = (bool)$request->valide;
        }
        
        $restaurant->save();
        
        return response()->json(['message' => 'Restaurant mis à jour avec succès', 'restaurant' => $restaurant]);
    }
    
    /**
     * Supprimer un restaurant
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $restaurant = Restaurant::findOrFail($id);
        
        // Vérification des permissions
        if ($restaurant->rest_util_id != $user->util_id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        // Supprimer le restaurant
        $restaurant->delete();
        
        return response()->json(['message' => 'Restaurant supprimé avec succès']);
    }
    
    /**
     * Rechercher des restaurants
     */
    public function search(Request $request): JsonResponse
    {
        $query = Restaurant::query();
        
        // Toujours filtrer sur les restaurants validés pour la recherche publique
        $query->where('rest_valide', 1);
        
        // Recherche par nom
        if ($request->has('q')) {
            $searchTerm = $request->q;
            $query->where(function($q) use ($searchTerm) {
                $q->where('rest_nom', 'like', "%{$searchTerm}%")
                  ->orWhere('rest_description', 'like', "%{$searchTerm}%");
            });
        }
        
        // Filtres par localisation
        if ($request->has('npa')) {
            $query->where('rest_npa', $request->npa);
        }
        
        if ($request->has('localite')) {
            $query->where('rest_localite', 'like', "%{$request->localite}%");
        }
        
        if ($request->has('canton')) {
            $query->where('rest_canton', $request->canton);
        }
        
        // Distance si lat/long fournies
        if ($request->has('lat') && $request->has('long') && $request->has('distance')) {
            $lat = $request->lat;
            $long = $request->long;
            $distance = $request->distance; // en km
            
            // Calcul approximatif de la distance
            $query->selectRaw(
                "*, (6371 * acos(cos(radians(?)) * cos(radians(rest_latitude)) * cos(radians(rest_longitude) - radians(?)) + sin(radians(?)) * sin(radians(rest_latitude)))) AS distance", 
                [$lat, $long, $lat]
            )
            ->having('distance', '<=', $distance)
            ->orderBy('distance');
        }
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $restaurants = $query->paginate($perPage);
        
        return response()->json($restaurants);
    }
}