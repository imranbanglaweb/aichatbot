<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Str;

class Specialization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'common_symptoms',
        'is_active',
    ];

    protected $casts = [
        'common_symptoms' => 'array',
        'is_active' => 'boolean',
    ];

    // Boot method to auto-generate slug
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($specialization) {
            if (empty($specialization->slug)) {
                $specialization->slug = Str::slug($specialization->name);
            }
        });
    }

    // Relationships
    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Helper methods
    public function getDoctorsCountAttribute(): int
    {
        return $this->doctors()->available()->count();
    }
}
