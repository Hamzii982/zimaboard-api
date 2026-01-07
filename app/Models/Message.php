<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'title',
        'description',
        'priority',
        'status_id',
        'creator_id',
        'assigned_to',
        'is_announcement',
        'is_archived',
    ];

    protected $casts = [
        'is_announcement' => 'boolean',
        'is_archived' => 'boolean',
    ];

    /* =======================
     * Relationships
     * ======================= */

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function status()
    {
        return $this->belongsTo(MessageStatus::class, 'status_id');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'message_user')
                    ->withPivot('assigned_by')
                    ->withTimestamps();
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->latest();
    }

    /* =======================
     * Helpers
     * ======================= */

    public function isAssigned()
    {
        return $this->assignees()->exists();
    }

    public function latestChat()
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany();
    }
}
