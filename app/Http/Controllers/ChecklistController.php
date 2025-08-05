<?php

namespace App\Http\Controllers;

use App\Models\Checklist;
use App\Models\ChecklistRepeatDay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class ChecklistController extends Controller
{
    // GET ALL CHECKLISTS
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
            ->where('is_completed', false)
            ->get();

        return response()->json($checklists);
    }

    /**
     * @OA\Get(
     *     path="/api/checklists/completed",
     *     tags={"Checklist"},
     *     summary="Ambil semua checklist yang sudah selesai (is_completed = true)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Checklist yang sudah selesai dikembalikan"),
     *     @OA\Response(response=401, description="Unauthorized")
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
     * summary="Ambil checklist yang jatuh tempo hari ini",
     * description="Mengembalikan daftar checklist yang jatuh tempo hari ini, termasuk yang berulang harian atau mingguan pada hari ini.",
     * security={{"bearerAuth":{}}},
     * @OA\Response(response=200, description="List checklist hari ini dikembalikan"),
     * @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function todayChecklists()
    {
        $user = Auth::user();
        $today = Carbon::today();
        $dayName = strtolower($today->englishDayOfWeek);

        $checklists = Checklist::with(['repeatDays' => function ($q) use ($dayName) {
            $q->where('day', $dayName)
                ->where('is_completed', false);
        }])
            ->when($user->role !== 'admin', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('is_completed', false)
            ->where(function ($query) use ($today, $dayName) {
                $query
                    ->whereDate('due_time', $today)

                    ->orWhere(function ($q) use ($today) {
                        $q->where('repeat_interval', 'daily')
                            ->whereDate('due_time', '<=', $today);
                    })

                    ->orWhere(function ($q) use ($dayName) {
                        $q->where('repeat_interval', 'weekly')
                            ->whereHas('repeatDays', function ($q2) use ($dayName) {
                                $q2->where('day', $dayName)
                                    ->where('is_completed', false);
                            });
                    });
            })
            ->get();

        return response()->json($checklists);
    }

    // GET CHECKLISTS THIS WEEK
    /**
     * @OA\Get(
     * path="/api/checklists/weekly",
     * tags={"Checklist"},
     * summary="Ambil checklist yang jatuh tempo minggu ini",
     * description="Mengembalikan daftar checklist yang jatuh tempo dalam minggu ini, termasuk yang berulang harian atau mingguan.",
     * security={{"bearerAuth":{}}},
     * @OA\Response(response=200, description="List checklist minggu ini dikembalikan"),
     * @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function weeklyChecklists()
    {
        $user = Auth::user();
        $today = Carbon::today();
        $startOfWeek = $today->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $today->copy()->endOfWeek(Carbon::SUNDAY);

        $checklists = Checklist::with(['repeatDays' => function ($q) {
            $q->where('is_completed', false);
        }])
            ->when($user->role !== 'admin', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->where('is_completed', false)
            ->where(function ($query) use ($startOfWeek, $endOfWeek) {
                $query
                    ->whereBetween('due_time', [$startOfWeek, $endOfWeek])

                    ->orWhere(function ($q) use ($endOfWeek) {
                        $q->whereIn('repeat_interval', ['daily', '3_days', 'weekly', 'monthly', 'yearly'])
                            ->whereDate('due_time', '<=', $endOfWeek);
                    });
            })
            ->where(function ($q) {
                $q->where('repeat_interval', '!=', 'weekly')
                    ->orWhereHas('repeatDays', function ($q2) {
                        $q2->where('is_completed', false);
                    });
            })
            ->get();

        return response()->json($checklists);
    }


    // POST CHECKLISTS
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

    // GET CHECKLIST BY ID
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

    // PUT CHECKLISTS
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

    // DELETE CHECKLISTS (SOFT DELETE)
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

    // RESTORE CHECKLISTS (SOFT DELETE)
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

    // MARK CHECKLIST AS COMPLETE AND CREATE NEW REPEAT
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
        $checklist = Checklist::with('repeatDays')->findOrFail($id);
        $user = auth()->user();

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $checklist->is_completed = true;
        $checklist->save();

        $checklist->repeatDays()->update(['is_completed' => true]);

        $newDueTime = match ($checklist->repeat_interval) {
            'daily'    => \Carbon\Carbon::parse($checklist->due_time)->addDay(),
            '3_days'   => \Carbon\Carbon::parse($checklist->due_time)->addDays(3),
            'weekly'   => \Carbon\Carbon::parse($checklist->due_time)->addWeek(),
            'monthly'  => \Carbon\Carbon::parse($checklist->due_time)->addMonth(),
            'yearly'   => \Carbon\Carbon::parse($checklist->due_time)->addYear(),
            default    => null,
        };

        if ($newDueTime) {
            $newChecklist = Checklist::create([
                'user_id'         => $checklist->user_id,
                'title'           => $checklist->title,
                'due_time'        => $newDueTime,
                'repeat_interval' => $checklist->repeat_interval,
                'is_completed'    => false,
            ]);

            if ($checklist->repeat_interval === 'weekly' && $checklist->repeatDays) {
                foreach ($checklist->repeatDays as $repeatDay) {
                    ChecklistRepeatDay::create([
                        'checklist_id' => $newChecklist->id,
                        'day'          => $repeatDay->day,
                        'is_completed' => false,
                    ]);
                }
            }

            return response()->json([
                'message'           => 'Checklist marked as completed and new checklist created.',
                'new_checklist_id'  => $newChecklist->id,
                'new_due_time'      => $newDueTime,
            ]);
        }

        return response()->json(['message' => 'Checklist marked as completed (non-repeatable or invalid interval).']);
    }

    // UNMARK CHECKLIST AS COMPLETE AND UNCOMPLETE REPEAT DAYS
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
     *     path="/api/checklists/{id}/complete-today",
     *     tags={"Checklist"},
     *     summary="Tandai repeat day checklist weekly",
     *     description="Secara otomatis menandai checklist weekly berdasarkan hari ini (misalnya 'monday').",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID checklist",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Repeat day hari ini ditandai selesai"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Checklist tidak ditemukan")
     * )
     */
    public function completeTodayRepeatDay($id)
    {
        $user = Auth::user();
        $checklist = Checklist::with('repeatDays')->findOrFail($id);

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($checklist->repeat_interval !== 'weekly') {
            return response()->json(['message' => 'This feature only supports weekly checklists'], 400);
        }

        $today = strtolower(Carbon::today()->englishDayOfWeek);
        $repeatDay = $checklist->repeatDays()->where('day', $today)->first();

        if (!$repeatDay) {
            return response()->json(['message' => "No repeat day found for today ($today)"], 404);
        }

        if ($repeatDay->is_completed) {
            return response()->json(['message' => "Repeat day '$today' already marked as completed"]);
        }

        $repeatDay->is_completed = true;
        $repeatDay->save();

        $allCompleted = $checklist->repeatDays()->where('is_completed', false)->count() === 0;

        if ($allCompleted) {
            $checklist->is_completed = true;
            $checklist->save();

            $newChecklist = Checklist::create([
                'user_id' => $checklist->user_id,
                'title' => $checklist->title,
                'due_time' => Carbon::parse($checklist->due_time)->addWeek(),
                'repeat_interval' => $checklist->repeat_interval,
                'is_completed' => false,
            ]);

            foreach ($checklist->repeatDays as $rd) {
                ChecklistRepeatDay::create([
                    'checklist_id' => $newChecklist->id,
                    'day' => $rd->day,
                    'is_completed' => false,
                ]);
            }

            return response()->json([
                'message' => "Repeat day '$today' marked as completed. Checklist completed and new weekly checklist created.",
                'new_checklist_id' => $newChecklist->id,
            ]);
        }

        return response()->json(['message' => "Repeat day '$today' marked as completed"]);
    }

    /**
     * @OA\Post(
     *     path="/api/checklists/{id}/uncomplete-today",
     *     tags={"Checklist"},
     *     summary="Tandai repeat day checklist hari ini sebagai belum selesai",
     *     description="Menandai repeat day checklist hari ini sebagai belum selesai.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UUID checklist",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(response=200, description="Repeat day hari ini ditandai belum selesai"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Checklist atau repeat day tidak ditemukan")
     * )
     */
    public function uncompleteTodayRepeatDay($id)
    {
        $user = Auth::user();
        $checklist = Checklist::with('repeatDays')->findOrFail($id);

        if ($user->role !== 'admin' && $checklist->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $today = strtolower(Carbon::now()->englishDayOfWeek);

        $repeatDay = $checklist->repeatDays()->where('day', $today)->first();

        if (!$repeatDay) {
            return response()->json(['message' => "Repeat day for today ($today) not found"], 404);
        }

        $repeatDay->is_completed = false;
        $repeatDay->save();

        if ($checklist->is_completed) {
            $checklist->is_completed = false;
            $checklist->save();
        }

        return response()->json(['message' => "Repeat day for today ($today) marked as not completed"]);
    }
}
