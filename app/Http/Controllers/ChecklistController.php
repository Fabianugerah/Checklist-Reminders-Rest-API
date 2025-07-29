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
     *             @OA\Property(property="title", type="string", example="Olahraga"),
     *             @OA\Property(property="due_time", type="string", format="date-time", example="2025-07-30T10:00:00"),
     *             @OA\Property(property="repeat_interval", type="string", enum={"daily", "3_days", "weekly", "monthly", "yearly"}, example="weekly"),
     *             @OA\Property(
     *                 property="repeat_days",
     *                 type="array",
     *                 @OA\Items(type="string", enum={"monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"}),
     *                 example={"sunday", "friday"}
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
            'repeat_days' => 'nullable|array',
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
     *             @OA\Property(property="title", type="string", example="Olahraga Pagi"),
     *             @OA\Property(property="due_time", type="string", format="date-time", example="2025-08-01T07:00:00"),
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

    /**
     * @OA\Post(
     *     path="/api/checklists/{id}/restore",
     *     tags={"Checklist"},
     *     summary="Mengembalikan checklist yang telah dihapus (soft delete)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID checklist",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Checklist berhasil dikembalikan"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Checklist tidak ditemukan"
     *     )
     * )
     */
    public function restore($id)
    {
        $checklist = Checklist::onlyTrashed()->findOrFail($id);
        $user = Auth::user();

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $checklist->restore();

        return response()->json([
            'message' => 'Checklist berhasil dikembalikan',
            'data' => $checklist
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/checklists/{id}/complete",
     *     tags={"Checklist"},
     *     summary="Tandai checklist sebagai selesai (beserta repeat days)",
     *     description="Menandai checklist utama dan semua repeat_days sebagai selesai.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID checklist yang ingin ditandai selesai",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Checklist ditandai selesai"),
     *     @OA\Response(response=403, description="Tidak diizinkan"),
     *     @OA\Response(response=404, description="Checklist tidak ditemukan")
     * )
     */
    public function markAsComplete($id)
    {
        $checklist = Checklist::findOrFail($id);
        $user = auth()->user();

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $checklist->is_completed = true;
        $checklist->save();

        $checklist->repeatDays()->update(['is_completed' => true]);

        return response()->json([
            'message' => 'Checklist marked as completed',
            'data' => $checklist
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/checklists/{id}/uncomplete",
     *     tags={"Checklist"},
     *     summary="Tandai checklist sebagai belum selesai (beserta repeat days)",
     *     description="Menandai checklist utama dan semua repeat_days sebagai belum selesai.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID checklist yang ingin ditandai belum selesai",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Checklist ditandai belum selesai"),
     *     @OA\Response(response=403, description="Tidak diizinkan"),
     *     @OA\Response(response=404, description="Checklist tidak ditemukan")
     * )
     */
    public function unmarkAsComplete($id)
    {
        $checklist = Checklist::findOrFail($id);
        $user = auth()->user();

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $checklist->is_completed = false;
        $checklist->save();

        $checklist->repeatDays()->update(['is_completed' => false]);

        return response()->json(['message' => 'Checklist marked as not completed']);
    }

    /**
     * @OA\Post(
     *     path="/api/checklists/{id}/repeat-days/{day}/complete",
     *     tags={"Checklist"},
     *     summary="Tandai checklist mingguan di hari tertentu sebagai selesai",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID checklist",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="day",
     *         in="path",
     *         required=true,
     *         description="Hari dalam minggu (e.g. monday, tuesday, dst)",
     *         @OA\Schema(type="string", enum={"monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"})
     *     ),
     *     @OA\Response(response=200, description="Hari checklist ditandai selesai"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Data tidak ditemukan")
     * )
     */
    public function markRepeatDayAsComplete($id, $day)
    {
        $user = Auth::user();
        $checklist = Checklist::findOrFail($id);

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $repeatDay = $checklist->repeatDays()->where('day', $day)->first();

        if (!$repeatDay) {
            return response()->json(['message' => 'Repeat day not found'], 404);
        }

        $repeatDay->is_completed = true;
        $repeatDay->save();

        return response()->json(['message' => "Repeat day '$day' marked as completed"]);
    }

    /**
     * @OA\Post(
     *     path="/api/checklists/{id}/repeat-days/{day}/uncomplete",
     *     tags={"Checklist"},
     *     summary="Tandai checklist mingguan di hari tertentu sebagai belum selesai",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID checklist",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="day",
     *         in="path",
     *         required=true,
     *         description="Hari dalam minggu (e.g. monday, tuesday, dst)",
     *         @OA\Schema(type="string", enum={"monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"})
     *     ),
     *     @OA\Response(response=200, description="Hari checklist ditandai belum selesai"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Data tidak ditemukan")
     * )
     */
    public function unmarkRepeatDayAsComplete($id, $day)
    {
        $user = Auth::user();
        $checklist = Checklist::findOrFail($id);

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $repeatDay = $checklist->repeatDays()->where('day', $day)->first();

        if (!$repeatDay) {
            return response()->json(['message' => 'Repeat day not found'], 404);
        }

        $repeatDay->is_completed = false;
        $repeatDay->save();

        return response()->json(['message' => "Repeat day '$day' marked as not completed"]);
    }
}
