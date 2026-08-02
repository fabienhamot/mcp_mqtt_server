<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->json('capabilities')->nullable()->after('type');
            $table->string('status_topic')->nullable()->after('mqtt_topic');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['capabilities', 'status_topic']);
        });
    }
};
