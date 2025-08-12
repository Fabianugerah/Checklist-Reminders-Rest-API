<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('checklist_repeat_days', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('checklist_id')->index();
            $table->uuid('parent_checklist_id')->nullable()->index();
            $table->enum('day', [
                'monday',
                'tuesday',
                'wednesday',
                'thursday',
                'friday',
                'saturday',
                'sunday'
            ]);
            $table->timestamps();

            $table->foreign('checklist_id')
                ->references('id')
                ->on('checklists')
                ->onDelete('cascade');

            $table->foreign('parent_checklist_id')
                ->references('id')
                ->on('checklists')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_repeat_days');
    }
};
