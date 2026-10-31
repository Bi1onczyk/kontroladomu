<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil fachowca (zadanie 004, ADR-017...ADR-021): jeden profil na konto z rolą `specialist`.
 *
 * Wyliczenia (account_type, location_status, verification_status...) to zwykły tekst z wartością enuma (bez CHECK).
 * Punkt startowy = środek miejscowości z TERYT (start_lat/start_lng kopiowane z `teryt_localities`), nie adres.
 * Brak kolumn zdjęcia/logo (decyzja właściciela z 31.10.2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialist_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('slug', 60)->unique();
            $table->timestamp('slug_changed_at')->nullable();
            $table->string('display_name', 120);
            $table->string('account_type', 20)->default('sole_proprietor');
            $table->text('description')->nullable();

            // Telefon prywatny (kontakt serwisu z fachowcem). Publiczny dopiero po zgodzie (art. 7 ust. 1 RODO).
            $table->string('phone', 20)->nullable();
            $table->boolean('phone_is_public')->default(false);
            $table->timestamp('phone_public_consent_at')->nullable();

            // Obszar działania.
            $table->char('locality_simc', 7)->nullable();
            $table->unsignedSmallInteger('service_radius_km')->default(25);
            $table->unsignedInteger('travel_rate_per_km_grosze')->default(0);
            $table->unsignedSmallInteger('travel_free_km')->default(0);

            // Punkt startowy: kopia środka miejscowości (pod filtr bounding box w wyszukiwarce).
            $table->decimal('start_lat', 10, 7)->nullable();
            $table->decimal('start_lng', 10, 7)->nullable();
            $table->string('start_precision', 20)->nullable();
            $table->string('start_source', 20)->nullable();
            $table->string('location_status', 20)->default('pending');
            $table->timestamp('location_resolved_at')->nullable();

            // Weryfikacja: tylko pole stanu; logikę i historię dodaje zadanie 005.
            $table->string('verification_status', 20)->default('pending');
            $table->timestamp('verified_at')->nullable();

            $table->boolean('is_published')->default(false);
            $table->timestamp('first_published_at')->nullable();
            $table->timestamps();

            // Miejscowości się nie kasuje (ADR-012), więc restrict.
            $table->foreign('locality_simc')->references('simc')->on('teryt_localities')->restrictOnDelete();

            $table->index('verification_status');
            // Wyszukiwarka (006): najpierw is_published, potem zakres start_lat, potem start_lng, na końcu haversine.
            $table->index(['is_published', 'start_lat', 'start_lng'], 'specialist_profiles_search_idx');
            // Job geokodowania i komenda ponowień.
            $table->index(['locality_simc', 'location_status'], 'specialist_profiles_locality_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialist_profiles');
    }
};
