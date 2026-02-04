<?php

namespace PictaStudio\Translatable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Translation extends Model
{
    public $timestamps = false;

    protected $table = 'translations';

    protected $fillable = [
        'locale',
        'attribute',
        'value',
    ];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
