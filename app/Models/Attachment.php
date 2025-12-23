<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $fillable = [
        'message_id',
        'chat_message_id',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        return url(Storage::url($this->path));
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function chatMessage()
    {
        return $this->belongsTo(ChatMessage::class);
    }
}
