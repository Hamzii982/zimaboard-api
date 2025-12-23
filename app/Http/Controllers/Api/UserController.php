<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        // Eager load department
        $users = User::with('department:id,name,color')
                     ->orderBy('name')
                     ->get();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:6',
            'department_id' => 'nullable|exists:departments,id',
            'is_admin'      => 'sometimes|boolean',
        ]);

        $user = User::create([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'password'      => Hash::make($validated['password']),
            'department_id' => $validated['department_id'] ?? null,
            'is_admin'      => $validated['is_admin'] ?? false,
        ]);

        return response()->json(['message' => 'Benutzer erfolgreich erstellt.', 'data' => $user], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,' . $user->id,
            'password'      => 'nullable|string|min:6',
            'department_id' => 'nullable|exists:departments,id',
            'is_admin'      => 'sometimes|boolean',
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->department_id = $validated['department_id'] ?? null;
        $user->is_admin = $validated['is_admin'] ?? false;

        $user->save();

        return response()->json(['message' => 'Benutzer erfolgreich aktualisiert.', 'data' => $user]);
    }

    public function destroy(User $user)
    {
        // Prevent deleting self
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot delete yourself.'
            ], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Benutzer erfolgreich gelöscht.']);
    }
}
