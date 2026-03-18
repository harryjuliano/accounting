<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dimensions', function (Blueprint $table) {
            $table->json('attribute_schema_json')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('dimensions', function (Blueprint $table) {
            $table->dropColumn('attribute_schema_json');
        });
    }
};
