<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Models\ChecklistRepeatDay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChecklistController extends Controller
{
    // GET /checklists
    /**
     * @OA\Get(
     *     path="/api/checklists",
     *     tags={"Checklist"},
     *     summary="Ambil semua checklist user (admin bisa semua)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="List checklist dikembalikan"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index()
    {
        $user = Auth::user();

        $checklists = Checklist::with('repeatDays')
            ->when($user->role !== 'admin', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get();

        return response()->json($checklists);
    }

    // POST /checklists
    /**
     * @OA\Post(
     *     path="/api/checklists",
     *     tags={"Checklist"},
     *     summary="Buat checklist baru",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "due_time", "repeat_interval"},
     *             @OA\Property(property="title", type="string", example="Minum Air"),
     *             @OA\Property(property="due_time", type="string", format="date-time", example="2025-07-28T09:00:00"),
     *             @OA\Property(property="repeat_interval", type="string", enum={"daily", "3_days", "weekly", "monthly", "yearly"}, example="weekly"),
     *             @OA\Property(
     *                 property="repeat_days",
     *                 type="array",
     *                 @OA\Items(type="string", enum={"monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"}),
     *                 example={"monday", "friday"}
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Checklist dibuat"),
     *     @OA\Response(response=422, description="Validasi gagal"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'due_time' => 'required|date',
            'repeat_interval' => 'required|in:daily,3_days,weekly,monthly,yearly',
            'repeat_days' => 'nullable|array', // only for weekly
            'repeat_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        $user = Auth::user();

        $checklist = Checklist::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'due_time' => $request->due_time,
            'repeat_interval' => $request->repeat_interval,
            'is_completed' => false,
        ]);

        if ($request->repeat_interval === 'weekly' && $request->has('repeat_days')) {
            foreach ($request->repeat_days as $day) {
                ChecklistRepeatDay::create([
                    'checklist_id' => $checklist->id,
                    'day' => $day,
                ]);
            }
        }

        return response()->json(['message' => 'Checklist created', 'data' => $checklist]);
    }

    // GET /checklists/{id}
    /**
     * @OA\Get(
     *     path="/api/checklists/{id}",
     *     tags={"Checklist"},
     *     summary="Lihat detail checklist berdasarkan ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID checklist",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Detail checklist dikembalikan"),
     *     @OA\Response(response=403, description="Unauthorized access"),
     *     @OA\Response(response=404, description="Checklist tidak ditemukan")
     * )
     */
    public function show($id)
    {
        $checklist = Checklist::with('repeatDays')->findOrFail($id);
        $user = Auth::user();

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($checklist);
    }

    // PUT /checklists/{id}
    /**
     * @OA\Put(
     *     path="/api/checklists/{id}",
     *     tags={"Checklist"},
     *     summary="Update checklist",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID checklist",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Update Judul Checklist"),
     *             @OA\Property(property="due_time", type="string", format="date-time", example="2025-08-01T08:00:00"),
     *             @OA\Property(property="repeat_interval", type="string", enum={"daily", "3_days", "weekly", "monthly", "yearly"}, example="weekly"),
     *             @OA\Property(
     *                 property="repeat_days",
     *                 type="array",
     *                 @OA\Items(type="string", enum={"monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"}),
     *                 example={"tuesday", "thursday"}
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Checklist diperbarui"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Checklist tidak ditemukan")
     * )
     */
    public function update(Request $request, $id)
    {
        $checklist = Checklist::findOrFail($id);
        $user = Auth::user();

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'title' => 'sometimes|string',
            'due_time' => 'sometimes|date',
            'repeat_interval' => 'sometimes|in:daily,3_days,weekly,monthly,yearly',
            'repeat_days' => 'nullable|array',
            'repeat_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        $checklist->update($request->only('title', 'due_time', 'repeat_interval'));

        if ($request->repeat_interval === 'weekly') {
            // Hapus hari lama, buat ulang
            $checklist->repeatDays()->delete();

            if ($request->has('repeat_days')) {
                foreach ($request->repeat_days as $day) {
                    ChecklistRepeatDay::create([
                        'checklist_id' => $checklist->id,
                        'day' => $day,
                    ]);
                }
            }
        }

        return response()->json(['message' => 'Checklist updated', 'data' => $checklist]);
    }

    // DELETE /checklists/{id}
    /**
     * @OA\Delete(
     *     path="/api/checklists/{id}",
     *     tags={"Checklist"},
     *     summary="Hapus checklist (soft delete)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID checklist",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Checklist dihapus"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Checklist tidak ditemukan")
     * )
     */
    public function destroy($id)
    {
        $checklist = Checklist::findOrFail($id);
        $user = Auth::user();

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $checklist->delete();

        return response()->json(['message' => 'Checklist deleted']);
    }
}
