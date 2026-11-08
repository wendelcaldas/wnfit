<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicAppointmentReservation extends Model
{
    protected $fillable = ['request_id', 'reference', 'name', 'phone'];
}
