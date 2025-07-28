<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Checklist extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'title', 'due_time', 'repeat_interval', 'is_completed'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function repeatDays()
    {
        return $this->hasMany(ChecklistRepeatDay::class);
    }
}
