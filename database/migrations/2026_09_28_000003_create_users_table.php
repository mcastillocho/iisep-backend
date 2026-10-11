<?php

declare(strict_types=1);

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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->text('first_name');
            $table->text('paternal_surname');
            $table->text('maternal_surname')->nullable();
            $table->text('document');
            $table->pgEnum('document_type', 'enum_document_type', array_column(DocumentType::cases(), 'value'));
            $table->date('birth_date')->nullable();
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->text('username')->unique();
            $table->text('password');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['document_type', 'document'], 'uq_user_document');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
