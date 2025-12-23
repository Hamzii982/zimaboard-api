<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MessageStatus;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    public function index()
    {
        return response()->json(
            MessageStatus::orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255|unique:message_statuses,name',
            'color' => 'nullable|string|max:20',
        ]);

        $status = MessageStatus::create($validated);

        return response()->json(['message' => 'Status erfolgreich erstellt.', 'data' => $status], 201);
    }

    public function update(Request $request, MessageStatus $status)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255|unique:message_statuses,name,' . $status->id,
            'color' => 'nullable|string|max:20',
        ]);

        $status->update($validated);

        return response()->json(['message' => 'Status erfolgreich aktualisiert.', 'data' => $status]);
    }

    public function destroy(MessageStatus $status)
    {
        if ($status->messages()->exists()) {
            return response()->json([
                'message' => 'Status is used by messages and cannot be deleted.'
            ], 409);
        }

        $status->delete();

        return response()->json(['message' => 'Status erfolgreich gelöscht.']);
    }
}
