<?php

namespace Tests\Feature;

use App\Jobs\WhatsAppChatArchiveImportJob;
use App\Models\User;
use App\Models\WhatsAppChatArchive;
use App\Models\WhatsAppChatArchiveUploadFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppFolderUploadTest extends TestCase
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
            $table->unsignedTinyInteger('import_progress')->default(0);
            $table->string('import_phase', 255)->nullable();
            $table->string('folder_source_signature', 64)->nullable();
            $table->string('folder_root_name', 255)->nullable();
            $table->unsignedInteger('folder_total_files')->default(0);
            $table->unsignedInteger('folder_uploaded_files')->default(0);
            $table->timestamp('imported_at')->nullable();
        });

        Schema::create('whatsapp_chat_archive_upload_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_chat_archive_id')->constrained('whatsapp_chat_archives')->cascadeOnDelete();
            $table->string('relative_path', 4096);
            $table->char('relative_path_hash', 64);
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

    public function test_folder_upload_skips_files_already_registered_for_same_signature(): void
    {
        $user = User::factory()->create();
        $signature = str_repeat('a', 64);

        $payload = $this->folderUploadPayload($signature, [
            ['path' => 'Chat Trabajo/messages/message_1.html', 'name' => 'message_1.html', 'contents' => '<html>uno</html>', 'size' => 16, 'last_modified' => 1711490000000],
            ['path' => 'Chat Trabajo/_chat.txt', 'name' => '_chat.txt', 'contents' => '[27/03/2026, 10:00] Kevin: hola', 'size' => 33, 'last_modified' => 1711490005000],
        ]);

        $first = $this->actingAs($user)->post(route('whatsapp-chats.admin.folder-upload'), $payload, ['Accept' => 'application/json']);

        $first->assertOk()
            ->assertJsonPath('uploaded', 2)
            ->assertJsonPath('skipped', 0);

        $archive = WhatsAppChatArchive::query()->where('folder_source_signature', $signature)->firstOrFail();

        $this->assertSame(2, $archive->uploadFiles()->count());
        $this->assertTrue(Storage::disk('whatsapp_chats')->exists($archive->storage_root_path.'/Chat Trabajo/messages/message_1.html'));
        $this->assertTrue(Storage::disk('whatsapp_chats')->exists($archive->storage_root_path.'/Chat Trabajo/_chat.txt'));

        $second = $this->actingAs($user)->post(route('whatsapp-chats.admin.folder-upload'), $payload, ['Accept' => 'application/json']);

        $second->assertOk()
            ->assertJsonPath('uploaded', 0)
            ->assertJsonPath('skipped', 2)
            ->assertJsonPath('uploaded_in_archive', 2);

        $this->assertSame(1, WhatsAppChatArchive::query()->where('folder_source_signature', $signature)->count());
        $this->assertSame(2, WhatsAppChatArchiveUploadFile::query()->where('whatsapp_chat_archive_id', $archive->id)->count());
    }

    public function test_finalize_folder_upload_requires_all_expected_files_before_dispatching_import(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $signature = str_repeat('b', 64);

        $upload = $this->actingAs($user)->post(route('whatsapp-chats.admin.folder-upload'), $this->folderUploadPayload($signature, [
            ['path' => 'Chat Trabajo/messages/message_1.html', 'name' => 'message_1.html', 'contents' => '<html>uno</html>', 'size' => 16, 'last_modified' => 1711490000000],
        ], 2), ['Accept' => 'application/json']);

        $upload->assertOk()->assertJsonPath('uploaded', 1);

        $response = $this->actingAs($user)->post(route('whatsapp-chats.admin.folder-finalize'), [
            'folder_signature' => $signature,
            'folder_total_files' => 2,
            'label' => 'Chat Trabajo',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Faltan 1 archivos por subir. Repite la selección de carpeta y solo se enviará lo pendiente.');

        Queue::assertNothingPushed();
    }

    public function test_finalize_folder_upload_dispatches_import_when_manifest_is_complete(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $signature = str_repeat('c', 64);

        $this->actingAs($user)->post(route('whatsapp-chats.admin.folder-upload'), $this->folderUploadPayload($signature, [
            ['path' => 'Chat Trabajo/messages/message_1.html', 'name' => 'message_1.html', 'contents' => '<html>uno</html>', 'size' => 16, 'last_modified' => 1711490000000],
            ['path' => 'Chat Trabajo/_chat.txt', 'name' => '_chat.txt', 'contents' => '[27/03/2026, 10:00] Kevin: hola', 'size' => 33, 'last_modified' => 1711490005000],
        ]), ['Accept' => 'application/json'])->assertOk();

        $response = $this->actingAs($user)->post(route('whatsapp-chats.admin.folder-finalize'), [
            'folder_signature' => $signature,
            'folder_total_files' => 2,
            'label' => 'Chat Trabajo',
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('ok', true);

        $archive = WhatsAppChatArchive::query()->where('folder_source_signature', $signature)->firstOrFail();
        $this->assertSame(WhatsAppChatArchive::IMPORT_STATUS_PROCESSING, $archive->fresh()->import_status);

        Queue::assertPushed(WhatsAppChatArchiveImportJob::class, function (WhatsAppChatArchiveImportJob $job) use ($archive, $user) {
            return $job->archiveId === $archive->id
                && $job->userId === $user->id
                && $job->fromExtractedFolder === true;
        });
    }

    public function test_finalize_merges_duplicate_uploading_archives_with_same_folder_signature(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $signature = str_repeat('d', 64);

        $base = [
            'title' => 'Recibiendo archivos…',
            'original_zip_name' => 'Justicia',
            'message_parts' => [],
            'message_parts_count' => 0,
            'created_by' => $user->id,
            'is_encrypted' => false,
            'wrapped_dek' => null,
            'encrypted_key_version' => 1,
            'storage_disk' => 'whatsapp_chats',
            'import_status' => WhatsAppChatArchive::IMPORT_STATUS_UPLOADING,
            'import_error' => null,
            'import_progress' => 0,
            'import_phase' => 'Recibiendo archivos…',
            'folder_source_signature' => $signature,
            'folder_root_name' => 'Justicia',
            'folder_total_files' => 2,
            'folder_uploaded_files' => 1,
            'imported_at' => null,
        ];

        $master = WhatsAppChatArchive::create(array_merge($base, [
            'storage_root_path' => 'whatsapp-chats/master-uuid',
        ]));

        $slave = WhatsAppChatArchive::create(array_merge($base, [
            'storage_root_path' => 'whatsapp-chats/slave-uuid',
        ]));

        WhatsAppChatArchiveUploadFile::create([
            'whatsapp_chat_archive_id' => $master->id,
            'relative_path' => 'Justicia/a.txt',
            'relative_path_hash' => hash('sha256', 'Justicia/a.txt'),
            'file_size' => 1,
            'client_last_modified_at' => 1,
        ]);

        WhatsAppChatArchiveUploadFile::create([
            'whatsapp_chat_archive_id' => $slave->id,
            'relative_path' => 'Justicia/b.txt',
            'relative_path_hash' => hash('sha256', 'Justicia/b.txt'),
            'file_size' => 1,
            'client_last_modified_at' => 2,
        ]);

        Storage::disk('whatsapp_chats')->put('whatsapp-chats/master-uuid/Justicia/a.txt', 'a');
        Storage::disk('whatsapp_chats')->put('whatsapp-chats/slave-uuid/Justicia/b.txt', 'b');

        $response = $this->actingAs($user)->postJson(route('whatsapp-chats.admin.folder-finalize'), [
            'folder_signature' => $signature,
            'folder_total_files' => 2,
            'label' => 'Justicia',
        ]);

        $response->assertOk()->assertJsonPath('ok', true);

        $this->assertSame(1, WhatsAppChatArchive::query()->where('folder_source_signature', $signature)->count());
        $merged = WhatsAppChatArchive::query()->where('folder_source_signature', $signature)->firstOrFail();
        $this->assertSame((int) $master->id, (int) $merged->id);
        $this->assertSame(2, $merged->uploadFiles()->count());
        $this->assertTrue(Storage::disk('whatsapp_chats')->exists('whatsapp-chats/master-uuid/Justicia/a.txt'));
        $this->assertTrue(Storage::disk('whatsapp_chats')->exists('whatsapp-chats/master-uuid/Justicia/b.txt'));

        Queue::assertPushed(WhatsAppChatArchiveImportJob::class, function (WhatsAppChatArchiveImportJob $job) use ($master, $user) {
            return $job->archiveId === $master->id
                && $job->userId === $user->id
                && $job->fromExtractedFolder === true;
        });
    }

    /**
     * @param  array<int, array{path:string,name:string,contents:string,size:int,last_modified:int}>  $files
     * @return array<string, mixed>
     */
    private function folderUploadPayload(string $signature, array $files, ?int $totalFiles = null): array
    {
        $payload = [
            'batch_token' => (string) Str::uuid(),
            'folder_signature' => $signature,
            'folder_total_files' => $totalFiles ?? count($files),
            'label' => 'Chat Trabajo',
            'root_name' => 'Chat Trabajo',
            'relative_paths' => [],
            'file_sizes' => [],
            'last_modifieds' => [],
            'files' => [],
        ];

        foreach ($files as $file) {
            $payload['relative_paths'][] = $file['path'];
            $payload['file_sizes'][] = $file['size'];
            $payload['last_modifieds'][] = $file['last_modified'];
            $payload['files'][] = UploadedFile::fake()->createWithContent($file['name'], $file['contents']);
        }

        return $payload;
    }
}
