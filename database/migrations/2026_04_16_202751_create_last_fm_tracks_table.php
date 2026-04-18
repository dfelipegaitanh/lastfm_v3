<?php

declare(strict_types=1);

use App\Models\LastFmArtist;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('last_fm_tracks');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('last_fm_tracks', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(LastFmArtist::class);
            $table->string('name');
            $table->string('mbid')->nullable();
            $table->timestamps();
        });
    }
};
