<?php

namespace App\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionTranslation extends Model
{
    use HasFactory;
    use Sluggable;

    protected $casts = [
        'locale_additional' => 'collection',
    ];

    protected $fillable = [
        'section_id',
        'locale',
        'title',
        'keywords',
        'slug',
        'desc',
        'icon',
        'locale_additional',
        'active',
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }
}
