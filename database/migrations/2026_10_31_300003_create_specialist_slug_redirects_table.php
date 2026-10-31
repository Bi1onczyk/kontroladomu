<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stare adresy profili (slug) zarezerwowane dla swojego właściciela i przekierowujące 301 na bieżący adres (ADR-018).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialist_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('specialist_profile_id')->constrained('specialist_profiles')->cascadeOnDelete();
            $table->string('old_slug', 60)->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specialist_slug_redirects');
    }
};
