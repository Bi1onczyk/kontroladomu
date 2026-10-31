<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Miejscowości z rejestru TERYT (plik SIMC), także wsie i części miejscowości (ADR-012).
 * SIMC nie zawiera współrzędnych: `lat`/`lng` są uzupełniane leniwie (geokodowanie) albo ręcznie.
 * Źródło danych: GUS, rejestr TERYT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teryt_localities', function (Blueprint $table) {
            $table->id();
            $table->char('simc', 7)->unique();
            $table->char('parent_simc', 7)->nullable()->index();
            $table->foreignId('municipality_id')->constrained('teryt_municipalities')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('name_search', 100);
            $table->char('kind_code', 2);
            $table->string('kind', 20);
            $table->boolean('is_part')->default(false);
            $table->string('slug', 160)->unique();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('coordinates_source', 20)->nullable();
            $table->timestamp('geocoded_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Dopasowanie prefiksowe: WHERE name_search LIKE 'abc%' (dopasowanie "%abc%" z indeksu nie korzysta).
            $table->index(['name_search', 'is_part'], 'teryt_localities_search_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teryt_localities');
    }
};
