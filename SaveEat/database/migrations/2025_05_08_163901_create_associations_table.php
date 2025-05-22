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
        if (!Schema::hasTable('sea_association')) {
            Schema::create('sea_association', function (Blueprint $table) {
                $table->increments('asso_id');
                $table->string('asso_nom', 100);
                $table->string('asso_adresse', 200);
                $table->string('asso_npa', 10);
                $table->string('asso_localite', 100);
                $table->string('asso_canton', 50);
                $table->float('asso_latitude')->nullable();
                $table->float('asso_longitude')->nullable();
                $table->string('asso_ide', 15)->nullable()->unique();
                $table->string('asso_zewo', 20)->nullable();
                $table->text('asso_description')->nullable();
                $table->string('asso_site_web', 255)->nullable();
                $table->boolean('asso_valide')->default(0);
                $table->integer('asso_util_id')->unsigned()->nullable();
                $table->timestamps();

                $table->foreign('asso_util_id')
                    ->references('util_id')
                    ->on('sea_utilisateur')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            });
        } else {
            // Ajouter seulement les champs de timestamp Laravel si nécessaire
            Schema::table('sea_association', function (Blueprint $table) {
                if (!Schema::hasColumn('sea_association', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('sea_association', 'updated_at')) {
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