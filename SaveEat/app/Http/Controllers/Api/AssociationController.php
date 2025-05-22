<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Association;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AssociationController extends Controller
{
    /**
     * Récupérer toutes les associations (filtrable)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Association::query();
        
        // Ne montrer que les associations validées aux utilisateurs normaux
        if (!$request->user()->hasRole('admin')) {
            $query->where('asso_valide', 1);
        }
        
        // Filtres optionnels
        if ($request->has('localite')) {
            $query->where('asso_localite', 'like', '%' . $request->localite . '%');
        }
        
        if ($request->has('npa')) {
            $query->where('asso_npa', $request->npa);
        }
        
        if ($request->has('canton')) {
            $query->where('asso_canton', $request->canton);
        }
        
        // Tri
        $sortBy = $request->input('sort_by', 'asso_nom');
        $sortDir = $request->input('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $associations = $query->paginate($perPage);
        
        return response()->json($associations);
    }
    
    /**
     * Récupérer une association spécifique
     */
    public function show($id): JsonResponse
    {
        $association = Association::findOrFail($id);
        
        // Vérifier si l'utilisateur peut voir cette association
        if (!$association->asso_valide && !auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Association non accessible'], 403);
        }
        
        return response()->json($association);
    }
    
    /**
     * Récupérer l'association de l'utilisateur connecté
     */
    public function myAssociation(Request $request): JsonResponse
    {
        $association = $request->user()->associations()->first();
        
        if (!$association) {
            return response()->json(['message' => 'Vous n\'avez pas d\'association'], 404);
        }
        
        return response()->json($association);
    }
    
    /**
     * Créer une nouvelle association
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Vérifier si l'utilisateur a déjà une association
        if ($user->associations()->exists()) {
            return response()->json(['message' => 'Vous avez déjà une association'], 422);
        }
        
        $validated = $request->validate([
            'nom' => 'required|string|max:100',
            'adresse' => 'required|string|max:200',
            'npa' => 'required|string|max:10',
            'localite' => 'required|string|max:100',
            'canton' => 'required|string|max:50',
            'ide' => 'nullable|string|max:15|unique:sea_association,asso_ide',
            'zewo' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'site_web' => 'nullable|url|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);
        
        $association = new Association();
        $association->asso_nom = $validated['nom'];
        $association->asso_adresse = $validated['adresse'];
        $association->asso_npa = $validated['npa'];
        $association->asso_localite = $validated['localite'];
        $association->asso_canton = $validated['canton'];
        $association->asso_ide = $validated['ide'] ?? null;
        $association->asso_zewo = $validated['zewo'] ?? null;
        $association->asso_description = $validated['description'] ?? null;
        $association->asso_site_web = $validated['site_web'] ?? null;
        $association->asso_latitude = $validated['latitude'] ?? null;
        $association->asso_longitude = $validated['longitude'] ?? null;
        $association->asso_valide = 0; // Par défaut non validée
        $association->asso_util_id = $user->util_id;
        $association->save();
        
        // S'assurer que l'utilisateur a le rôle association
        $user->assignRole('association');
        
        return response()->json(['message' => 'Association créée avec succès', 'association' => $association], 201);
    }
    
    /**
     * Mettre à jour une association existante
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $association = Association::findOrFail($id);
        
        // Vérification des permissions
        if ($association->asso_util_id != $user->util_id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }
        
        $validated = $request->validate([
            'nom' => 'sometimes|string|max:100',
            'adresse' => 'sometimes|string|max:200',
            'npa' => 'sometimes|string|max:10',
            'localite' => 'sometimes|string|max:100',
            'canton' => 'sometimes|string|max:50',
            'ide' => 'nullable|string|max:15|unique:sea_association,asso_ide,' . $id . ',asso_id',
            'zewo' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'site_web' => 'nullable|url|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);
        
        // Mise à jour des champs
        if (isset($validated['nom'])) $association->asso_nom = $validated['nom'];
        if (isset($validated['adresse'])) $association->asso_adresse = $validated['adresse'];
        if (isset($validated['npa'])) $association->asso_npa = $validated['npa'];
        if (isset($validated['localite'])) $association->asso_localite = $validated['localite'];
        if (isset($validated['canton'])) $association->asso_canton = $validated['canton'];
        if (isset($validated['ide'])) $association->asso_ide = $validated['ide'];
        if (isset($validated['zewo'])) $association->asso_zewo = $validated['zewo'];
        if (isset($validated['description'])) $association->asso_description = $validated['description'];
        if (isset($validated['site_web'])) $association->asso_site_web = $validated['site_web'];
        if (isset($validated['latitude'])) $association->asso_latitude = $validated['latitude'];
        if (isset($validated['longitude'])) $association->asso_longitude = $validated['longitude'];
        
        // Si admin, possibilité de valider directement
        if ($user->hasRole('admin') && $request->has('valide')) {
            $association->asso_valide = (bool)$request->valide;
        }
        
        $association->save();
        
        return response()->json(['message' => 'Association mise à jour avec succès', 'association' => $association]);
    }
    
    /**
     * Rechercher des associations
     */
    public function search(Request $request): JsonResponse
    {
        $query = Association::query();
        
        // Toujours filtrer sur les associations validées pour la recherche publique
        $query->where('asso_valide', 1);
        
        // Recherche par nom
        if ($request->has('q')) {
            $searchTerm = $request->q;
            $query->where(function($q) use ($searchTerm) {
                $q->where('asso_nom', 'like', "%{$searchTerm}%")
                  ->orWhere('asso_description', 'like', "%{$searchTerm}%");
            });
        }
        
        // Filtres par localisation
        if ($request->has('npa')) {
            $query->where('asso_npa', $request->npa);
        }
        
        if ($request->has('localite')) {
            $query->where('asso_localite', 'like', "%{$request->localite}%");
        }
        
        if ($request->has('canton')) {
            $query->where('asso_canton', $request->canton);
        }
        
        // Distance si lat/long fournies
        if ($request->has('lat') && $request->has('long') && $request->has('distance')) {
            $lat = $request->lat;
            $long = $request->long;
            $distance = $request->distance; // en km
            
            // Calcul approximatif de la distance
            $query->selectRaw(
                "*, (6371 * acos(cos(radians(?)) * cos(radians(asso_latitude)) * cos(radians(asso_longitude) - radians(?)) + sin(radians(?)) * sin(radians(asso_latitude)))) AS distance", 
                [$lat, $long, $lat]
            )
            ->having('distance', '<=', $distance)
            ->orderBy('distance');
        }
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $associations = $query->paginate($perPage);
        
        return response()->json($associations);
    }
}