<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Attachment;
use App\Models\Activity;
use Illuminate\Http\Request;
use App\Events\NewMessage;
use App\Events\ChatCreated;

class MessageController extends Controller
{
    /**
     * Board: Created by current user
     */
    public function created(Request $request)
    {
        $user = $request->user();

        $query = Message::query()
            ->where('creator_id', $user->id)
            ->where('is_announcement', false);

        // Optional filters
        if ($request->has('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('status')) {
            $query->whereHas('status', fn($q) => $q->where('name', $request->status));
        }

        if ($request->filled('creator_id')) {
            $query->where('creator_id', $request->creator_id);
        }

        $messages = $query->with($this->relations())
            ->latest()
            ->get();

        return response()->json($messages);
    }

    /**
     * Board: Assigned to current user
     * (Created by someone else)
     */
    public function assigned(Request $request)
    {
        $user = $request->user();

        $query = Message::query()
            ->whereHas('assignees', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->where('is_announcement', false);

        // Optional filters
        if ($request->has('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('status')) {
            $query->whereHas('status', fn($q) => $q->where('name', $request->status));
        }

        if ($request->filled('creator_id')) {
            $query->where('creator_id', $request->creator_id);
        }

        $messages = $query->with($this->relations())
            ->latest()
            ->get();

        return response()->json($messages);
    }

    /**
     * Board: Announcements (visible to everyone)
     */
    public function announcements(Request $request)
    {
        $user = $request->user();

        $query = Message::query()
            ->where('is_announcement', true);

        // Optional filters
        if ($request->has('is_archived')) {
            $query->where('is_archived', $request->boolean('is_archived'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('status')) {
            $query->whereHas('status', fn($q) => $q->where('name', $request->status));
        }

        if ($request->filled('creator_id')) {
            $query->where('creator_id', $request->creator_id);
        }

        $messages = $query->with($this->relations())
            ->latest()
            ->get();

        return response()->json($messages);
    }

    /**
     * Shared eager-load relations
     */
    protected function relations(): array
    {
        return [
            'creator:id,name,department_id',
            'creator.department:id,name,color',
            'assignees:id,name',
            'status:id,name,color',
            'chatMessages:id,message_id,user_id,content,created_at',
            'chatMessages.user:id,name',
            'activities:id,message_id,user_id,action,assignee_id,created_at',
            'activities.user:id,name',
            'activities.assignee:id,name',
            'attachments:id,message_id,chat_message_id,path,original_name,mime_type,size',
        ];
    }

    public function show(Message $message)
    {
        // Load all relations defined in the relations() helper
        $message->load($this->relations());

        return response()->json($message);
    }
    /**
     * Store a new message
     */
    public function store(Request $request)
    {

        $validated = $request->validate( [
            'title'         => 'required|string|max:255',
            'description'   => 'required|string',
            'priority'      => 'required|string|in:Niedrig,Mittel,Hoch',
            'status_id'     => 'required|exists:message_statuses,id',
            'assignees'       => 'array',
            'assignees.*'     => 'exists:users,id',
            'is_announcement' => 'sometimes|boolean',
        ]);

        $user = $request->user();

        $message = Message::create([
            'title'           => $validated['title'],
            'description'     => $validated['description'],
            'priority'        => $validated['priority'],
            'status_id'       => $validated['status_id'],
            'creator_id'      => $user->id,
            'department_id'   => $user->department_id,
            'is_announcement' => $validated['is_announcement'] ?? false,
        ]);
    
        // Attach assignees (if any)
        if (!empty($validated['assignees'])) {
            $message->assignees()->attach(
                collect($validated['assignees'])->mapWithKeys(fn ($id) => [
                    $id => ['assigned_by' => $user->id]
                ])->toArray()
            );
        }

        broadcast(new NewMessage($message));
    
        return response()->json([
            'message' => 'Nachricht erfolgreich erstellt',
            'data' => $message->load('assignees:id,name'),
        ], 201);
    }

    /**
     * Update an existing message
     */
    public function updateMessage(Request $request, Message $message)
    {
        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'required|string',
            'priority'        => 'required|string|in:Niedrig,Mittel,Hoch',
            'status_id'       => 'required|exists:message_statuses,id',
            'assignees'       => 'array',
            'assignees.*'     => 'exists:users,id',
            'is_announcement' => 'sometimes|boolean',
        ]);

        $user = $request->user();

        /**
         * (Optional but recommended)
         * Authorization check
         */
        $isCreator = $message->creator_id === $user->id;

        $isAssignee = $message->assignees()
            ->where('users.id', $user->id)
            ->exists();

        if (! $isCreator && ! $isAssignee) {
            abort(403, 'Nicht berechtigt, diese Nachricht zu bearbeiten');
        }

        /**
         * Update message fields
         */
        $message->update([
            'title'           => $validated['title'],
            'description'     => $validated['description'],
            'priority'        => $validated['priority'],
            'status_id'       => $validated['status_id'],
            'is_announcement' => $validated['is_announcement'] ?? false,
        ]);

        /**
         * Sync assignees
         * - removes unselected
         * - adds new
         * - updates pivot metadata
         */
        if (array_key_exists('assignees', $validated)) {
            $syncData = collect($validated['assignees'])->mapWithKeys(fn ($id) => [
                $id => ['assigned_by' => $user->id],
            ])->toArray();

            $message->assignees()->sync($syncData);
        }

        /**
         * Optional: broadcast update
         */
        // broadcast(new MessageUpdated($message));

        return response()->json([
            'message' => 'Nachricht erfolgreich aktualisiert',
            'data'    => $message->load($this->relations()),
        ]);
    }

    public function messageStatuses()
    {
        $statuses = \App\Models\MessageStatus::all();

        return response()->json($statuses);
    }

    public function storeAttachment(Request $request)
    {
        $request->validate([
            'message_id'      => 'required|exists:messages,id',
            'chat_message_id' => 'nullable|exists:chat_messages,id',
            'files'         => 'required|array',
            'files.*'       => 'required|file|max:10240',
        ]);

        $attachments = [];
        foreach ($request->file('files') as $file) {
            $path = $file->store('attachments', 'public');

            $attachments[] = Attachment::create([
                'message_id'      => $request->message_id,
                'chat_message_id' => $request->chat_message_id ?? null,
                'path'            => $path,
                'original_name'   => $file->getClientOriginalName(),
                'mime_type'       => $file->getClientMimeType(),
                'size'            => $file->getSize(),
            ]);
        }

        return response()->json([
            'message' => 'Anhang erfolgreich hochgeladen',
            'data'    => $attachments,
        ], 201);
    }

    public function storeActivity(Request $request)
    {
        $request->validate([
            'message_id'  => 'required|exists:messages,id',
            'action'      => 'required|string|max:255',
            'assignee_id' => 'nullable|exists:users,id',
        ]);

        $activity = Activity::create([
            'message_id'  => $request->message_id,
            'user_id'     => $request->user()->id, // Use authenticated user
            'action'      => $request->action,
            'assignee_id' => $request->assignee_id,
        ]);

        return response()->json([
            'message' => 'Aktivität erfolgreich erstellt',
            'data'    => $activity,
        ], 201);
    }

    public function assign(Request $request, Message $message)
    {
        $request->validate([
            'assignees'   => 'required|array',
            'assignees.*' => 'exists:users,id',
        ]);

        // Attach new assignees (without detaching existing)
        foreach ($request->assignees as $userId) {
            $message->assignees()->syncWithoutDetaching([$userId => ['assigned_by' => $request->user()->id]]);
        }

        // Log activity
        foreach ($request->assignees as $assigneeId) {
            $message->activities()->firstOrCreate([
                'user_id' => $request->user()->id,
                'action'  => 'assigned to',
                'assignee_id' => $assigneeId,
            ]);
        }

        $message->update(["is_announcement" => false]);

        return response()->json([
            'message' => 'Zugewiesene wurden erfolgreich aktualisiert',
            'data' => $message->load(['assignees', 'activities'])
        ]);
    }

    public function update(Request $request, Message $message)
    {
        $request->validate([
            'is_archived' => 'required|boolean',
        ]);

        $message->is_archived = $request->is_archived;
        $message->save();

        // Log activity
        $message->activities()->create([
            'user_id' => $request->user()->id,
            'action' => $request->is_archived ? 'archived message' : 'unarchived message',
            'assignee_id' => null,
        ]);

        return response()->json([
            'message' => 'Nachricht erfolgreich aktualisiert',
            'data' => $message
        ]);
    }

    public function addComment(Request $request, Message $message)
    {
        $request->validate([
            'text' => 'required|string|max:2000',
        ]);

        $comment = $message->chatMessages()->create([
            'user_id' => $request->user()->id,
            'content' => $request->text,
        ]);

        $comment = $comment->load('user:id,name', 'message');

        broadcast(new ChatCreated($comment));

        // Optional: log activity
        $message->activities()->create([
            'user_id' => $request->user()->id,
            'action' => 'added a comment',
            'assignee_id' => null,
        ]);

        return response()->json([
            'message' => 'Kommentar erfolgreich hinzugefügt',
            'data' => $comment
        ], 201);
    }
}
