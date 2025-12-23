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
            'announcements' => $this->announcements(),
        ]);
    }

    protected function assignedToUser($user)
    {
        $query = Message::query()
            ->whereHas('assignees', fn ($q) =>
                $q->where('users.id', $user->id)
            )
            ->where('is_announcement', false)
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
            ->where('is_announcement', false)
            ->where('is_archived', false)
            ->with($this->messageRelations())
            ->latest();

        return [
            'latest' => $query->get()->take(5),
            'total' => $query->count(),
        ];
    }

    protected function announcements()
    {
        $query = Message::query()
            ->where('is_announcement', true)
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
