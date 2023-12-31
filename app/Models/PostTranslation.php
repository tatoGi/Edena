<?php

namespace App\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostTranslation extends Model
{
    use HasFactory;
    use Sluggable;

    protected $casts = [
        'locale_additional' => 'collection',
        'files' => 'collection',
    ];

    protected $fillable = [
        'post_id',
        'locale',
        'title',
        'slug',
        'keywords',
        'desc',
        'text',
        'locale_additional',
        'files',
        'active',
    ];

    public function getAttribute($key)
    {

        if (isset($this->attributes['locale_additional']) && array_key_exists($key, json_decode($this->attributes['locale_additional'], true))) {

            return json_decode($this->attributes['locale_additional'], true)[$key];
        }

        return parent::getAttribute($key);
    }

    /**
     * Get the post associated with the PostTranslation
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function post()
    {
        return $this->hasOne(Post::class, 'id', 'post_id');
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }
}
