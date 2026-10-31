<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache wyników geokodowania adresów (ADR-013).
 *
 * RODO / minimalizacja: NIE zapisujemy tekstu adresu ani nazwy z Nominatim, tylko skrót HMAC-SHA256
 * (klucz: APP_KEY) znormalizowanego zapytania i wynik liczbowy. To pseudonimizacja, nie anonimizacja: bez APP_KEY nie da się
 * sprawdzić, czy w cache jest dany adres, ale kto zna APP_KEY, może skrót odtworzyć dla znanego adresu.
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
