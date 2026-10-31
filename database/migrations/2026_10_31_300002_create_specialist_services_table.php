<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Macierz usług fachowca: rodzaj przeglądu x typ nieruchomości + cena (ADR-017).
 * Wiersz istnieje = fachowiec wykonuje ten przegląd dla tego typu. `price_grosze` NULL = wycena indywidualna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialist_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialist_profile_id')->constrained('specialist_profiles')->cascadeOnDelete();
            $table->string('inspection_type', 30);
            $table->string('property_type', 30);
            $table->unsignedInteger('price_grosze')->nullable();
            $table->timestamps();

            $table->unique(['specialist_profile_id', 'inspection_type', 'property_type'], 'specialist_services_unique');
            // Wyszukiwarka (006): po rodzaju i typie, potem po profilu.
            $table->index(['inspection_type', 'property_type', 'specialist_profile_id'], 'specialist_services_search_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialist_services');
    }
};
