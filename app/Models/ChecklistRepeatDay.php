<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ChecklistRepeatDay extends Model
{
    protected $fillable = ['checklist_id', 'day'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    public function checklist()
    {
        return $this->belongsTo(Checklist::class);
    }
}
