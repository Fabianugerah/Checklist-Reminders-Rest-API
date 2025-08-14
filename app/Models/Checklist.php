<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Checklist extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }

            if (!$model->parent_checklist_id) {
                $model->parent_checklist_id = $model->id; // auto set parent ke dirinya sendiri
            }
        });
    }

    protected $fillable = [
        'id',
        'user_id',
        'parent_checklist_id',
        'title',
        'due_time',
        'repeat_interval',
        'repeat_type',
        'repeat_end_date',
        'repeat_max_count',
        'repeat_current_count',
        'is_completed'
    ];

    protected $casts = [
        'due_time' => 'datetime',
        'repeat_end_date' => 'date',
        'is_completed' => 'boolean',
    ];

    // Relasi ke user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi ke parent checklist (original)
    public function parentChecklist()
    {
        return $this->belongsTo(Checklist::class, 'parent_checklist_id');
    }

    // Relasi ke child checklists (repeat instances)
    public function childChecklists()
    {
        return $this->hasMany(Checklist::class, 'parent_checklist_id');
    }

    // Relasi ke repeat days (untuk weekly repeats)
    public function repeatDays()
    {
        return $this->hasMany(ChecklistRepeatDay::class, 'parent_checklist_id', 'parent_checklist_id');
    }

    // Relasi ke repeat days dari parent (untuk bridging)
    public function parentRepeatDays()
    {
        return $this->hasMany(ChecklistRepeatDay::class, 'parent_checklist_id');
    }

    // Check apakah repeat sudah mencapai limit
    public function hasReachedRepeatLimit()
    {
        if ($this->repeat_type === 'never') {
            return true; // Tidak repeat
        }

        if ($this->repeat_type === 'until_date') {
            return now()->toDateString() > $this->repeat_end_date;
        }

        if ($this->repeat_type === 'after_count') {
            return $this->repeat_current_count >= $this->repeat_max_count;
        }

        return false;
    }

    public function getOriginalChecklist()
    {
        if ($this->parent_checklist_id) {
            $parent = Checklist::find($this->parent_checklist_id);
            if ($parent) {
                return $parent;
            }
        }
        return $this;
    }
}
