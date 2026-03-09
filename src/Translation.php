<?php

namespace PictaStudio\Translatable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Translation extends Model
{
    public const GENERATED_BY_AI = 'ai';

    public const GENERATED_BY_USER = 'user';

    public $timestamps = false;

    protected $table = 'translations';

    protected $fillable = [
        'locale',
        'attribute',
        'value',
        'generated_by',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
