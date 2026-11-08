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
        Schema::table('last_fm_charts', function (Blueprint $table): void {
            $table->dropUnique('lfm_charts_user_from_limit_viral_limit_unique');
            $table->renameColumn('viral_limit', 'threshold');
            $table->unique(['user', 'from', 'play_count_limit', 'threshold'], 'lfm_charts_user_from_limit_threshold_unique');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('last_fm_charts', function (Blueprint $table): void {
            $table->dropUnique('lfm_charts_user_from_limit_threshold_unique');
            $table->renameColumn('threshold', 'viral_limit');
            $table->unique(['user', 'from', 'play_count_limit', 'viral_limit'], 'lfm_charts_user_from_limit_viral_limit_unique');
        });
    }
};
