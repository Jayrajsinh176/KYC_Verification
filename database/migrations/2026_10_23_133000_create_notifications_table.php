<?php

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
        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('audience')->default('admin'); // 'admin', 'RT1001', 'all'
            $table->string('type')->default('info'); // 'info', 'warning', 'success', 'error'
            $table->string('title');
            $table->text('text');
            $table->boolean('read')->default(false);
            $table->string('link')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

