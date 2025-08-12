<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ChecklistRepeatDay extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'id',
        'checklist_id',
        'parent_checklist_id',
        'day',
    ];

    // Relasi ke checklist instance saat ini
    public function checklist()
    {
        return $this->belongsTo(Checklist::class, 'checklist_id');
    }

    // Relasi ke parent checklist (original)
    public function parentChecklist()
    {
        return $this->belongsTo(Checklist::class, 'parent_checklist_id');
    }
}
