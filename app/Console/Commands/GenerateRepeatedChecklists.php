<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Checklist;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GenerateRepeatedChecklists extends Command
{
    protected $signature = 'checklists:generate-repeats';
    protected $description = 'Generate repeated checklists based on repeat_interval';

    public function handle(): void
    {
        $now = now();
        $checklists = Checklist::where('is_completed', true)->get();

        foreach ($checklists as $item) {
            $nextDue = null;

            switch ($item->repeat_interval) {
                case 'daily':
                    $nextDue = Carbon::parse($item->due_time)->addDay();
                    break;
                case '3_days':
                    $nextDue = Carbon::parse($item->due_time)->addDays(3);
                    break;
                case 'weekly':
                    $nextDue = Carbon::parse($item->due_time)->addWeek();
                    break;
                case 'monthly':
                    $nextDue = Carbon::parse($item->due_time)->addMonth();
                    break;
                case 'yearly':
                    $nextDue = Carbon::parse($item->due_time)->addYear();
                    break;
            }

            if ($nextDue) {
                // Buat checklist baru
                $newChecklist = Checklist::create([
                    'id' => Str::uuid(),
                    'user_id' => $item->user_id,
                    'title' => $item->title,
                    'due_time' => $nextDue,
                    'repeat_interval' => $item->repeat_interval,
                    'is_completed' => false,
                ]);

                if ($item->repeat_interval === 'weekly' && $item->repeatDays->isNotEmpty()) {
                    foreach ($item->repeatDays as $day) {
                        $newChecklist->repeatDays()->create([
                            'day' => $day->day,
                            'is_completed' => false,
                        ]);
                    }
                }

                $this->info("Generated repeat for checklist: {$item->title}");
            }
        }
    }
}
