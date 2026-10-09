<?php

declare(strict_types=1);

use App\Enums\TipoCliente;
use App\Enums\TipoDocumento;
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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->text('nombre');
            $table->text('apellido');
            $table->date('fecha_nacimiento')->nullable();
            $table->pgEnum('tipo_cliente', 'enum_tipo_cliente', array_column(TipoCliente::cases(), 'value'));
            $table->text('documento');
            $table->pgEnum('tipo_documento', 'enum_tipo_documento', array_column(TipoDocumento::cases(), 'value'));
            $table->text('email')->nullable();
            $table->text('telefono')->nullable();

            $table->unique(['tipo_documento', 'documento'], 'uq_cliente_documento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
