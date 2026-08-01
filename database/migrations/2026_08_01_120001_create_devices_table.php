<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('led_display');
            $table->string('mqtt_topic');
            $table->json('status')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique('mqtt_topic');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
