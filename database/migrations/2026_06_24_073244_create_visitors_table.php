<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone');
            $table->string('company')->nullable();
            $table->enum('id_type', ['ic', 'passport', 'driving_license', 'other'])->default('ic');
            $table->string('id_number');
            $table->string('photo')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('id_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
