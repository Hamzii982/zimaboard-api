<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'assigned'      => $this->assignedToUser($user),
            'created'       => $this->createdByUser($user),
            'announcements' => $this->announcements($user),
        ]);
    }

    protected function assignedToUser($user)
    {
        $query = Message::query()
            ->where('assigned_to', $user->id)
            // ->whereHas('assignees', fn ($q) =>
            //     $q->where('users.id', $user->id)
            // )
            // ->where('is_announcement', false)
            ->where('is_archived', false)
            ->with($this->messageRelations())
            ->latest();
        
        return [
            'latest' => $query->get()->take(5),
            'total' => $query->count(),
        ];
    }

    protected function createdByUser($user)
    {
        $query = Message::query()
            ->where('creator_id', $user->id)
            // ->where('is_announcement', false)
            ->where('is_archived', false)
            ->with($this->messageRelations())
            ->latest();

        return [
            'latest' => $query->get()->take(5),
            'total' => $query->count(),
        ];
    }

    protected function announcements($user)
    {

        $query = Message::query()
            ->whereHas('assignees', function ($q) use ($user) {
                $q->where('users.id', $user->id); // current user is a subscriber
            })
            ->where(function ($q) use ($user) {
                $q->where('assigned_to', '<>', $user->id)
                ->orWhereNull('assigned_to'); // include if assigned_to is null
            })
            ->where(function ($q) use ($user) {
                $q->where('creator_id', '<>', $user->id)
                ->orWhereNull('creator_id'); // include if creator_id is null
            })
            ->where('is_archived', false)
            ->with($this->messageRelations())
            ->latest();

        return [
            'latest' => $query->get()->take(5),
            'total' => $query->count(),
        ];
    }

    protected function messageRelations(): array
    {
        return [
            'status:id,name,color',
            'creator:id,name,department_id',
            'creator.department:id,name,color',
            'assignees:id,name',
            'latestChat:id,content,created_at',
        ];
    }
}
