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
        Schema::create('checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();

            // TAMBAHAN BARU: Parent ID untuk bridging repeat instances
            $table->uuid('parent_checklist_id')->nullable()->index();

            $table->string('title');
            $table->dateTime('due_time');
            $table->enum('repeat_interval', ['daily', '3_days', 'weekly', 'monthly', 'yearly'])->default('daily');

            // TAMBAHAN BARU: Repeat configuration
            $table->enum('repeat_type', ['never', 'until_date', 'after_count'])->default('never');
            $table->date('repeat_end_date')->nullable(); // Untuk "until_date"
            $table->integer('repeat_max_count')->nullable(); // Untuk "after_count"
            $table->integer('repeat_current_count')->default(0); // Counter saat ini

            $table->boolean('is_completed')->default(false);
            $table->softDeletes();
            $table->timestamps();

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parent_checklist_id')->references('id')->on('checklists')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklists');
    }
};
