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
        Schema::create('detenus', function (Blueprint $table) {
            $table->id();

            // Identification
            $table->string('numero_ecrou', 50)->unique();
            $table->string('nom');
            $table->string('sexe');
            $table->date('date_naissance');
            $table->string('lieu_naissance');
            $table->string('nationalite')->default('Cameroun');
            $table->string('langue')->nullable();
            $table->string('ethnie')->nullable();
            $table->string('religion')->nullable();
            $table->string('profession');
            $table->string('departement')->nullable();
            $table->string('arrondissement')->nullable();
            $table->string('residence')->nullable();

            // Situation familiale et documents d'identité
            $table->string('statut_matrimonial')->nullable();
            $table->unsignedTinyInteger('nombre_enfants')->nullable();
            $table->string('niveau_etudes')->nullable();
            $table->string('numero_cni')->nullable()->unique();
            $table->string('numero_passeport')->nullable()->unique();
            $table->string('nom_pere');
            $table->string('nom_mere');

            // Contact d'urgence
            $table->string('contact_urgence_nom')->nullable();
            $table->string('contact_urgence_lien_parente')->nullable();
            $table->string('contact_urgence_telephone')->nullable();
            $table->string('contact_urgence_adresse')->nullable();

            // Photos (Cloudinary)
            $table->string('photo_face_url')->nullable();
            $table->string('photo_face_public_id')->nullable();
            $table->string('photo_profil_url')->nullable();
            $table->string('photo_profil_public_id')->nullable();

            // Informations complémentaires
            $table->text('anthropometrie')->nullable();

            // Présence dans l'établissement (l'historique détaillé des sorties
            // - libération, décès, évasion, transfert - vivra dans une table dédiée)
            $table->boolean('est_present')->default(true);

            // Traçabilité
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detenus');
    }
};
