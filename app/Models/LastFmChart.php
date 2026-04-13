<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[WithoutTimestamps]
#[Fillable(['from', 'to', 'user', 'synced'])]
class LastFmChart extends Model
{

    protected function casts(): array
    {
        return [
            'from' => 'datetime:Y-m-d H:i:s',
            'to' => 'datetime:Y-m-d H:i:s',
        ];
    }

}
