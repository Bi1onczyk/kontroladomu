<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gminy z rejestru TERYT (plik TERC), z denormalizowanymi nazwami powiatu i województwa (ADR-012).
 * Źródło danych: GUS, rejestr TERYT. Tabela jest odświeżana komendą `teryt:import`, nie edytowana ręcznie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teryt_municipalities', function (Blueprint $table) {
            $table->id();
            // WOJ(2) + POW(2) + GMI(2) + RODZ(1); zgodny z kluczem gminy w pliku SIMC (WOJ+POW+GMI+RODZ_GMI).
            $table->char('code', 7)->unique();
            $table->string('name', 100);
            $table->char('type_code', 1);
            $table->char('county_code', 4);
            $table->string('county_name', 100);
            // Dodatek do projektu architekta: nazwa powiatu bez polskich znaków, do wpisywania "dabrowka poznanski".
            $table->string('county_name_search', 100);
            $table->char('voivodeship_code', 2);
            $table->string('voivodeship_name', 50);
            $table->string('name_search', 100);
            $table->timestamps();

            $table->index(['voivodeship_code', 'county_code']);
            $table->index('name_search');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teryt_municipalities');
    }
};
