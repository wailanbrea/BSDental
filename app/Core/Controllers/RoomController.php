<?php

namespace App\Core\Controllers;

use App\Core\Models\Branch;
use App\Core\Models\Room;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Store a newly created room / dental chair in branch.
     */
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
        ]);

        $room = $branch->rooms()->create($validated);

        $this->auditLogger->logTenant('room.created', 'Room', $room->id, [
            'name' => $room->name,
            'branch_id' => $branch->id,
        ]);

        return redirect()->back()->with('success', 'Consultorio / Sillón creado exitosamente.');
    }

    /**
     * Remove the specified room.
     */
    public function destroy(Room $room): RedirectResponse
    {
        $room->delete();

        $this->auditLogger->logTenant('room.deleted', 'Room', $room->id, [
            'name' => $room->name,
        ]);

        return redirect()->back()->with('success', 'Consultorio / Sillón eliminado exitosamente.');
    }
}
