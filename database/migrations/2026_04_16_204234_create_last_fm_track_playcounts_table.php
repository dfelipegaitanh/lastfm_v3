<?php

use App\Models\LastFmChart;
use App\Models\LastFmTrack;
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
        Schema::create('last_fm_track_playcounts', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(LastFmTrack::class);
            $table->foreignIdFor(LastFmChart::class);
            $table->integer('playcount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('last_fm_track_playcounts');
    }
};
