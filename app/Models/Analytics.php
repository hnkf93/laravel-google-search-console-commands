<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class Analytics extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'data';

    protected $fillable = [
        'details'
    ];
}
