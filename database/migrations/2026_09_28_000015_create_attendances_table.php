<?php

declare(strict_types=1);

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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_course_id')->constrained('scheduled_courses');
            $table->foreignId('client_id')->index()->constrained('clients');
            $table->date('date');
            $table->boolean('attended');
            $table->text('notes')->nullable();
            $table->foreignId('origin_scheduled_course_id')->nullable()->index()->constrained('scheduled_courses');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['scheduled_course_id', 'client_id', 'date'], 'uq_daily_attendance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
