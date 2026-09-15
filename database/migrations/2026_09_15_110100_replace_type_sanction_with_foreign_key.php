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
        Schema::table('sanctions', function (Blueprint $table) {
            $table->dropColumn('type_sanction');
            $table->foreignId('type_sanction_id')->after('detenu_id')->constrained('types_sanction')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sanctions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('type_sanction_id');
            $table->string('type_sanction', 150)->after('detenu_id');
        });
    }
};
