<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\AssociationController;
use App\Http\Controllers\Api\InvenduController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\JustificatifController;
use App\Http\Controllers\Api\AdminController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Test route
Route::get('/test', function() {
    return response()->json(['message' => 'API works!']);
});

// Routes publiques d'authentification
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/password/reset', [AuthController::class, 'resetPassword']);
});

// Routes protégées par authentification
Route::middleware('auth:sanctum')->group(function () {
    
    // Routes d'authentification et profil
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/password/change', [AuthController::class, 'changePassword']);
    });
    
    // Routes communes à tous les utilisateurs authentifiés
    Route::get('/restaurants', [RestaurantController::class, 'index']);
    Route::get('/restaurants/{id}', [RestaurantController::class, 'show']);
    
    Route::get('/associations', [AssociationController::class, 'index']);
    Route::get('/associations/{id}', [AssociationController::class, 'show']);
    
    Route::get('/invendus', [InvenduController::class, 'index']);
    Route::get('/invendus/{id}', [InvenduController::class, 'show']);
    
    // Route générique pour les réservations (redirige selon le type d'utilisateur)
    Route::get('/reservations', function(Request $request) {
        $user = $request->user();
        
        // Vérifier le type d'utilisateur et rediriger vers la bonne méthode
        if ($user->type === 'restaurant' || $user->hasRole('restaurant')) {
            return app(ReservationController::class)->forRestaurant($request);
        } elseif ($user->type === 'association' || $user->hasRole('association')) {
            return app(ReservationController::class)->forAssociation($request);
        } elseif ($user->type === 'admin' || $user->hasRole('admin')) {
            // Pour les admins, retourner toutes les réservations
            return response()->json([
                'success' => true,
                'data' => \App\Models\Reservation::with(['invendu', 'association'])->get()
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Type d\'utilisateur non reconnu'
        ], 403);
    });
    
    // Routes de statistiques générales
    Route::prefix('stats')->group(function () {
        Route::get('/general', function() {
            return response()->json([
                'success' => true,
                'data' => [
                    'totalInvendus' => \DB::table('sea_invendu')->count(),
                    'totalReservations' => \DB::table('sea_reservation')->count(),
                    'totalRestaurants' => \DB::table('sea_restaurant')->where('rest_valide', true)->count(),
                    'totalAssociations' => \DB::table('sea_association')->where('asso_valide', true)->count(),
                    'invendusDisponibles' => \DB::table('sea_invendu')->where('inv_statut', 'disponible')->count(),
                    'reservationsEnCours' => \DB::table('sea_reservation')->where('res_statut', 'en_cours')->count()
                ]
            ]);
        });
    });
    
    // Routes spécifiques aux restaurants (et admin)
    Route::middleware('role:restaurant|admin')->group(function () {
        
        // Routes de restaurants
        Route::prefix('restaurants')->group(function () {
            Route::get('/me', [RestaurantController::class, 'myRestaurant']);
            Route::post('/', [RestaurantController::class, 'store']);
            Route::put('/{id}', [RestaurantController::class, 'update']);
            Route::delete('/{id}', [RestaurantController::class, 'destroy']);
        });
        
        // Routes d'invendus
        Route::prefix('invendus')->group(function () {
            Route::get('/my', [InvenduController::class, 'myInvendus']);
            Route::post('/', [InvenduController::class, 'store']);
            Route::put('/{id}', [InvenduController::class, 'update']);
            Route::delete('/{id}', [InvenduController::class, 'destroy']);
        });
        
        // Routes pour gérer les réservations (côté restaurant)
        Route::prefix('reservations')->group(function () {
            Route::get('/restaurant', [ReservationController::class, 'forRestaurant']);
            Route::put('/{id}/status', [ReservationController::class, 'updateStatus']);
        });
        
        // Stats spécifiques au restaurant
        Route::get('/stats/restaurant', function(Request $request) {
            $user = $request->user();
            $restaurantId = null;
            
            // Récupérer l'ID du restaurant selon la structure de la DB
            if ($user->hasRole('restaurant')) {
                // Si l'utilisateur a un restaurant_id direct
                if (isset($user->restaurant_id)) {
                    $restaurantId = $user->restaurant_id;
                } else {
                    // Sinon chercher dans la table sea_restaurant
                    $restaurant = \DB::table('sea_restaurant')
                        ->where('rest_user_id', $user->id)
                        ->first();
                    $restaurantId = $restaurant ? $restaurant->rest_id : null;
                }
            }
            
            if (!$restaurantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Restaurant non trouvé'
                ], 404);
            }
            
            // Calculer les statistiques
            $stats = [
                'repasSauves' => \DB::table('sea_reservation')
                    ->join('sea_invendu', 'sea_reservation.res_invendu_id', '=', 'sea_invendu.inv_id')
                    ->where('sea_invendu.inv_restaurant_id', $restaurantId)
                    ->where('sea_reservation.res_statut', 'complete')
                    ->count(),
                
                'associationsAidees' => \DB::table('sea_reservation')
                    ->join('sea_invendu', 'sea_reservation.res_invendu_id', '=', 'sea_invendu.inv_id')
                    ->where('sea_invendu.inv_restaurant_id', $restaurantId)
                    ->where('sea_reservation.res_statut', 'complete')
                    ->distinct()
                    ->count('sea_reservation.res_association_id'),
                
                'invendusActifs' => \DB::table('sea_invendu')
                    ->where('inv_restaurant_id', $restaurantId)
                    ->where('inv_statut', 'disponible')
                    ->count(),
                
                'reservationsEnCours' => \DB::table('sea_reservation')
                    ->join('sea_invendu', 'sea_reservation.res_invendu_id', '=', 'sea_invendu.inv_id')
                    ->where('sea_invendu.inv_restaurant_id', $restaurantId)
                    ->where('sea_reservation.res_statut', 'en_cours')
                    ->count()
            ];
            
            // Calcul du CO2 économisé (estimation)
            $stats['co2Economise'] = $stats['repasSauves'] * 2.5;
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        });
    });
    
    // Routes spécifiques aux associations (et admin)
    Route::middleware('role:association|admin')->group(function () {
        
        // Routes d'associations
        Route::prefix('associations')->group(function () {
            Route::get('/me', [AssociationController::class, 'myAssociation']);
            Route::post('/', [AssociationController::class, 'store']);
            Route::put('/{id}', [AssociationController::class, 'update']);
        });
        
        // Routes de réservations (côté association)
        Route::prefix('reservations')->group(function () {
            Route::get('/association', [ReservationController::class, 'forAssociation']);
            Route::post('/', [ReservationController::class, 'store']);
            Route::put('/{id}', [ReservationController::class, 'update']);
            Route::delete('/{id}', [ReservationController::class, 'destroy']);
            Route::post('/{id}/cancel', [ReservationController::class, 'cancel']);
        });
        
        // Stats spécifiques à l'association
        Route::get('/stats/association', function(Request $request) {
            $user = $request->user();
            $associationId = null;
            
            // Récupérer l'ID de l'association
            if ($user->hasRole('association')) {
                if (isset($user->association_id)) {
                    $associationId = $user->association_id;
                } else {
                    $association = \DB::table('sea_association')
                        ->where('asso_user_id', $user->id)
                        ->first();
                    $associationId = $association ? $association->asso_id : null;
                }
            }
            
            if (!$associationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Association non trouvée'
                ], 404);
            }
            
            $stats = [
                'repasRecuperes' => \DB::table('sea_reservation')
                    ->where('res_association_id', $associationId)
                    ->where('res_statut', 'complete')
                    ->count(),
                
                'reservationsEnCours' => \DB::table('sea_reservation')
                    ->where('res_association_id', $associationId)
                    ->where('res_statut', 'en_cours')
                    ->count(),
                
                'restaurantsPartenaires' => \DB::table('sea_reservation')
                    ->join('sea_invendu', 'sea_reservation.res_invendu_id', '=', 'sea_invendu.inv_id')
                    ->where('sea_reservation.res_association_id', $associationId)
                    ->distinct()
                    ->count('sea_invendu.inv_restaurant_id')
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        });
    });
    
    // Routes pour les justificatifs (commun à tous les utilisateurs authentifiés)
    Route::prefix('justificatifs')->group(function () {
        Route::get('/', [JustificatifController::class, 'index']);
        Route::post('/', [JustificatifController::class, 'store']);
        Route::get('/status', [JustificatifController::class, 'getStatus']);
        Route::get('/{id}', [JustificatifController::class, 'show']);
        Route::get('/{id}/download', [JustificatifController::class, 'download']);
        Route::delete('/{id}', [JustificatifController::class, 'destroy']);
    });
    
    // Routes d'administration (admin uniquement)
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        
        // Dashboard admin
        Route::get('/dashboard', function() {
            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'totalUsers' => \App\Models\User::count(),
                        'totalRestaurants' => \DB::table('sea_restaurant')->count(),
                        'totalAssociations' => \DB::table('sea_association')->count(),
                        'totalInvendus' => \DB::table('sea_invendu')->count(),
                        'totalReservations' => \DB::table('sea_reservation')->count(),
                        'pendingValidations' => \DB::table('sea_restaurant')->where('rest_valide', false)->count() +
                                              \DB::table('sea_association')->where('asso_valide', false)->count()
                    ]
                ]
            ]);
        });
        
        // Gestion des utilisateurs
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminController::class, 'getAllUsers']);
            Route::post('/', [AdminController::class, 'createUser']); 
            Route::get('/{id}', [AdminController::class, 'getUserDetails']);
            Route::put('/{id}/update', [AdminController::class, 'updateUser']);
            Route::put('/{id}/role', [AdminController::class, 'changeUserRole']);
            Route::put('/{id}/status', [AdminController::class, 'changeUserStatus']);
            Route::delete('/{id}', [AdminController::class, 'deleteUser']);
        });
        
        // Gestion des restaurants
        Route::prefix('restaurants')->group(function () {
            Route::get('/', [AdminController::class, 'getAllRestaurants']);
            Route::put('/{id}/validate', [AdminController::class, 'validateRestaurant']);
            Route::put('/{id}/suspend', [AdminController::class, 'suspendRestaurant']);
            Route::delete('/{id}', [AdminController::class, 'deleteRestaurant']);
        });
        
        // Gestion des associations
        Route::prefix('associations')->group(function () {
            Route::get('/', [AdminController::class, 'getAllAssociations']);
            Route::put('/{id}/validate', [AdminController::class, 'validateAssociation']);
            Route::put('/{id}/suspend', [AdminController::class, 'suspendAssociation']);
            Route::delete('/{id}', [AdminController::class, 'deleteAssociation']);
        });
        
        // Gestion des justificatifs
        Route::prefix('justificatifs')->group(function () {
            Route::get('/', [AdminController::class, 'getAllJustificatifs']);
            Route::put('/{id}/validate', [AdminController::class, 'validateJustificatif']);
            Route::put('/{id}/reject', [AdminController::class, 'rejectJustificatif']);
        });
        
        // Statistiques et rapports détaillés
        Route::prefix('stats')->group(function () {
            Route::get('/users', [AdminController::class, 'getUserStats']);
            Route::get('/invendus', [AdminController::class, 'getInvendusStats']);
            Route::get('/reservations', [AdminController::class, 'getReservationsStats']);
            Route::get('/activity', [AdminController::class, 'getActivityStats']);
            Route::get('/export', [AdminController::class, 'exportStats']);
        });
        
        // Gestion des invendus et réservations
        Route::get('/invendus', [AdminController::class, 'getAllInvendus']);
        Route::get('/reservations', [AdminController::class, 'getAllReservations']);
    });
    
    // Routes de recherche
    Route::prefix('search')->group(function () {
        Route::get('/restaurants', [RestaurantController::class, 'search']);
        Route::get('/invendus', [InvenduController::class, 'search']);
        Route::get('/associations', [AssociationController::class, 'search']);
        Route::get('/global', function(Request $request) {
            $query = $request->get('q');
            
            if (!$query) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paramètre de recherche manquant'
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'restaurants' => \App\Models\Restaurant::where('name', 'like', "%{$query}%")->limit(5)->get(),
                    'associations' => \App\Models\Association::where('name', 'like', "%{$query}%")->limit(5)->get(),
                    'invendus' => \App\Models\Invendu::where('title', 'like', "%{$query}%")->limit(5)->get()
                ]
            ]);
        });
    });
    
    // Route de test de la base de données
    Route::get('/test-db', function() {
        try {
            $result = [];
            
            // Test des tables
            $result['tables'] = [
                'users' => \Schema::hasTable('users'),
                'sea_restaurant' => \Schema::hasTable('sea_restaurant'),
                'sea_association' => \Schema::hasTable('sea_association'),
                'sea_invendu' => \Schema::hasTable('sea_invendu'),
                'sea_reservation' => \Schema::hasTable('sea_reservation'),
                'sea_justificatif' => \Schema::hasTable('sea_justificatif'),
            ];
            
            // Test des colonnes spécifiques
            if (\Schema::hasTable('sea_restaurant')) {
                $result['restaurant_columns'] = [
                    'rest_id' => \Schema::hasColumn('sea_restaurant', 'rest_id'),
                    'rest_user_id' => \Schema::hasColumn('sea_restaurant', 'rest_user_id'),
                    'rest_nom' => \Schema::hasColumn('sea_restaurant', 'rest_nom'),
                    'rest_valide' => \Schema::hasColumn('sea_restaurant', 'rest_valide'),
                    'created_at' => \Schema::hasColumn('sea_restaurant', 'created_at'),
                    'updated_at' => \Schema::hasColumn('sea_restaurant', 'updated_at'),
                ];
            }
            
            if (\Schema::hasTable('sea_association')) {
                $result['association_columns'] = [
                    'asso_id' => \Schema::hasColumn('sea_association', 'asso_id'),
                    'asso_user_id' => \Schema::hasColumn('sea_association', 'asso_user_id'),
                    'asso_nom' => \Schema::hasColumn('sea_association', 'asso_nom'),
                    'asso_valide' => \Schema::hasColumn('sea_association', 'asso_valide'),
                    'created_at' => \Schema::hasColumn('sea_association', 'created_at'),
                    'updated_at' => \Schema::hasColumn('sea_association', 'updated_at'),
                ];
            }
            
            if (\Schema::hasTable('sea_invendu')) {
                $result['invendu_columns'] = [
                    'inv_id' => \Schema::hasColumn('sea_invendu', 'inv_id'),
                    'inv_restaurant_id' => \Schema::hasColumn('sea_invendu', 'inv_restaurant_id'),
                    'inv_titre' => \Schema::hasColumn('sea_invendu', 'inv_titre'),
                    'inv_statut' => \Schema::hasColumn('sea_invendu', 'inv_statut'),
                    'inv_quantite' => \Schema::hasColumn('sea_invendu', 'inv_quantite'),
                    'created_at' => \Schema::hasColumn('sea_invendu', 'created_at'),
                    'updated_at' => \Schema::hasColumn('sea_invendu', 'updated_at'),
                ];
            }
            
            if (\Schema::hasTable('sea_reservation')) {
                $result['reservation_columns'] = [
                    'res_id' => \Schema::hasColumn('sea_reservation', 'res_id'),
                    'res_invendu_id' => \Schema::hasColumn('sea_reservation', 'res_invendu_id'),
                    'res_association_id' => \Schema::hasColumn('sea_reservation', 'res_association_id'),
                    'res_statut' => \Schema::hasColumn('sea_reservation', 'res_statut'),
                    'created_at' => \Schema::hasColumn('sea_reservation', 'created_at'),
                    'updated_at' => \Schema::hasColumn('sea_reservation', 'updated_at'),
                ];
            }
            
            // Compter les enregistrements
            $result['counts'] = [
                'users' => \App\Models\User::count(),
                'restaurants' => \DB::table('sea_restaurant')->count(),
                'associations' => \DB::table('sea_association')->count(),
                'invendus' => \DB::table('sea_invendu')->count(),
                'reservations' => \DB::table('sea_reservation')->count(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    });
});