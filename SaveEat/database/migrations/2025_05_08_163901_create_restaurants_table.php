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
        if (!Schema::hasTable('sea_restaurant')) {
            Schema::create('sea_restaurant', function (Blueprint $table) {
                $table->increments('rest_id');
                $table->string('rest_nom', 100);
                $table->string('rest_adresse', 200);
                $table->string('rest_npa', 10);
                $table->string('rest_localite', 100);
                $table->string('rest_canton', 50);
                $table->float('rest_latitude')->nullable();
                $table->float('rest_longitude')->nullable();
                $table->string('rest_ide', 15)->unique();
                $table->text('rest_description')->nullable();
                $table->string('rest_site_web', 255)->nullable();
                $table->boolean('rest_valide')->default(0);
                $table->integer('rest_util_id')->unsigned()->nullable();
                $table->timestamps();

                $table->foreign('rest_util_id')
                    ->references('util_id')
                    ->on('sea_utilisateur')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            });
        } else {
            // Ajouter seulement les champs de timestamp Laravel si nécessaire
            Schema::table('sea_restaurant', function (Blueprint $table) {
                if (!Schema::hasColumn('sea_restaurant', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('sea_restaurant', 'updated_at')) {
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