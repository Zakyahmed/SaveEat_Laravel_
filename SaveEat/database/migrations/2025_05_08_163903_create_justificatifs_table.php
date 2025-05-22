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
        if (!Schema::hasTable('sea_justificatif')) {
            Schema::create('sea_justificatif', function (Blueprint $table) {
                $table->increments('just_id');
                $table->string('just_nom_fichier', 255);
                $table->string('just_chemin_fichier', 255);
                $table->timestamp('just_date_envoi')->nullable();
                $table->string('just_statut', 20);
                $table->text('just_commentaire')->nullable();
                $table->integer('just_util_id')->unsigned()->nullable();
                $table->timestamps();

                $table->foreign('just_util_id')
                    ->references('util_id')
                    ->on('sea_utilisateur')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            });
        } else {
            // Ajouter seulement les champs de timestamp Laravel si nécessaire
            Schema::table('sea_justificatif', function (Blueprint $table) {
                if (!Schema::hasColumn('sea_justificatif', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('sea_justificatif', 'updated_at')) {
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
        // Ne jamais supprimer la table si elle existe
    }
};