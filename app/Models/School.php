<?php

namespace App\Models;

use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(SchoolFactory::class)]
class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'reference',
        'description',
        'email',
        'phone_number',
        'address',
        'status',
        'access_code',
        'is_active',
        'cantine_enabled',
        'year_end_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'cantine_enabled' => 'boolean',
        'year_end_date' => 'date',
    ];
}
