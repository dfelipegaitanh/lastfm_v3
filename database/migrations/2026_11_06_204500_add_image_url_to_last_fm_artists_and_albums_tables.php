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
        Schema::table('last_fm_albums', function (Blueprint $table): void {
            $table->dropColumn('image_url');
        });

        Schema::table('last_fm_artists', function (Blueprint $table): void {
            $table->dropColumn('image_url');
        });
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('last_fm_artists', function (Blueprint $table): void {
            $table->string('image_url', 500)->nullable()->after('mbid');
        });

        Schema::table('last_fm_albums', function (Blueprint $table): void {
            $table->string('image_url', 500)->nullable()->after('mbid');
        });
    }
};
