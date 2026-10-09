<?php

declare(strict_types=1);

use App\Enums\DayOfWeek;
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
        Schema::create('course_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_course_id')->index()->constrained('scheduled_courses')->cascadeOnDelete();
            $table->foreignId('classroom_id')->nullable()->index()->constrained('classrooms');
            $table->pgEnum('day_of_week', 'enum_day_of_week', array_column(DayOfWeek::cases(), 'value'));
            $table->time('start_time');
            $table->time('end_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_schedules');
    }
};
