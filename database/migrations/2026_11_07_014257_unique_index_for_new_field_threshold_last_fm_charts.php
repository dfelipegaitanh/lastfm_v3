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
        Schema::table('last_fm_charts', fn (Blueprint $t) => $t->unique(['user', 'from', 'play_count_limit'], 'lfm_charts_user_from_limit_unique'));

        Schema::table('last_fm_charts', fn (Blueprint $t) => $t->dropUnique('lfm_charts_user_from_limit_threshold_unique'));
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('last_fm_charts', fn (Blueprint $t) => $t->dropUnique('lfm_charts_user_from_limit_unique'));

        Schema::table('last_fm_charts', fn (Blueprint $t) => $t->unique(['user', 'from', 'play_count_limit', 'threshold'], 'lfm_charts_user_from_limit_threshold_unique'));
    }
};
