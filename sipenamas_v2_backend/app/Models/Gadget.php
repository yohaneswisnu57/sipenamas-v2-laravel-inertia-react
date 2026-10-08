<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gadget extends Model
{
    protected $table = 'gadget';

    public $timestamps = false;

    protected $guarded = [];
}
