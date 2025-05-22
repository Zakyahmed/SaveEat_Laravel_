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
    
    Route::get('/invendus', [InvenduController::class, 'index']);
    Route::get('/invendus/{id}', [InvenduController::class, 'show']);
    
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
        });
    });
    
    // Routes pour les justificatifs (commun)
    Route::prefix('justificatifs')->group(function () {
        Route::get('/', [JustificatifController::class, 'index']);
        Route::post('/', [JustificatifController::class, 'store']);
        Route::get('/status', [JustificatifController::class, 'getStatus']); // Corrigé
        Route::get('/{id}', [JustificatifController::class, 'show']);
        Route::get('/{id}/download', [JustificatifController::class, 'download']);
        Route::delete('/{id}', [JustificatifController::class, 'destroy']);
    });
    
    // Routes d'administration (admin uniquement)
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        
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
        });
        
        // Gestion des associations
        Route::prefix('associations')->group(function () {
            Route::get('/', [AdminController::class, 'getAllAssociations']);
            Route::put('/{id}/validate', [AdminController::class, 'validateAssociation']);
        });
        
        // Gestion des justificatifs
        Route::prefix('justificatifs')->group(function () {
            Route::get('/', [AdminController::class, 'getAllJustificatifs']);
            Route::put('/{id}/validate', [AdminController::class, 'validateJustificatif']);
        });
        
        // Statistiques et rapports
        Route::prefix('stats')->group(function () {
            Route::get('/users', [AdminController::class, 'getUserStats']);
            Route::get('/invendus', [AdminController::class, 'getInvendusStats']);
            Route::get('/reservations', [AdminController::class, 'getReservationsStats']);
        });
    });
    
    // Routes de recherche
    Route::prefix('search')->group(function () {
        Route::get('/restaurants', [RestaurantController::class, 'search']);
        Route::get('/invendus', [InvenduController::class, 'search']);
        Route::get('/associations', [AssociationController::class, 'search']);
    });

    Route::get('/test-db', function() {
        try {
            $result = [];
            
            // Test des tables
            $result['tables'] = [
                'sea_restaurant' => \Schema::hasTable('sea_restaurant'),
                'sea_association' => \Schema::hasTable('sea_association'),
                'sea_invendu' => \Schema::hasTable('sea_invendu'),
                'sea_reservation' => \Schema::hasTable('sea_reservation'),
            ];
            
            // Test des colonnes spécifiques
            if (\Schema::hasTable('sea_restaurant')) {
                $result['restaurant_columns'] = [
                    'rest_valide' => \Schema::hasColumn('sea_restaurant', 'rest_valide'),
                    'created_at' => \Schema::hasColumn('sea_restaurant', 'created_at'),
                    'updated_at' => \Schema::hasColumn('sea_restaurant', 'updated_at'),
                ];
            }
            
            if (\Schema::hasTable('sea_association')) {
                $result['association_columns'] = [
                    'asso_valide' => \Schema::hasColumn('sea_association', 'asso_valide'),
                    'created_at' => \Schema::hasColumn('sea_association', 'created_at'),
                    'updated_at' => \Schema::hasColumn('sea_association', 'updated_at'),
                ];
            }
            
            if (\Schema::hasTable('sea_invendu')) {
                $result['invendu_columns'] = [
                    'inv_statut' => \Schema::hasColumn('sea_invendu', 'inv_statut'),
                    'inv_quantite' => \Schema::hasColumn('sea_invendu', 'inv_quantite'),
                    'created_at' => \Schema::hasColumn('sea_invendu', 'created_at'),
                    'updated_at' => \Schema::hasColumn('sea_invendu', 'updated_at'),
                ];
            }
            
            if (\Schema::hasTable('sea_reservation')) {
                $result['reservation_columns'] = [
                    'res_statut' => \Schema::hasColumn('sea_reservation', 'res_statut'),
                    'created_at' => \Schema::hasColumn('sea_reservation', 'created_at'),
                    'updated_at' => \Schema::hasColumn('sea_reservation', 'updated_at'),
                ];
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    });
});