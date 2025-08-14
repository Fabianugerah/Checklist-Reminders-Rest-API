<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Checklist;
use App\Models\ChecklistRepeatDay;
use Illuminate\Support\Carbon;

class GenerateRepeatedChecklists extends Command
{
    protected $signature = 'checklists:generate-repeats';
    protected $description = 'Generate repeated checklists based on repeat settings';

    public function handle(): void
    {
        // Ambil original checklists yang completed dan perlu repeat
        $completedOriginals = Checklist::where('is_completed', true)
            ->where('repeat_type', '!=', 'never')
            ->get();

        foreach ($completedOriginals as $original) {
            // Check apakah sudah mencapai limit
            if ($original->hasReachedRepeatLimit()) {
                $this->info("Checklist '{$original->title}' reached repeat limit");
                continue;
            }

            $nextDueTime = $this->calculateNextDueTime($original);

            if (!$nextDueTime) {
                continue;
            }

            // Buat repeat instance baru
            $repeatInstance = Checklist::create([
                'user_id' => $original->user_id,
                'parent_checklist_id' => $original->id,
                'title' => $original->title,
                'due_time' => $nextDueTime,
                'repeat_interval' => $original->repeat_interval,
                'repeat_type' => $original->repeat_type,
                'repeat_end_date' => $original->repeat_end_date,
                'repeat_max_count' => $original->repeat_max_count,
                'repeat_current_count' => $original->repeat_current_count + 1,
                'is_completed' => false,
            ]);

            // Update counter di original
            $original->increment('repeat_current_count');

            // Copy repeat days jika weekly (bridge ke parent)
            if ($original->repeat_interval === 'weekly') {
                foreach ($original->repeatDays as $day) {
                    ChecklistRepeatDay::create([
                        'parent_checklist_id' => $original->id,
                        'day' => $day->day,
                    ]);
                }
            }

            $this->info("Generated repeat instance for: '{$original->title}' (Count: {$repeatInstance->repeat_current_count})");
        }
    }

    private function calculateNextDueTime(Checklist $checklist): ?Carbon
    {
        $currentDue = Carbon::parse($checklist->due_time);

        return match ($checklist->repeat_interval) {
            'daily' => $currentDue->addDay(),
            '3_days' => $currentDue->addDays(3),
            'weekly' => $currentDue->addWeek(),
            'monthly' => $currentDue->addMonth(),
            'yearly' => $currentDue->addYear(),
            default => null,
        };
    }
}
