<?php

declare(strict_types=1);

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
        Schema::create('scheduled_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->index()->constrained('branches');
            $table->foreignId('course_id')->index()->constrained('courses');
            $table->foreignId('teacher_id')->nullable()->index()->constrained('teachers');
            $table->date('start_date');
            $table->date('end_date');
            $table->timestamps();
            $table->softDeletes();
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE scheduled_courses ADD CONSTRAINT chk_scheduled_course_dates CHECK (start_date <= end_date)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_courses');
    }
};
