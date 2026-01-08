<?php

namespace App\Events;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class ChatCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ChatMessage $chat;

    public function __construct(ChatMessage $chat)
    {
        $this->chat = $chat;
    }

    public function broadcastOn()
    {
        $channels = [];
        $appEnv = env('APP_ENV');

        if ($this->chat->message->is_announcement) {
            // Send to all users except the creator
            $users = User::where('id', '!=', $this->chat->user_id)->get();
            foreach ($users as $user) {
                $channels[] = new PrivateChannel($appEnv . '.user.' . $user->id);
            }
        } else {
            // Send to assignees + creator, but exclude current user
            $recipientIds = $this->chat->message->assignees->pluck('id')->toArray();
            $recipientIds[] = $this->chat->message->creator_id; // include creator
            $recipientIds = array_unique($recipientIds);

            // Exclude the currently logged-in user if needed
            if (auth()->check()) {
                $recipientIds = array_diff($recipientIds, [auth()->id()]);
            }

            foreach ($recipientIds as $id) {
                $channels[] = new PrivateChannel($appEnv . '.user.' . $id);
            }
        }

        return $channels;
    }

    public function broadcastAs()
    {
        return 'chat.created';
    }

    // public function broadcastWith()
    // {
    //     return [
    //         'id' => $this->chat->id,
    //         'message_id' => $this->chat->message_id,
    //         'content' => $this->chat->content,
    //         'user' => $this->chat->user->only('id', 'name'),
    //         'created_at' => $this->chat->created_at->toDateTimeString(),
    //     ];
    // }
}