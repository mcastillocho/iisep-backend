<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DO \$\$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'enum_document_type') THEN
                    CREATE TYPE enum_document_type AS ENUM ('dni', 'ce', 'pasaporte', 'ruc');
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'enum_day_of_week') THEN
                    CREATE TYPE enum_day_of_week AS ENUM ('lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo');
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'enum_enrollment_status') THEN
                    CREATE TYPE enum_enrollment_status AS ENUM ('preinscrito', 'activo', 'completado', 'convalidado', 'retirado', 'anulado');
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'enum_student_career_status') THEN
                    CREATE TYPE enum_student_career_status AS ENUM ('activo', 'egresado', 'titulado', 'en_reserva', 'retirado');
                END IF;
            END \$\$;");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TYPE IF EXISTS enum_student_career_status');
            DB::statement('DROP TYPE IF EXISTS enum_enrollment_status');
            DB::statement('DROP TYPE IF EXISTS enum_day_of_week');
            DB::statement('DROP TYPE IF EXISTS enum_document_type');
        }
    }
};
