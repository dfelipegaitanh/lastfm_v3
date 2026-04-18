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
            $table->dropColumn('play_count_limit');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('last_fm_charts', function (Blueprint $table): void {
            $table->integer('play_count_limit')
                ->after('to');
        });
    }
};
