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
        if (!Schema::hasTable('sea_invendu')) {
            Schema::create('sea_invendu', function (Blueprint $table) {
                $table->increments('inv_id');
                $table->string('inv_titre', 100);
                $table->text('inv_description')->nullable();
                $table->float('inv_quantite');
                $table->string('inv_unite', 20);
                // Utiliser TIMESTAMP avec NULL autorisé
                $table->timestamp('inv_date_disponibilite')->nullable();
                $table->timestamp('inv_date_limite')->nullable();
                $table->string('inv_statut', 20);
                $table->boolean('inv_urgent')->default(0);
                $table->text('inv_allergenes')->nullable();
                $table->string('inv_temperature', 50)->nullable();
                $table->integer('inv_rest_id')->unsigned()->nullable();
                $table->timestamps();

                $table->foreign('inv_rest_id')
                    ->references('rest_id')
                    ->on('sea_restaurant')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            });
        } else {
            // Ajouter seulement les champs de timestamp Laravel si nécessaire
            Schema::table('sea_invendu', function (Blueprint $table) {
                if (!Schema::hasColumn('sea_invendu', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('sea_invendu', 'updated_at')) {
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