<?php

declare(strict_types=1);

use App\Models\LastFmAlbum;
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
        Schema::table('last_fm_tracks', function (Blueprint $table): void {
            $table->dropForeignIdFor(LastFmAlbum::class);
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('last_fm_tracks', function (Blueprint $table): void {
            $table->foreignIdFor(LastFmAlbum::class)->after('last_fm_artist_id');
        });
    }
};
