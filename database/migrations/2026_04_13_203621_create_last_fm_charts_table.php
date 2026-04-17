<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('last_fm_charts', function (Blueprint $table) {
            $table->id();
            $table->string('from')->default('');
            $table->string('to')->default('');
            $table->string('user')->default('');
            $table->boolean('synced')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('last_fm_charts');
    }
};
