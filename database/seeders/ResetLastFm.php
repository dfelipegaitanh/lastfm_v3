<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LastFmAlbum;
use App\Models\LastFmArtist;
use App\Models\LastFmChart;
use App\Models\LastFmTag;
use App\Models\LastFmTrack;
use App\Models\LastFmTrackPlaycounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ResetLastFm extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schema::withoutForeignKeyConstraints(function (): void {
            LastFmChart::truncate();
            LastFmArtist::truncate();
            LastFmTrack::truncate();
            LastFmTrackPlaycounts::truncate();
            LastFmAlbum::truncate();
            LastFmTag::truncate();
            DB::table('last_fm_tag_last_fm_track')->truncate();
        });
    }
}
