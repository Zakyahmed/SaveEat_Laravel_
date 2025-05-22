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
        if (!Schema::hasTable('sea_reservation')) {
            Schema::create('sea_reservation', function (Blueprint $table) {
                $table->increments('res_id');
                $table->timestamp('res_date')->nullable();
                $table->timestamp('res_date_collecte')->nullable();
                $table->string('res_statut', 20);
                $table->text('res_commentaire')->nullable();
                $table->integer('res_inv_id')->unsigned()->nullable();
                $table->integer('res_asso_id')->unsigned()->nullable();
                $table->timestamps();

                $table->foreign('res_inv_id')
                    ->references('inv_id')
                    ->on('sea_invendu')
                    ->onDelete('set null')
                    ->onUpdate('cascade');

                $table->foreign('res_asso_id')
                    ->references('asso_id')
                    ->on('sea_association')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            });
        } else {
            // Ajouter seulement les champs de timestamp Laravel si nécessaire
            Schema::table('sea_reservation', function (Blueprint $table) {
                if (!Schema::hasColumn('sea_reservation', 'created_at')) {
                    $table->timestamp('created_at')->nullable();
                }
                if (!Schema::hasColumn('sea_reservation', 'updated_at')) {
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