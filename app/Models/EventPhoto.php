<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPhoto extends Model
{
    protected $attributes = ['status' => 'pending'];

    protected $guarded = ['id'];

    protected $hidden = ['path', 'thumbnail_path', 'disk'];
}
