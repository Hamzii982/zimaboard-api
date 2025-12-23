<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        return response()->json(
            Department::orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255|unique:departments,name',
            'color' => 'nullable|string|max:20',
        ]);

        $department = Department::create($validated);

        return response()->json(['message' => 'Abteilung erfolgreich erstellt.', 'data' => $department], 201);
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255|unique:departments,name,' . $department->id,
            'color' => 'nullable|string|max:20',
        ]);

        $department->update($validated);

        return response()->json(['message' => 'Abteilung erfolgreich aktualisiert.', 'data' => $department]);
    }

    public function destroy(Department $department)
    {
        // Optional safety check
        if ($department->users()->exists()) {
            return response()->json([
                'message' => 'Department is assigned to users and cannot be deleted.'
            ], 409);
        }

        $department->delete();

        return response()->json(['message' => 'Abteilung erfolgreich gelöscht.']);
    }
}
