<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Utilisateur;
use App\Models\Restaurant;
use App\Models\Association;
use App\Models\Invendu;
use App\Models\Reservation;
use App\Models\Justificatif;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Récupérer tous les utilisateurs
     */
    public function getAllUsers(Request $request): JsonResponse
    {
        $query = Utilisateur::with('roles');
        
        // Filtres optionnels
        if ($request->has('role')) {
            $query->role($request->role);
        }
        
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('util_email', 'like', "%{$searchTerm}%")
                  ->orWhere('util_nom', 'like', "%{$searchTerm}%")
                  ->orWhere('util_prenom', 'like', "%{$searchTerm}%");
            });
        }
        
        // Tri
        $sortBy = $request->input('sort_by', 'util_date_inscription');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);
        
        // IMPORTANT : Si no_pagination est présent, retourner tous les résultats
        if ($request->has('no_pagination') || $request->has('all')) {
            $users = $query->get()->map(function($user) {
                // S'assurer que type est défini et que les rôles sont correctement formatés
                $userData = $user->toArray();
                
                // Ajouter le type si non présent
                $userData['type'] = $user->getUserType();
                
                // Formater les rôles correctement pour le modèle C#
                $userData['roles'] = $user->roles->map(function($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name
                    ];
                })->toArray();
                
                return $userData;
            });
            
            return response()->json($users);
        }
        
        // Pour la pagination
        $perPage = $request->input('per_page', 15);
        $users = $query->paginate($perPage);
        
        // Transformer les résultats pour inclure le type et formater les rôles
        $users->getCollection()->transform(function($user) {
            $userData = $user->toArray();
            
            // Ajouter le type
            $userData['type'] = $user->getUserType();
            
            // Formater les rôles correctement pour le modèle C#
            $userData['roles'] = $user->roles->map(function($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name
                ];
            })->toArray();
            
            return $userData;
        });
        
        return response()->json($users);
    }
    
    /**
     * Récupérer les détails d'un utilisateur
     */
    public function getUserDetails($id): JsonResponse
    {
        $user = Utilisateur::with(['restaurants', 'associations', 'justificatifs', 'roles'])->findOrFail($id);
        
        // Formater l'utilisateur pour le modèle C#
        $userData = $user->toArray();
        $userData['type'] = $user->getUserType();
        $userData['roles'] = $user->roles->map(function($role) {
            return [
                'id' => $role->id,
                'name' => $role->name
            ];
        })->toArray();
        
        $data = [
            'utilisateur' => $userData,
            'roles' => $user->getRoleNames(),
        ];
        
        // Ajouter des statistiques
        if ($user->restaurants()->exists()) {
            $data['stats'] = [
                'invendus_count' => Invendu::whereIn('inv_rest_id', $user->restaurants()->pluck('rest_id'))->count(),
                'reservations_count' => Reservation::whereHas('invendu', function($q) use ($user) {
                    $q->whereIn('inv_rest_id', $user->restaurants()->pluck('rest_id'));
                })->count(),
            ];
        } elseif ($user->associations()->exists()) {
            $data['stats'] = [
                'reservations_count' => Reservation::whereIn('res_asso_id', $user->associations()->pluck('asso_id'))->count(),
            ];
        }
        
        return response()->json($data);
    }
    
    /**
     * Créer un nouvel utilisateur (admin uniquement)
     */
    public function createUser(Request $request): JsonResponse
    {
        \Log::info('CreateUser called with data:', $request->all());
        
        $request->validate([
            'email' => 'required|email|unique:sea_utilisateur,util_email',
            'password' => 'required|min:8',
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'username' => 'nullable|string|max:50',
            'type' => 'required|in:restaurant,association,admin',
        ]);
    
        try {
            $utilisateur = new Utilisateur();
            $utilisateur->util_email = $request->email;
            $utilisateur->util_mdp = Hash::make($request->password);
            $utilisateur->util_nom = $request->nom;
            $utilisateur->util_prenom = $request->prenom;
            $utilisateur->util_username = $request->username;
            $utilisateur->util_date_inscription = now();
            $utilisateur->save();
    
            // Assigner le rôle en fonction du type d'utilisateur
            $role = $request->type;
            $utilisateur->assignRole($role);
            
            // IMPORTANT : Recharger l'utilisateur avec ses relations
            $utilisateur = Utilisateur::with('roles')->find($utilisateur->util_id);
            
            // Créer une réponse qui correspond exactement au modèle C#
            $response = [
                'util_id' => $utilisateur->util_id,
                'util_nom' => $utilisateur->util_nom,
                'util_prenom' => $utilisateur->util_prenom,
                'util_email' => $utilisateur->util_email,
                'util_username' => $utilisateur->util_username,
                'util_telephone' => $utilisateur->util_telephone,
                'util_date_inscription' => $utilisateur->util_date_inscription,
                'util_derniere_connexion' => $utilisateur->util_derniere_connexion,
                'util_image_profil' => $utilisateur->util_image_profil,
                'roles' => $utilisateur->roles->map(function($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name
                    ];
                })->toArray(),
                'type' => $request->type // Retourner le type tel qu'envoyé
            ];
            
            \Log::info('Réponse créée:', $response);
            
            return response()->json($response, 201);
        } catch (\Exception $e) {
            \Log::error('Erreur création utilisateur: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Changer le rôle d'un utilisateur
     */
    public function changeUserRole(Request $request, $id): JsonResponse
    {
        // Log pour debug
        \Log::info('changeUserRole - Requête reçue:', [
            'id' => $id,
            'data' => $request->all(),
            'role' => $request->input('role'),
            'type' => gettype($request->input('role'))
        ]);
        
        $validRoles = Role::pluck('name')->toArray();
        \Log::info('Rôles disponibles dans la BD:', $validRoles);
        
        $request->validate([
            'role' => 'required|string',
        ]);
        
        // Normaliser le rôle (minuscules)
        $role = strtolower(trim($request->role));
        
        // Vérifier manuellement que le rôle existe
        $validRolesLower = array_map('strtolower', $validRoles);
        if (!in_array($role, $validRolesLower)) {
            \Log::error('Rôle invalide:', [
                'role_demande' => $role,
                'roles_valides' => $validRolesLower
            ]);
            
            return response()->json([
                'message' => 'Le rôle sélectionné n\'est pas valide',
                'role_demande' => $role,
                'roles_valides' => $validRolesLower
            ], 422);
        }
        
        $user = Utilisateur::findOrFail($id);
        
        // Révoquer tous les rôles existants
        $user->syncRoles([]);
        
        // Ajouter le nouveau rôle
        $user->assignRole($role);
        
        // Recharger l'utilisateur avec les rôles
        $user->load('roles');
        
        // Formater la réponse pour correspondre au modèle C#
        $userData = $user->toArray();
        $userData['type'] = $user->getUserType();
        $userData['roles'] = $user->roles->map(function($role) {
            return [
                'id' => $role->id,
                'name' => $role->name
            ];
        })->toArray();
        
        \Log::info('Rôle mis à jour avec succès:', [
            'user_id' => $id,
            'nouveau_role' => $role
        ]);
        
        return response()->json($userData);
    }
    
    /**
     * Changer le statut d'un utilisateur (méthode désactivée)
     */
    public function changeUserStatus(Request $request, $id): JsonResponse
    {
        // Cette méthode est désactivée car nous n'utilisons pas de statut actif/inactif
        return response()->json([
            'message' => 'La fonctionnalité de statut utilisateur est désactivée',
            'success' => false
        ], 404);
    }
    
    /**
     * Supprimer un utilisateur
     */
    public function deleteUser($id): JsonResponse
    {
        $user = Utilisateur::findOrFail($id);
        
        // Ne pas permettre de supprimer un admin
        if ($user->hasRole('admin')) {
            return response()->json(['message' => 'Impossible de supprimer un administrateur'], 403);
        }
        
        $user->delete();
        
        return response()->json(['message' => 'Utilisateur supprimé avec succès']);
    }
    
    /**
     * Récupérer tous les restaurants
     */
    public function getAllRestaurants(Request $request): JsonResponse
    {
        $query = Restaurant::with('utilisateur');
        
        // Filtres optionnels
        if ($request->has('valide')) {
            $query->where('rest_valide', $request->valide);
        }
        
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('rest_nom', 'like', "%{$searchTerm}%")
                  ->orWhere('rest_ide', 'like', "%{$searchTerm}%")
                  ->orWhere('rest_localite', 'like', "%{$searchTerm}%");
            });
        }
        
        // Tri
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);
        
        // Si no_pagination est présent, retourner tous les résultats
        if ($request->has('no_pagination') || $request->has('all')) {
            $restaurants = $query->get();
            return response()->json($restaurants);
        }
        
        // Sinon, pagination normale
        $perPage = $request->input('per_page', 15);
        $restaurants = $query->paginate($perPage);
        
        return response()->json($restaurants);
    }
    
    /**
     * Valider ou invalider un restaurant
     */
    public function validateRestaurant(Request $request, $id): JsonResponse
    {
        $request->validate([
            'valide' => 'required|boolean',
            'commentaire' => 'nullable|string',
        ]);
        
        $restaurant = Restaurant::findOrFail($id);
        $restaurant->rest_valide = $request->valide;
        $restaurant->save();
        
        return response()->json([
            'message' => 'Statut du restaurant mis à jour avec succès',
            'restaurant' => $restaurant,
        ]);
    }
    
    /**
     * Récupérer toutes les associations
     */
    public function getAllAssociations(Request $request): JsonResponse
    {
        $query = Association::with('utilisateur');
        
        // Filtres optionnels
        if ($request->has('valide')) {
            $query->where('asso_valide', $request->valide);
        }
        
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('asso_nom', 'like', "%{$searchTerm}%")
                  ->orWhere('asso_ide', 'like', "%{$searchTerm}%")
                  ->orWhere('asso_localite', 'like', "%{$searchTerm}%");
            });
        }
        
        // Tri
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);
        
        // Si no_pagination est présent, retourner tous les résultats
        if ($request->has('no_pagination') || $request->has('all')) {
            $associations = $query->get();
            return response()->json($associations);
        }
        
        // Sinon, pagination normale
        $perPage = $request->input('per_page', 15);
        $associations = $query->paginate($perPage);
        
        return response()->json($associations);
    }
    
    /**
     * Valider ou invalider une association
     */
    public function validateAssociation(Request $request, $id): JsonResponse
    {
        $request->validate([
            'valide' => 'required|boolean',
            'commentaire' => 'nullable|string',
        ]);
        
        $association = Association::findOrFail($id);
        $association->asso_valide = $request->valide;
        $association->save();
        
        return response()->json([
            'message' => 'Statut de l\'association mis à jour avec succès',
            'association' => $association,
        ]);
    }
    
    /**
     * Récupérer tous les justificatifs
     */
    public function getAllJustificatifs(Request $request): JsonResponse
    {
        $query = Justificatif::with('utilisateur');
        
        // Filtres optionnels
        if ($request->has('statut')) {
            $query->where('just_statut', $request->statut);
        }
        
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->whereHas('utilisateur', function($q) use ($searchTerm) {
                $q->where('util_email', 'like', "%{$searchTerm}%")
                  ->orWhere('util_nom', 'like', "%{$searchTerm}%")
                  ->orWhere('util_prenom', 'like', "%{$searchTerm}%");
            });
        }
        
        // Tri
        $sortBy = $request->input('sort_by', 'just_date_envoi');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);
        
        // Si no_pagination est présent, retourner tous les résultats
        if ($request->has('no_pagination') || $request->has('all')) {
            $justificatifs = $query->get();
            return response()->json($justificatifs);
        }
        
        // Sinon, pagination normale
        $perPage = $request->input('per_page', 15);
        $justificatifs = $query->paginate($perPage);
        
        return response()->json($justificatifs);
    }
    
   /**
 * Valider ou refuser un justificatif
 */
public function validateJustificatif(Request $request, $id): JsonResponse
{
    $request->validate([
        'statut' => 'required|in:accepte,refuse',
        'commentaire' => 'nullable|string|max:500',
    ]);
    
    $justificatif = Justificatif::with('utilisateur')->findOrFail($id);
    $oldStatus = $justificatif->just_statut;
    $justificatif->just_statut = $request->statut;
    
    if ($request->has('commentaire')) {
        $justificatif->just_commentaire = $request->commentaire;
    }
    
    $justificatif->save();
    
    $utilisateur = $justificatif->utilisateur;
    
    // Si le justificatif est accepté, nous mettons à jour l'entité associée
    if ($request->statut === 'accepte') {
        // Déterminer le type d'entité à valider en fonction du type de justificatif
        if ($justificatif->just_type === 'restaurant') {
            if ($restaurant = $utilisateur->restaurants()->first()) {
                $restaurant->rest_valide = true;
                $restaurant->save();
                
                \Log::info('Restaurant validé suite à justificatif', [
                    'restaurant_id' => $restaurant->rest_id,
                    'justificatif_id' => $justificatif->just_id,
                    'admin_id' => auth()->user()->util_id
                ]);
            }
        } elseif ($justificatif->just_type === 'association') {
            if ($association = $utilisateur->associations()->first()) {
                $association->asso_valide = true;
                $association->save();
                
                \Log::info('Association validée suite à justificatif', [
                    'association_id' => $association->asso_id,
                    'justificatif_id' => $justificatif->just_id,
                    'admin_id' => auth()->user()->util_id
                ]);
            }
        } elseif ($justificatif->just_type === 'identite') {
            // Cas spécifique pour la validation d'identité
            \Log::info('Identité validée pour l\'utilisateur', [
                'user_id' => $utilisateur->util_id,
                'justificatif_id' => $justificatif->just_id,
                'admin_id' => auth()->user()->util_id
            ]);
        }
    } else if ($request->statut === 'refuse') {
        // Journaliser le refus
        \Log::info('Justificatif refusé', [
            'justificatif_id' => $justificatif->just_id,
            'utilisateur_id' => $utilisateur->util_id,
            'admin_id' => auth()->user()->util_id,
            'type' => $justificatif->just_type,
            'commentaire' => $request->commentaire
        ]);
    }
    
    return response()->json([
        'message' => 'Statut du justificatif mis à jour avec succès',
        'justificatif' => [
            'id' => $justificatif->just_id,
            'statut' => $justificatif->just_statut,
            'statut_precedent' => $oldStatus,
            'commentaire' => $justificatif->just_commentaire,
            'utilisateur' => [
                'id' => $utilisateur->util_id,
                'nom' => $utilisateur->util_nom,
                'prenom' => $utilisateur->util_prenom,
                'email' => $utilisateur->util_email,
                'type' => $utilisateur->getUserType()
            ]
        ]
    ]);
}
    
    /**
     * Statistiques sur les utilisateurs (version adaptée pour l'app C#)
     */
    public function getUserStats(): JsonResponse
    {
        try {
            \Log::info('Début getUserStats');
            
            $totalRestaurants = Restaurant::count();
            \Log::info("Total restaurants: $totalRestaurants");
            
            $totalAssociations = Association::count();
            \Log::info("Total associations: $totalAssociations");
            
            $pendingValidations = Restaurant::where('rest_valide', 0)->count() + 
                                 Association::where('asso_valide', 0)->count();
            \Log::info("Pending validations: $pendingValidations");
            
            $activeUsers = Utilisateur::whereNotNull('util_derniere_connexion')
                                     ->where('util_derniere_connexion', '>', now()->subDays(30))
                                     ->count();
            \Log::info("Active users: $activeUsers");

            $result = [
                'total_restaurants' => $totalRestaurants,
                'total_associations' => $totalAssociations,
                'pending_validations' => $pendingValidations,
                'active_users' => $activeUsers
            ];
            
            \Log::info('Résultat: ' . json_encode($result));
            
            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Erreur getUserStats: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            \Log::error('File: ' . $e->getFile());
            \Log::error('Line: ' . $e->getLine());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Statistiques sur les invendus (version adaptée pour l'app C#)
     */
    public function getInvendusStats(): JsonResponse
    {
        try {
            $totalQuantity = Invendu::where('inv_statut', 'disponible')->sum('inv_quantite');
            $totalCount = Invendu::count();
            $savedThisMonth = Invendu::where('inv_statut', 'distribue')
                                     ->whereMonth('updated_at', now()->month)
                                     ->whereYear('updated_at', now()->year)
                                     ->sum('inv_quantite');

            return response()->json([
                'total_quantity' => (int) $totalQuantity,
                'total_count' => $totalCount,
                'saved_this_month' => (int) $savedThisMonth
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur getInvendusStats: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
/**
 * Mettre à jour un utilisateur (admin uniquement)
 */
public function updateUser(Request $request, $id): JsonResponse
{
    $request->validate([
        'nom' => 'required|string|max:100',
        'prenom' => 'required|string|max:100',
        'email' => 'required|email|unique:sea_utilisateur,util_email,' . $id . ',util_id',
        'username' => 'nullable|string|max:50',
        'type' => 'required|in:restaurant,association,admin',
    ]);

    try {
        $utilisateur = Utilisateur::findOrFail($id);
        
        // Mettre à jour les informations
        $utilisateur->util_nom = $request->nom;
        $utilisateur->util_prenom = $request->prenom;
        $utilisateur->util_email = $request->email;
        $utilisateur->util_username = $request->username;
        $utilisateur->save();
        
        // Changer le rôle si nécessaire
        $newRole = $request->type;
        if ($utilisateur->getRoleNames()->first() !== $newRole) {
            $utilisateur->syncRoles([$newRole]);
        }
        
        // Recharger l'utilisateur avec ses relations
        $utilisateur->load('roles');
        
        // Formater la réponse
        $response = [
            'util_id' => $utilisateur->util_id,
            'util_nom' => $utilisateur->util_nom,
            'util_prenom' => $utilisateur->util_prenom,
            'util_email' => $utilisateur->util_email,
            'util_username' => $utilisateur->util_username,
            'util_telephone' => $utilisateur->util_telephone,
            'util_date_inscription' => $utilisateur->util_date_inscription,
            'util_derniere_connexion' => $utilisateur->util_derniere_connexion,
            'util_image_profil' => $utilisateur->util_image_profil,
            'roles' => $utilisateur->roles->map(function($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name
                ];
            })->toArray(),
            'type' => $request->type
        ];
        
        return response()->json($response);
    } catch (\Exception $e) {
        \Log::error('Erreur mise à jour utilisateur: ' . $e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
    /**
     * Statistiques sur les réservations (version adaptée pour l'app C#)
     */
    public function getReservationsStats(): JsonResponse
    {
        try {
            $totalReservations = Reservation::count();
            $confirmedReservations = Reservation::where('res_statut', 'accepte')->count();
            $pendingReservations = Reservation::where('res_statut', 'en_attente')->count();

            return response()->json([
                'total_reservations' => $totalReservations,
                'confirmed_reservations' => $confirmedReservations,
                'pending_reservations' => $pendingReservations
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur getReservationsStats: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    
}