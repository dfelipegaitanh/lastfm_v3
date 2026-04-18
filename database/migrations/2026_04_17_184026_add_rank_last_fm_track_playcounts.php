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
        Schema::table('last_fm_track_playcounts', function (Blueprint $table): void {
            $table->dropColumn('rank');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('last_fm_track_playcounts', function (Blueprint $table): void {
            $table->unsignedInteger('rank')->nullable()->after('playcount');
        });
    }
};
