<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // Créer les rôles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'restaurant']);
        Role::create(['name' => 'association']);
        Role::create(['name' => 'utilisateur']);
    }
}