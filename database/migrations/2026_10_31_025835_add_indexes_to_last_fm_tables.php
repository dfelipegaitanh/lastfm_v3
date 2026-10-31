<?php

declare(strict_types=1);

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
        Schema::table('last_fm_track_playcounts', fn (Blueprint $t) => $t->dropUnique('lfm_playcounts_chart_track_unique'));
        Schema::table('last_fm_tag_last_fm_track', fn (Blueprint $t) => $t->dropUnique('lfm_tag_track_unique'));
        Schema::table('last_fm_charts', fn (Blueprint $t) => $t->dropUnique('lfm_charts_user_from_limit_unique'));
        Schema::table('last_fm_artists', fn (Blueprint $t) => $t->dropUnique('lfm_artists_name_unique'));
        Schema::table('last_fm_tags', fn (Blueprint $t) => $t->dropUnique('lfm_tags_name_unique'));
        Schema::table('last_fm_albums', fn (Blueprint $t) => $t->dropIndex('lfm_albums_artist_name_index'));
        Schema::table('last_fm_tracks', fn (Blueprint $t) => $t->dropIndex('lfm_tracks_artist_album_name_index'));
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('last_fm_track_playcounts', fn (Blueprint $t) => $t->unique(['last_fm_chart_id', 'last_fm_track_id'], 'lfm_playcounts_chart_track_unique'));
        Schema::table('last_fm_tag_last_fm_track', fn (Blueprint $t) => $t->unique(['last_fm_tag_id', 'last_fm_track_id'], 'lfm_tag_track_unique'));
        Schema::table('last_fm_charts', fn (Blueprint $t) => $t->unique(['user', 'from', 'play_count_limit'], 'lfm_charts_user_from_limit_unique'));
        Schema::table('last_fm_artists', fn (Blueprint $t) => $t->unique('name', 'lfm_artists_name_unique'));
        Schema::table('last_fm_tags', fn (Blueprint $t) => $t->unique('name', 'lfm_tags_name_unique'));
        Schema::table('last_fm_albums', fn (Blueprint $t) => $t->index(['last_fm_artist_id', 'name'], 'lfm_albums_artist_name_index'));
        Schema::table('last_fm_tracks', fn (Blueprint $t) => $t->index(['last_fm_artist_id', 'last_fm_album_id', 'name'], 'lfm_tracks_artist_album_name_index'));
    }
};
