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
        Schema::table('last_fm_charts', function (Blueprint $table) {
            $table->dropColumn('threshold');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('last_fm_charts', function (Blueprint $table) {
            $table->integer('threshold')
                ->after('play_count_limit')
                ->default(0);
        });
    }
};
