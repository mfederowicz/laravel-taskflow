<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('task_ownership_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('performed_by')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('from_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('to_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('note')->nullable();

            $table->timestamps();
        });

        $this->backfillInitialOwnershipEntries();
    }

    /**
     * Seed a "created" entry for tasks that already exist, so the ownership
     * history is complete even for tasks created before this feature landed.
     */
    private function backfillInitialOwnershipEntries(): void
    {
        if (! DB::table('tasks')->exists()) {
            return;
        }

        if (DB::table('task_ownership_histories')->whereNotNull('from_user_id')->exists()) {
            return;
        }

        DB::table('tasks')->chunkById(500, function ($tasks) {
            foreach ($tasks as $task) {
                DB::table('task_ownership_histories')->insert([
                    'task_id' => $task->id,
                    'performed_by' => $task->user_id,
                    'from_user_id' => null,
                    'to_user_id' => $task->user_id,
                    'note' => null,
                    'created_at' => $task->created_at,
                    'updated_at' => $task->created_at,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_ownership_histories');
    }
};
