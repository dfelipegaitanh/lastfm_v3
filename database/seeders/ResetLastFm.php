<?php

namespace Database\Seeders;

use App\Models\LastFmArtist;
use App\Models\LastFmChart;
use App\Models\LastFmTrack;
use App\Models\LastFmTrackPlaycounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ResetLastFm extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            LastFmChart::truncate();
            LastFmArtist::truncate();
            LastFmTrack::truncate();
            LastFmTrackPlaycounts::truncate();
        });
    }
}
