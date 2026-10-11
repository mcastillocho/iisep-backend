<?php

declare(strict_types=1);

use App\Enums\EnrollmentStatus;
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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_course_id')->constrained('scheduled_courses');
            $table->foreignId('student_id')->index()->constrained('clients');
            $table->foreignId('student_career_id')->nullable()->index()->constrained('student_careers');
            $table->foreignId('guardian_id')->nullable()->index()->constrained('clients');
            $table->date('enrollment_date')->useCurrent();
            $table->pgEnum('status', 'enum_enrollment_status', array_column(EnrollmentStatus::cases(), 'value'))->default('preinscrito');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX uq_enrollment_student_course ON enrollments (scheduled_course_id, student_id) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
