<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache wyników geokodowania adresów (ADR-013).
 *
 * RODO / minimalizacja: NIE zapisujemy tekstu adresu ani nazwy z Nominatim, tylko skrót SHA-256
 * znormalizowanego zapytania i wynik liczbowy. Z samego skrótu i współrzędnych nie da się odtworzyć adresu.
 * Błędy sieci nie są zapisywane.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geocode_cache', function (Blueprint $table) {
            $table->id();
            $table->char('query_hash', 64)->unique();
            $table->string('status', 12);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('precision', 12)->nullable();
            $table->string('provider', 20)->default('nominatim');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocode_cache');
    }
};
