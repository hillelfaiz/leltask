<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'code', 'name', 'lecturer', 'color_theme', 'semester', 'schedule_day', 'schedule_time_start', 'schedule_time_end', 'room'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
     public function notes()
    {
        return $this->hasMany(Note::class);
    }
}