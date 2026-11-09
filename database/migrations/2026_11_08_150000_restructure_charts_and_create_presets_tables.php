<?php

declare(strict_types=1);

use App\Enums\LastFmChartPresetType;
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
        Schema::dropIfExists('last_fm_chart_preset_track');
        Schema::dropIfExists('last_fm_chart_presets');

        Schema::table('last_fm_charts', function (Blueprint $table): void {
            $table->dropUnique('lfm_charts_user_from_unique');
            $table->integer('play_count_limit')->default(0);
            $table->integer('viral_limit')->default(0);
            $table->unique(['user', 'from', 'play_count_limit', 'viral_limit'], 'lfm_charts_user_from_limit_viral_limit_unique');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('last_fm_charts', function (Blueprint $table): void {
            $table->dropUnique('lfm_charts_user_from_limit_viral_limit_unique');
            $table->dropColumn(['play_count_limit', 'viral_limit']);
            $table->unique(['user', 'from'], 'lfm_charts_user_from_unique');
        });

        Schema::create('last_fm_chart_presets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('last_fm_chart_id')->constrained('last_fm_charts')->cascadeOnDelete();
            $table->enum('type', array_column(LastFmChartPresetType::cases(), 'value'));
            $table->integer('target_value');
            $table->timestamps();
            $table->unique(['last_fm_chart_id', 'type', 'target_value'], 'lfm_chart_presets_chart_type_val_unique');
        });

        Schema::create('last_fm_chart_preset_track', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('last_fm_chart_preset_id')->constrained('last_fm_chart_presets')->cascadeOnDelete();
            $table->foreignId('last_fm_track_id')->constrained('last_fm_tracks')->cascadeOnDelete();
            $table->integer('playcount');
            $table->unsignedInteger('rank')->nullable();
            $table->timestamps();
            $table->unique(['last_fm_chart_preset_id', 'last_fm_track_id'], 'lfm_chart_preset_track_unique');
        });
    }
};
