<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sea_utilisateur')) {
            Schema::create('sea_utilisateur', function (Blueprint $table) {
                $table->increments('util_id');
                $table->string('util_email', 100)->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('util_mdp', 100);
                $table->string('util_nom', 100);
                $table->string('util_prenom', 100);
                $table->string('util_telephone', 20)->nullable();
                $table->date('util_date_inscription');
                $table->string('util_image_profil', 255)->nullable();
                $table->string('util_username', 50)->nullable()->unique();
                $table->timestamp('util_derniere_connexion')->nullable();
                $table->string('remember_token', 100)->nullable();
                $table->timestamps();
            });
        } else {
            // Si la table existe, ajouter les colonnes nécessaires pour Laravel
            Schema::table('sea_utilisateur', function (Blueprint $table) {
                if (!Schema::hasColumn('sea_utilisateur', 'email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable()->after('util_email');
                }
                if (!Schema::hasColumn('sea_utilisateur', 'remember_token')) {
                    $table->string('remember_token', 100)->nullable();
                }
                if (!Schema::hasColumn('sea_utilisateur', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('sea_utilisateur', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Ne jamais supprimer la table si elle existe pour ne pas perdre de données
    }
};