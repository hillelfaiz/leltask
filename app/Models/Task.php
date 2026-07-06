<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'course_id', 'title', 'description', 
        'status', 'priority', 'due_date', 'is_notified',
        'attachment_content', 'attachment_type', 'attachment_name'
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'is_notified' => 'boolean',
    ];

    // Accessor untuk mendapatkan URL file secara langsung
    protected $appends = ['attachment_url'];

    public function getAttachmentUrlAttribute()
    {
        return $this->attachment_content ? route('tasks.attachment', $this->id) : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
}