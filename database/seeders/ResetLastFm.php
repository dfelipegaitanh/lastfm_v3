<?php

namespace Database\Seeders;

use App\Models\LastFmChart;
use Illuminate\Database\Seeder;

class ResetLastFm extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        LastFmChart::truncate();
    }
}
