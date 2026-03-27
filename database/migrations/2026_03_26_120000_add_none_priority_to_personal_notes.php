<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE personal_notes MODIFY COLUMN priority ENUM('none','low','medium','high') NOT NULL DEFAULT 'none'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("UPDATE personal_notes SET priority = 'low' WHERE priority = 'none'");
            DB::statement("ALTER TABLE personal_notes MODIFY COLUMN priority ENUM('low','medium','high') NOT NULL DEFAULT 'low'");
        }
    }
};
