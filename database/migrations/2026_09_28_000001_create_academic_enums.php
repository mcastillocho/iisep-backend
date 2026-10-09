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
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'enum_tipo_cliente') THEN
                    CREATE TYPE enum_tipo_cliente AS ENUM ('estudiante', 'apoderado', 'docente', 'particular');
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'enum_tipo_documento') THEN
                    CREATE TYPE enum_tipo_documento AS ENUM ('dni', 'ce', 'pasaporte', 'ruc');
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'enum_dia_semana') THEN
                    CREATE TYPE enum_dia_semana AS ENUM ('lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo');
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'enum_estado_matricula') THEN
                    CREATE TYPE enum_estado_matricula AS ENUM ('preinscrito', 'activo', 'completado', 'retirado', 'anulado');
                END IF;
                IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'enum_estado_carrera_estudiante') THEN
                    CREATE TYPE enum_estado_carrera_estudiante AS ENUM ('activo', 'egresado', 'titulado', 'en_reserva', 'retirado');
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
            DB::statement('DROP TYPE IF EXISTS enum_estado_carrera_estudiante');
            DB::statement('DROP TYPE IF EXISTS enum_estado_matricula');
            DB::statement('DROP TYPE IF EXISTS enum_dia_semana');
            DB::statement('DROP TYPE IF EXISTS enum_tipo_documento');
            DB::statement('DROP TYPE IF EXISTS enum_tipo_cliente');
        }
    }
};
