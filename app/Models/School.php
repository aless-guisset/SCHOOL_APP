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

    /**
     * access_code ne doit jamais être sérialisé dans les réponses JSON/Inertia —
     * seul le Directeur y a accès, via le prop partagé `school` construit
     * manuellement dans HandleInertiaRequests (qui n'est pas affecté par $hidden).
     */
    protected $hidden = ['access_code'];

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
        'cantine_meal_price',
        'year_end_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'cantine_enabled' => 'boolean',
        'cantine_meal_price' => 'float',
        'year_end_date' => 'date',
    ];
}
