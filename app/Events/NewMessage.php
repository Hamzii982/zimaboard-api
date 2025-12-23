<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Message;
use App\Models\User;

class NewMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $chatMessage;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $chatMessage)
    {
        $this->chatMessage = $chatMessage;
    }

    /**
     * The event name for frontend listeners
     */
    public function broadcastAs(): string
    {
        return 'message.created';
    }

    /**
     * Determine the channels the event should broadcast on
     */
    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->chatMessage->is_announcement) {
            // Send to all users except creator
            $users = User::where('id', '!=', $this->chatMessage->creator_id)->get();
            foreach ($users as $user) {
                $channels[] = new PrivateChannel('user.' . $user->id);
            }
        } else {
            // Send only to assignees
            foreach ($this->chatMessage->assignees as $assignee) {
                $channels[] = new PrivateChannel('user.' . $assignee->id);
            }
        }

        return $channels;
    }

    /**
     * Optional: broadcast payload
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->chatMessage->id,
            'title' => $this->chatMessage->title,
            'creator' => $this->chatMessage->creator->only('id', 'name'),
            'assignees' => $this->chatMessage->assignees->pluck('id'),
            'is_announcement' => $this->chatMessage->is_announcement,
            'created_at' => $this->chatMessage->created_at->toDateTimeString(),
        ];
    }
}
