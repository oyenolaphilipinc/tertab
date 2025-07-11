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
        Schema::table('verification_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('verification_requests', 'document_type')) {
                $table->string('document_type')->after('user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verification_requests', function (Blueprint $table) {
            if (Schema::hasColumn('verification_requests', 'document_type')) {
                $table->dropColumn('document_type');
            }
        });
    }
}; 