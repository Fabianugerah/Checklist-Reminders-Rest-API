<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Models\ChecklistRepeatDay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ChecklistController extends Controller
{
    // GET ALL CHECKLISTS
    /**
     * @OA\Get(
     * path="/api/checklists",
     * tags={"Checklist"},
     * summary="Ambil semua checklist user (admin bisa semua)",
     * security={{"bearerAuth":{}}},
     * @OA\Response(response=200, description="List checklist dikembalikan"),
     * @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index()
    {
        $user = Auth::user();

        $checklists = Checklist::with('repeatDays')
            ->when($user->role !== 'admin', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('is_completed', false)
            ->get();

        return response()->json($checklists);
    }

    // GET CHECKLISTS COMPLETED
    /**
     * @OA\Get(
     * path="/api/checklists/completed",
     * tags={"Checklist"},
     * summary="Ambil semua checklist yang sudah selesai",
     * security={{"bearerAuth":{}}},
     * @OA\Response(response=200, description="Checklist yang sudah selesai dikembalikan"),
     * @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function completedChecklists()
    {
        $user = Auth::user();

        $checklists = Checklist::with('repeatDays')
            ->when($user->role !== 'admin', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('is_completed', true)
            ->get();

        return response()->json($checklists);
    }

    // GET CHECKLISTS TODAY
    /**
     * @OA\Get(
     * path="/api/checklists/today",
     * tags={"Checklist"},
     * summary="Ambil checklist hari ini (belum complete)",
     * description="Mengembalikan checklist yang sudah lewat tanggal dan belum complete + checklist hari ini.",
     * security={{"bearerAuth":{}}},
     * @OA\Response(response=200, description="List checklist today dikembalikan"),
     * @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function todayChecklists()
    {
        $user = Auth::user();
        $today = Carbon::today();

        $checklists = Checklist::with(['repeatDays'])
            ->when($user->role !== 'admin', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('is_completed', false)
            ->where(function ($query) use ($today) {
                $query
                    ->whereDate('due_time', '<', $today)
                    ->orWhereDate('due_time', $today);
            })
            ->orderBy('due_time', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $checklists,
            'count' => $checklists->count(),
        ]);
    }

    // GET CHECKLISTS THIS WEEK
    /**
     * @OA\Get(
     * path="/api/checklists/weekly",
     * tags={"Checklist"},
     * summary="Ambil checklist minggu ini (belum complete)",
     * description="Mengembalikan checklist yang jatuh tempo dalam minggu ini.",
     * security={{"bearerAuth":{}}},
     * @OA\Response(response=200, description="List checklist minggu ini dikembalikan"),
     * @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function weeklyChecklists()
    {
        $user = Auth::user();
        $startOfWeek = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = Carbon::today()->endOfWeek(Carbon::SUNDAY);

        $checklists = Checklist::with(['repeatDays'])
            ->when($user->role !== 'admin', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('is_completed', false)
            ->whereBetween('due_time', [$startOfWeek, $endOfWeek])
            ->orderBy('due_time', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $checklists,
            'count' => $checklists->count(),
            'week_start' => $startOfWeek->toDateString(),
            'week_end' => $endOfWeek->toDateString()
        ]);
    }

    // POST CHECKLISTS
    /**
     * @OA\Post(
     * path="/api/checklists",
     * tags={"Checklist"},
     * summary="Buat checklist baru dengan repeat settings",
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"title", "due_time"},
     * @OA\Property(property="title", type="string", example="Olahraga Pagi"),
     * @OA\Property(property="due_time", type="string", format="date-time", example="2025-08-18T07:00:00"),
     * @OA\Property(property="repeat_interval", type="string", enum={"daily", "3_days", "weekly", "monthly", "yearly"}, example="weekly"),
     * @OA\Property(property="repeat_type", type="string", enum={"never", "until_date", "after_count"}, example="after_count"),
     * @OA\Property(property="repeat_end_date", type="string", format="date", example="2025-12-31"),
     * @OA\Property(property="repeat_max_count", type="integer", example=10),
     * @OA\Property(
     * property="repeat_days",
     * type="array",
     * @OA\Items(type="string", enum={"monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"}),
     * example={"monday", "wednesday", "friday"}
     * )
     * )
     * ),
     * @OA\Response(response=201, description="Checklist berhasil dibuat"),
     * @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'due_time' => 'required|date',
            'repeat_interval' => 'nullable|in:daily,3_days,weekly,monthly,yearly',
            'repeat_type' => 'nullable|in:never,until_date,after_count',
            'repeat_end_date' => 'nullable|date',
            'repeat_max_count' => 'nullable|integer|min:1',
            'repeat_days' => 'nullable|array',
            'repeat_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        if ($request->repeat_type === 'until_date' && !$request->repeat_end_date) {
            return response()->json(['message' => 'repeat_end_date required when repeat_type is until_date'], 422);
        }
        if ($request->repeat_type === 'after_count' && !$request->repeat_max_count) {
            return response()->json(['message' => 'repeat_max_count required when repeat_type is after_count'], 422);
        }

        $user = auth()->user();

        $parentId = (string) Str::uuid();

        // Buat checklist baru dengan parent_checklist_id sama dengan parentId ini
        $checklist = Checklist::create([
            'user_id' => $user->id,
            'parent_checklist_id' => $parentId,
            'title' => $request->title,
            'due_time' => $request->due_time,
            'repeat_interval' => $request->repeat_interval ?? 'never',
            'repeat_type' => $request->repeat_type ?? 'never',
            'repeat_end_date' => $request->repeat_end_date,
            'repeat_max_count' => $request->repeat_max_count,
            'repeat_current_count' => 0,
            'is_completed' => false,
        ]);

        // Simpan repeat_days (hanya jika weekly dan ada repeat_days)
        if ($request->repeat_interval === 'weekly' && $request->has('repeat_days')) {
            foreach ($request->repeat_days as $day) {
                ChecklistRepeatDay::create([
                    'parent_checklist_id' => $parentId,
                    'day' => $day,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Checklist created successfully',
            'data' => $checklist->load('repeatDays')
        ], 201);
    }

    // GET CHECKLIST BY ID
    /**
     * @OA\Get(
     * path="/api/checklists/{id}",
     * tags={"Checklist"},
     * summary="Lihat detail checklist berdasarkan ID",
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="UUID checklist",
     * @OA\Schema(type="string", format="uuid")
     * ),
     * @OA\Response(response=200, description="Detail checklist dikembalikan"),
     * @OA\Response(response=403, description="Unauthorized access"),
     * @OA\Response(response=404, description="Checklist tidak ditemukan")
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

    // UPDATE CHECKLISTS
    /**
     * @OA\Put(
     * path="/api/checklists/{id}",
     * tags={"Checklist"},
     * summary="Update checklist",
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="UUID checklist",
     * @OA\Schema(type="string", format="uuid")
     * ),
     * @OA\RequestBody(
     * @OA\JsonContent(
     * @OA\Property(property="title", type="string", example="Olahraga Pagi"),
     * @OA\Property(property="due_time", type="string", format="date-time", example="2025-08-18T07:00:00"),
     * @OA\Property(property="repeat_interval", type="string", enum={"daily", "3_days", "weekly", "monthly", "yearly"}, example="weekly"),
     * @OA\Property(property="repeat_type", type="string", enum={"never", "until_date", "after_count"}, example="after_count"),
     * @OA\Property(property="repeat_end_date", type="string", format="date", example="2025-12-31"),
     * @OA\Property(property="repeat_max_count", type="integer", example=10),
     * @OA\Property(
     * property="repeat_days",
     * type="array",
     * @OA\Items(type="string", enum={"monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"}),
     * example={"tuesday", "thursday"}
     * )
     * )
     * ),
     * @OA\Response(response=200, description="Checklist diperbarui"),
     * @OA\Response(response=403, description="Unauthorized"),
     * @OA\Response(response=404, description="Checklist tidak ditemukan")
     * )
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'due_time' => 'required|date',
            'repeat_interval' => 'nullable|in:daily,3_days,weekly,monthly,yearly',
            'repeat_type' => 'nullable|in:never,until_date,after_count',
            'repeat_end_date' => 'nullable|date',
            'repeat_max_count' => 'nullable|integer|min:1',
            'repeat_days' => 'nullable|array',
            'repeat_days.*' => 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ]);

        $checklist = Checklist::findOrFail($id);
        $user = Auth::user();

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Cek apakah checklist sudah complete
        if ($checklist->is_completed) {
            return response()->json(['message' => 'Cannot update completed checklist'], 400);
        }

        // Update checklist utama
        $checklist->update($request->only([
            'title',
            'due_time',
            'repeat_interval',
            'repeat_type',
            'repeat_end_date',
            'repeat_max_count'
        ]));

        // Update repeat_days untuk weekly
        if ($request->repeat_interval === 'weekly' && $request->has('repeat_days')) {
            // Hapus repeat_days lama
            ChecklistRepeatDay::where('parent_checklist_id', $checklist->parent_checklist_id)->delete();

            // Buat repeat_days baru
            foreach ($request->repeat_days as $day) {
                ChecklistRepeatDay::create([
                    'parent_checklist_id' => $checklist->parent_checklist_id,
                    'day' => $day,
                ]);
            }
        }

        // Update children yang masih aktif dan belum complete
        $activeIncompleteChildren = Checklist::where('parent_checklist_id', $checklist->parent_checklist_id)
            ->where('id', '!=', $checklist->id)
            ->where('is_completed', false)
            ->orderBy('repeat_current_count', 'asc')
            ->get();

        foreach ($activeIncompleteChildren as $child) {
            // Cari checklist complete dengan repeat_current_count terbesar yang lebih kecil dari child
            $lastCompletedBefore = Checklist::where('parent_checklist_id', $checklist->parent_checklist_id)
                ->where('is_completed', true)
                ->where('repeat_current_count', '<', $child->repeat_current_count)
                ->orderBy('repeat_current_count', 'desc')
                ->first();

            if ($lastCompletedBefore) {
                $baseDueTime = Carbon::parse($lastCompletedBefore->due_time);
                $stepsToAdd = $child->repeat_current_count - $lastCompletedBefore->repeat_current_count;
            } else {
                $baseDueTime = Carbon::parse($checklist->due_time);
                $stepsToAdd = $child->repeat_current_count - $checklist->repeat_current_count;
            }

            $currentDate = $baseDueTime->copy();

            // untuk weekly
            if ($checklist->repeat_interval === 'weekly') {
                $repeatDays = ChecklistRepeatDay::where('parent_checklist_id', $checklist->parent_checklist_id)
                    ->pluck('day')
                    ->map(fn($day) => strtolower($day))
                    ->toArray();

                if (!empty($repeatDays)) {
                    $dayMap = [
                        'sunday' => 0,
                        'monday' => 1,
                        'tuesday' => 2,
                        'wednesday' => 3,
                        'thursday' => 4,
                        'friday' => 5,
                        'saturday' => 6,
                    ];

                    $repeatIndexes = collect($repeatDays)->map(fn($d) => $dayMap[$d])->sort()->values();

                    for ($i = 0; $i < $stepsToAdd; $i++) {
                        $currentDayIndex = $currentDate->dayOfWeek;
                        $nextDayIndex = null;

                        foreach ($repeatIndexes as $dayIndex) {
                            if ($dayIndex > $currentDayIndex) {
                                $nextDayIndex = $dayIndex;
                                break;
                            }
                        }

                        if ($nextDayIndex !== null) {
                            $daysToAdd = $nextDayIndex - $currentDayIndex;
                        } else {
                            $daysToAdd = (7 - $currentDayIndex) + $repeatIndexes->first();
                        }

                        $currentDate->addDays($daysToAdd);
                    }

                    $newDueTime = $currentDate;
                } else {
                    $newDueTime = $baseDueTime->copy()->addWeeks($stepsToAdd);
                }
            } else {
                $newDueTime = $baseDueTime->copy();
                for ($i = 0; $i < $stepsToAdd; $i++) {
                    switch ($checklist->repeat_interval) {
                        case 'daily':
                            $newDueTime->addDay();
                            break;
                        case '3_days':
                            $newDueTime->addDays(3);
                            break;
                        case 'monthly':
                            $newDueTime->addMonth();
                            break;
                        case 'yearly':
                            $newDueTime->addYear();
                            break;
                    }
                }
            }

            $child->update([
                'title' => $checklist->title,
                'due_time' => $newDueTime,
                'repeat_interval' => $checklist->repeat_interval,
                'repeat_type' => $checklist->repeat_type,
                'repeat_end_date' => $checklist->repeat_end_date,
                'repeat_max_count' => $checklist->repeat_max_count,
            ]);
        }

        // Update children yang di-trash (soft deleted)
        $trashedChildren = Checklist::onlyTrashed()
            ->where('parent_checklist_id', $checklist->parent_checklist_id)
            ->get();

        foreach ($trashedChildren as $child) {
            $child->title = $checklist->title;
            $child->repeat_interval = $checklist->repeat_interval;
            $child->repeat_type = $checklist->repeat_type;
            $child->repeat_end_date = $checklist->repeat_end_date;
            $child->repeat_max_count = $checklist->repeat_max_count;
            $child->save();
        }

        return response()->json([
            'message' => 'Checklist updated successfully',
            'data' => $checklist->load('repeatDays')
        ]);
    }

    // DELETE CHECKLISTS (SOFT DELETE)
    /**
     * @OA\Delete(
     * path="/api/checklists/{id}",
     * tags={"Checklist"},
     * summary="Hapus checklist (soft delete)",
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="UUID checklist",
     * @OA\Schema(type="string", format="uuid")
     * ),
     * @OA\Response(response=200, description="Checklist dihapus"),
     * @OA\Response(response=403, description="Unauthorized"),
     * @OA\Response(response=404, description="Checklist tidak ditemukan")
     * )
     */
    public function destroy($id)
    {
        $checklist = Checklist::findOrFail($id);
        $user = Auth::user();

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Soft delete checklist
        $checklist->delete();

        // Soft delete child checklist
        Checklist::where('parent_checklist_id', $checklist->id)->delete();

        // Soft delete repeat days
        if (method_exists($checklist->repeatDays()->getModel(), 'bootSoftDeletes')) {
            $checklist->repeatDays()->delete();
        }

        return response()->json(['message' => 'Checklist deleted successfully']);
    }

    // RESTORE DELETED CHECKLISTS
    /**
     * @OA\Patch(
     *     path="/api/checklists/{id}/restore",
     *     summary="Restore deleted checklist",
     *     tags={"Checklist"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Checklist ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Checklist sukses dipulihkan",
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Checklist tidak ditemukan atau tidak dihapus"
     *     )
     * )
     */
    public function restore($id)
    {
        $checklist = Checklist::onlyTrashed()
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$checklist) {
            return response()->json([
                'message' => 'Checklist not found or not deleted'
            ], 404);
        }

        $checklist->restore();

        return response()->json([
            'message' => 'Checklist restored successfully',
            'data' => $checklist
        ], 200);
    }

    // MARK CHECKLIST AS COMPLETE
    /**
     * @OA\Post(
     * path="/api/checklists/{id}/complete",
     * tags={"Checklist"},
     * summary="Tandai checklist sebagai completed",
     * description="Menandai checklist sebagai selesai.",
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="UUID Checklist",
     * @OA\Schema(type="string", format="uuid")
     * ),
     * @OA\Response(response=200, description="Checklist ditandai selesai"),
     * @OA\Response(response=403, description="Tidak diizinkan"),
     * @OA\Response(response=404, description="Checklist tidak ditemukan")
     * )
     */
    public function markAsComplete($id)
    {
        $checklist = Checklist::with('repeatDays')->findOrFail($id);
        $user = auth()->user();

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($checklist->is_completed) {
            return response()->json(['message' => 'Checklist already completed'], 400);
        }

        $checklist->is_completed = true;
        $checklist->save();

        if ($checklist->has_generated_next) {
            return response()->json([
                'message' => 'Checklist completed successfully',
            ]);
        }

        if ($checklist->repeat_type === 'never') {
            return response()->json(['message' => 'Checklist marked as completed (no repeat)']);
        }

        if ($checklist->hasReachedRepeatLimit()) {
            return response()->json(['message' => 'Repeat limit reached']);
        }

        $currentDate = Carbon::parse($checklist->due_time);
        $nextDueTime = null;

        $repeatDays = $checklist->repeatDays
            ->pluck('day')
            ->map(fn($day) => strtolower($day))
            ->toArray();

        if (!empty($repeatDays)) {
            $dayMap = [
                'sunday' => 0,
                'monday' => 1,
                'tuesday' => 2,
                'wednesday' => 3,
                'thursday' => 4,
                'friday' => 5,
                'saturday' => 6,
            ];

            $repeatIndexes = collect($repeatDays)->map(fn($d) => $dayMap[$d])->sort()->values();
            $currentDayIndex = $currentDate->dayOfWeek;
            $nextDayIndex = null;

            foreach ($repeatIndexes as $dayIndex) {
                if ($dayIndex > $currentDayIndex) {
                    $nextDayIndex = $dayIndex;
                    break;
                }
            }

            if ($nextDayIndex !== null) {
                $daysToAdd = $nextDayIndex - $currentDayIndex;
            } else {
                $daysToAdd = (7 - $currentDayIndex) + $repeatIndexes->first();
            }

            $nextDueTime = $currentDate->copy()->addDays($daysToAdd);
        } else {
            switch ($checklist->repeat_interval) {
                case 'daily':
                    $nextDueTime = $currentDate->addDay();
                    break;
                case '3_days':
                    $nextDueTime = $currentDate->addDays(3);
                    break;
                case 'weekly':
                    $nextDueTime = $currentDate->addWeek();
                    break;
                case 'monthly':
                    $nextDueTime = $currentDate->addMonth();
                    break;
                case 'yearly':
                    $nextDueTime = $currentDate->addYear();
                    break;
            }
        }

        // Buat repeat instance baru
        Checklist::create([
            'user_id'              => $checklist->user_id,
            'parent_checklist_id'  => $checklist->parent_checklist_id ?? $checklist->id,
            'title'                => $checklist->title,
            'due_time'             => $nextDueTime,
            'repeat_interval'      => $checklist->repeat_interval,
            'repeat_type'          => $checklist->repeat_type,
            'repeat_end_date'      => $checklist->repeat_end_date,
            'repeat_max_count'     => $checklist->repeat_max_count,
            'repeat_current_count' => $checklist->repeat_current_count + 1,
            'is_completed'         => false,
            'has_generated_next'   => false,
        ]);

        // Tandai bahwa checklist ini sudah pernah generate
        $checklist->has_generated_next = true;
        $checklist->save();

        $formatedNextDueTime = $nextDueTime->format('l, Y-m-d H:i:s');

        return response()->json([
            'message' => 'Checklist completed successfully',
            'next_due_time' => $formatedNextDueTime,
        ]);
    }

    // UNMARK CHECKLIST AS COMPLETE
    /**
     * @OA\Post(
     * path="/api/checklists/{id}/uncomplete",
     * tags={"Checklist"},
     * summary="Tandai checklist sebagai uncompleted",
     * description="Menandai checklist sebagai belum selesai.",
     * security={{"bearerAuth":{}}},
     * @OA\Parameter(
     * name="id",
     * in="path",
     * required=true,
     * description="UUID Checklist",
     * @OA\Schema(type="string", format="uuid")
     * ),
     * @OA\Response(response=200, description="Checklist ditandai belum selesai"),
     * @OA\Response(response=403, description="Tidak diizinkan"),
     * @OA\Response(response=404, description="Checklist tidak ditemukan")
     * )
     */
    public function unmarkAsComplete($id)
    {
        $checklist = Checklist::findOrFail($id);
        $user = auth()->user();

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$checklist->is_completed) {
            return response()->json(['message' => 'Checklist is already uncompleted'], 400);
        }

        $checklist->is_completed = false;
        $checklist->save();

        return response()->json([
            'message' => 'Checklist uncompleted successfully'
        ]);
    }
}
