<?php

declare(strict_types=1);

use App\Enums\StudentCareerStatus;
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
        Schema::create('student_careers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('career_id')->index()->constrained('careers');
            $table->date('admission_date')->useCurrent();
            $table->pgEnum('status', 'enum_student_career_status', array_column(StudentCareerStatus::cases(), 'value'))->default('activo');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_id', 'career_id'], 'uq_student_career');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_careers');
    }
};
