<?php

declare(strict_types=1);

use App\Enums\ClientType;
use App\Enums\DocumentType;
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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->text('first_name');
            $table->text('last_name');
            $table->date('birth_date')->nullable();
            $table->pgEnum('client_type', 'enum_client_type', array_column(ClientType::cases(), 'value'));
            $table->text('document');
            $table->pgEnum('document_type', 'enum_document_type', array_column(DocumentType::cases(), 'value'));
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['document_type', 'document'], 'uq_client_document');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
