<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addTemporaryModuleEntriesMicrorregionFk();
        $this->addWhatsAppArchiveCreatorFk();
        $this->addWhatsAppAccessLogUserFk();
    }

    public function down(): void
    {
        if (Schema::hasTable('temporary_module_entries') && $this->foreignKeyExists('temporary_module_entries', 'tm_entries_microrregion_fk')) {
            Schema::table('temporary_module_entries', function (Blueprint $table): void {
                $table->dropForeign('tm_entries_microrregion_fk');
            });
        }

        if (Schema::hasTable('whatsapp_chat_archives') && $this->foreignKeyExists('whatsapp_chat_archives', 'wa_archives_created_by_fk')) {
            Schema::table('whatsapp_chat_archives', function (Blueprint $table): void {
                $table->dropForeign('wa_archives_created_by_fk');
            });
        }

        if (Schema::hasTable('whatsapp_chat_access_logs') && $this->foreignKeyExists('whatsapp_chat_access_logs', 'wa_access_logs_user_fk')) {
            Schema::table('whatsapp_chat_access_logs', function (Blueprint $table): void {
                $table->dropForeign('wa_access_logs_user_fk');
            });
        }
    }

    private function addTemporaryModuleEntriesMicrorregionFk(): void
    {
        if (! Schema::hasTable('temporary_module_entries') || ! Schema::hasTable('microrregiones') || ! Schema::hasColumn('temporary_module_entries', 'microrregion_id')) {
            return;
        }

        DB::table('temporary_module_entries')
            ->whereNotNull('microrregion_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('microrregiones')
                    ->whereColumn('microrregiones.id', 'temporary_module_entries.microrregion_id');
            })
            ->update(['microrregion_id' => null]);

        if (! $this->indexExists('temporary_module_entries', 'temporary_module_entries_microrregion_id_index')) {
            Schema::table('temporary_module_entries', function (Blueprint $table): void {
                $table->index('microrregion_id', 'temporary_module_entries_microrregion_id_index');
            });
        }

        if ($this->foreignKeyExists('temporary_module_entries', 'tm_entries_microrregion_fk')) {
            return;
        }

        Schema::table('temporary_module_entries', function (Blueprint $table): void {
            $table->foreign('microrregion_id', 'tm_entries_microrregion_fk')
                ->references('id')
                ->on('microrregiones')
                ->nullOnDelete();
        });
    }

    private function addWhatsAppArchiveCreatorFk(): void
    {
        if (! Schema::hasTable('whatsapp_chat_archives') || ! Schema::hasTable('users') || ! Schema::hasColumn('whatsapp_chat_archives', 'created_by')) {
            return;
        }

        DB::table('whatsapp_chat_archives')
            ->whereNotNull('created_by')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'whatsapp_chat_archives.created_by');
            })
            ->update(['created_by' => null]);

        if (! $this->indexExists('whatsapp_chat_archives', 'whatsapp_chat_archives_created_by_index')) {
            Schema::table('whatsapp_chat_archives', function (Blueprint $table): void {
                $table->index('created_by', 'whatsapp_chat_archives_created_by_index');
            });
        }

        if ($this->foreignKeyExists('whatsapp_chat_archives', 'wa_archives_created_by_fk')) {
            return;
        }

        Schema::table('whatsapp_chat_archives', function (Blueprint $table): void {
            $table->foreign('created_by', 'wa_archives_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    private function addWhatsAppAccessLogUserFk(): void
    {
        if (! Schema::hasTable('whatsapp_chat_access_logs') || ! Schema::hasTable('users') || ! Schema::hasColumn('whatsapp_chat_access_logs', 'user_id')) {
            return;
        }

        DB::table('whatsapp_chat_access_logs')
            ->whereNotNull('user_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'whatsapp_chat_access_logs.user_id');
            })
            ->update(['user_id' => null]);

        if (! $this->indexExists('whatsapp_chat_access_logs', 'whatsapp_chat_access_logs_user_id_index')) {
            Schema::table('whatsapp_chat_access_logs', function (Blueprint $table): void {
                $table->index('user_id', 'whatsapp_chat_access_logs_user_id_index');
            });
        }

        if ($this->foreignKeyExists('whatsapp_chat_access_logs', 'wa_access_logs_user_fk')) {
            return;
        }

        Schema::table('whatsapp_chat_access_logs', function (Blueprint $table): void {
            $table->foreign('user_id', 'wa_access_logs_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $rows = DB::select(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_TYPE = ?
               AND CONSTRAINT_NAME = ?
             LIMIT 1',
            [$table, 'FOREIGN KEY', $constraintName]
        );

        return count($rows) > 0;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

        return count($rows) > 0;
    }
};
