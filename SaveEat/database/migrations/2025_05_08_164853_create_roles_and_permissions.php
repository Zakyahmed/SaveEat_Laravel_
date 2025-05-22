<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Création des rôles principaux
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $restaurantRole = Role::firstOrCreate(['name' => 'restaurant', 'guard_name' => 'web']);
        $associationRole = Role::firstOrCreate(['name' => 'association', 'guard_name' => 'web']);

        // Création des permissions
        // Permissions communes
        $viewProfile = Permission::firstOrCreate(['name' => 'view profile', 'guard_name' => 'web']);
        $editProfile = Permission::firstOrCreate(['name' => 'edit profile', 'guard_name' => 'web']);
        
        // Permissions spécifiques aux rôles
        $manageUsers = Permission::firstOrCreate(['name' => 'manage users', 'guard_name' => 'web']);
        $approveUsers = Permission::firstOrCreate(['name' => 'approve users', 'guard_name' => 'web']);
        $viewReports = Permission::firstOrCreate(['name' => 'view reports', 'guard_name' => 'web']);
        
        $manageInvendus = Permission::firstOrCreate(['name' => 'manage invendus', 'guard_name' => 'web']);
        $viewInvendus = Permission::firstOrCreate(['name' => 'view invendus', 'guard_name' => 'web']);
        $viewRestaurantReservations = Permission::firstOrCreate(['name' => 'view restaurant reservations', 'guard_name' => 'web']);
        
        $makeReservation = Permission::firstOrCreate(['name' => 'make reservation', 'guard_name' => 'web']);
        $manageAssociationReservations = Permission::firstOrCreate(['name' => 'manage association reservations', 'guard_name' => 'web']);

        // Attribution des permissions aux rôles
        $adminRole->givePermissionTo([
            $viewProfile, $editProfile, $manageUsers, $approveUsers, $viewReports,
            $manageInvendus, $viewInvendus, $viewRestaurantReservations,
            $makeReservation, $manageAssociationReservations
        ]);
        
        $restaurantRole->givePermissionTo([
            $viewProfile, $editProfile, $manageInvendus, $viewInvendus, $viewRestaurantReservations
        ]);
        
        $associationRole->givePermissionTo([
            $viewProfile, $editProfile, $makeReservation, $manageAssociationReservations
        ]);
        
        // Création d'un utilisateur admin
        if (\DB::table('sea_utilisateur')->where('util_email', 'admin@saveeat.com')->doesntExist()) {
            $adminId = \DB::table('sea_utilisateur')->insertGetId([
                'util_email' => 'admin@saveeat.com',
                'util_mdp' => bcrypt('admin123'),
                'util_nom' => 'Admin',
                'util_prenom' => 'SaveEat',
                'util_date_inscription' => now()->format('Y-m-d'),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            \DB::table('model_has_roles')->insert([
                'role_id' => $adminRole->id,
                'model_type' => 'App\\Models\\Utilisateur',
                'model_id' => $adminId
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Suppression des données n'est pas recommandée
    }
};