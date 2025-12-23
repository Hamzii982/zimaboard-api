<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageStatus extends Model
{
    protected $table = 'message_statuses';

    protected $fillable = [
        'name',
        'color',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class, 'status_id');
    }
}
