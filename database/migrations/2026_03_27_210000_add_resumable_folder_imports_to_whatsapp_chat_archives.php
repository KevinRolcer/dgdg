<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('whatsapp_chat_archives', 'folder_source_signature')) {
            Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
                $table->string('folder_source_signature', 64)->nullable()->after('import_phase');
            });
        }

        if (! Schema::hasColumn('whatsapp_chat_archives', 'folder_root_name')) {
            Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
                $table->string('folder_root_name', 255)->nullable()->after('folder_source_signature');
            });
        }

        if (! Schema::hasColumn('whatsapp_chat_archives', 'folder_total_files')) {
            Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
                $table->unsignedInteger('folder_total_files')->default(0)->after('folder_root_name');
            });
        }

        if (! Schema::hasColumn('whatsapp_chat_archives', 'folder_uploaded_files')) {
            Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
                $table->unsignedInteger('folder_uploaded_files')->default(0)->after('folder_total_files');
            });
        }

        if (! $this->indexExists('whatsapp_chat_archives', 'wa_chat_archives_folder_signature_idx')) {
            Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
                $table->index(['created_by', 'folder_source_signature'], 'wa_chat_archives_folder_signature_idx');
            });
        }

        if (Schema::hasTable('whatsapp_chat_archive_upload_files')) {
            Schema::drop('whatsapp_chat_archive_upload_files');
        }

        Schema::create('whatsapp_chat_archive_upload_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('whatsapp_chat_archive_id');
            $table->string('relative_path', 4096);
            $table->char('relative_path_hash', 64);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedBigInteger('client_last_modified_at')->nullable();
            $table->timestamps();

            $table->unique(['whatsapp_chat_archive_id', 'relative_path_hash'], 'wa_chat_archive_upload_files_unique');
            $table->foreign('whatsapp_chat_archive_id', 'wa_chat_upf_archive_fk')
                ->references('id')
                ->on('whatsapp_chat_archives')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_chat_archive_upload_files');

        Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_chat_archives', 'folder_source_signature')
                || Schema::hasColumn('whatsapp_chat_archives', 'folder_root_name')
                || Schema::hasColumn('whatsapp_chat_archives', 'folder_total_files')
                || Schema::hasColumn('whatsapp_chat_archives', 'folder_uploaded_files')) {
                $columns = [];
                if (Schema::hasColumn('whatsapp_chat_archives', 'folder_source_signature')) {
                    $columns[] = 'folder_source_signature';
                }
                if (Schema::hasColumn('whatsapp_chat_archives', 'folder_root_name')) {
                    $columns[] = 'folder_root_name';
                }
                if (Schema::hasColumn('whatsapp_chat_archives', 'folder_total_files')) {
                    $columns[] = 'folder_total_files';
                }
                if (Schema::hasColumn('whatsapp_chat_archives', 'folder_uploaded_files')) {
                    $columns[] = 'folder_uploaded_files';
                }

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            }
        });

        if ($this->indexExists('whatsapp_chat_archives', 'wa_chat_archives_folder_signature_idx')) {
            Schema::table('whatsapp_chat_archives', function (Blueprint $table) {
                $table->dropIndex('wa_chat_archives_folder_signature_idx');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

        return count($rows) > 0;
    }
};
