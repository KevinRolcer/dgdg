<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppChatArchive;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppFolderChunkUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('whatsapp_chats');
        config()->set('whatsapp_chats.storage_disk', 'whatsapp_chats');
        config()->set('filesystems.default', 'whatsapp_chats');

        $this->ensurePermissionTables();
        $this->ensureApplicationTables();

        $this->withoutMiddleware();
    }

    private function ensurePermissionTables(): void
    {
        if (! Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }

        if (! Schema::hasTable('model_has_permissions')) {
            Schema::create('model_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');

                $table->index(['model_id', 'model_type']);
                $table->primary(['permission_id', 'model_id', 'model_type']);
            });
        }

        if (! Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');

                $table->index(['model_id', 'model_type']);
                $table->primary(['role_id', 'model_id', 'model_type']);
            });
        }

        if (! Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');

                $table->primary(['permission_id', 'role_id']);
            });
        }

        DB::table('permissions')->delete();
        DB::table('roles')->delete();
        DB::table('model_has_permissions')->delete();
        DB::table('model_has_roles')->delete();
        DB::table('role_has_permissions')->delete();
    }

    private function ensureApplicationTables(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['notifications', 'whatsapp_chat_access_logs', 'whatsapp_chat_archive_upload_files', 'whatsapp_chat_archives', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken()->nullable();
            $table->text('whatsapp_totp_secret')->nullable();
            $table->timestamp('whatsapp_totp_confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_chat_archives', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('original_zip_name')->nullable();
            $table->string('storage_root_path');
            $table->json('message_parts')->nullable();
            $table->unsignedInteger('message_parts_count')->default(0);
            $table->unsignedInteger('created_by')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->text('wrapped_dek')->nullable();
            $table->unsignedTinyInteger('encrypted_key_version')->default(1);
            $table->string('storage_disk', 64)->default('whatsapp_chats');
            $table->string('import_status', 32)->default('ready');
            $table->text('import_error')->nullable();
            $table->unsignedInteger('import_progress')->default(0);
            $table->string('import_phase', 255)->nullable();
            $table->string('folder_source_signature', 64)->nullable();
            $table->string('folder_root_name')->nullable();
            $table->unsignedInteger('folder_total_files')->default(0);
            $table->unsignedInteger('folder_uploaded_files')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_chat_archive_upload_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('whatsapp_chat_archive_id');
            $table->text('relative_path');
            $table->string('relative_path_hash', 64);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedBigInteger('client_last_modified_at')->nullable();
            $table->timestamps();
            $table->unique(['whatsapp_chat_archive_id', 'relative_path_hash'], 'wa_chat_archive_upload_files_unique');
        });

        Schema::create('whatsapp_chat_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('whatsapp_chat_archive_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 48);
            $table->string('resource_path', 1024)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_chunk_upload_assembles_file_and_registers_manifest(): void
    {
        $user = User::factory()->create();
        $signature = str_repeat('d', 64);
        $batchToken = '11111111-1111-4111-8111-111111111111';
        $relativePath = 'Chat Trabajo/media/video.mp4';

        $full = '0123456789abcdef';
        $chunkA = substr($full, 0, 6);
        $chunkB = substr($full, 6, 6);
        $chunkC = substr($full, 12);

        $common = [
            'batch_token' => $batchToken,
            'folder_signature' => $signature,
            'folder_total_files' => 1,
            'label' => 'Chat Trabajo',
            'root_name' => 'Chat Trabajo',
            'relative_path' => $relativePath,
            'file_size' => strlen($full),
            'last_modified' => 1711490000000,
            'chunk_total' => 3,
        ];

        $this->actingAs($user)->post(route('whatsapp-chats.admin.folder-upload-chunk'), array_merge($common, [
            'chunk_index' => 0,
            'chunk_offset' => 0,
            'chunk' => UploadedFile::fake()->createWithContent('c0.part', $chunkA),
        ]), ['Accept' => 'application/json'])->assertOk();

        $this->actingAs($user)->post(route('whatsapp-chats.admin.folder-upload-chunk'), array_merge($common, [
            'chunk_index' => 1,
            'chunk_offset' => strlen($chunkA),
            'chunk' => UploadedFile::fake()->createWithContent('c1.part', $chunkB),
        ]), ['Accept' => 'application/json'])->assertOk();

        $last = $this->actingAs($user)->post(route('whatsapp-chats.admin.folder-upload-chunk'), array_merge($common, [
            'chunk_index' => 2,
            'chunk_offset' => strlen($chunkA) + strlen($chunkB),
            'chunk' => UploadedFile::fake()->createWithContent('c2.part', $chunkC),
        ]), ['Accept' => 'application/json']);

        $last->assertOk()
            ->assertJsonPath('assembled', true)
            ->assertJsonPath('uploaded', 1);

        $archive = WhatsAppChatArchive::query()->where('folder_source_signature', $signature)->firstOrFail();
        $this->assertSame(1, (int) ($archive->folder_uploaded_files ?? 0));
        $this->assertTrue(Storage::disk('whatsapp_chats')->exists($archive->storage_root_path.'/'.$relativePath));
        $this->assertSame($full, Storage::disk('whatsapp_chats')->get($archive->storage_root_path.'/'.$relativePath));
    }
}

