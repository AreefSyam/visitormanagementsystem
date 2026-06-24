<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('department');
            $table->string('position')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('department');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosts');
    }
};
