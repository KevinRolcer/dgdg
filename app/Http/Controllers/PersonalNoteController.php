<?php

namespace App\Http\Controllers;

use App\Models\PersonalNote;
use App\Models\PersonalNoteAttachment;
use App\Services\PersonalNoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PersonalNoteController extends Controller
{
    private const ATTACHMENT_ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
    ];

    private const INLINE_ATTACHMENT_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'pdf', 'txt', 'csv',
    ];

    public function __construct(
        private readonly PersonalNoteService $personalNoteService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $filter = $request->get('filter', 'all');
        $timeFilter = $request->get('time_filter');
        if (! is_string($timeFilter) || $timeFilter === '') {
            $timeFilter = $filter === 'all' ? 'month' : 'all';
        }
        $month = $request->get('month');
        $year = $request->get('year');
        $folderId = $request->get('folder_id');
        $priority = $request->get('priority');
        $creationDate = $request->get('creation_date');
        $search = $request->get('search');
        $search = is_string($search) ? trim($search) : '';
        if (strlen($search) > 200) {
            $search = substr($search, 0, 200);
        }
        $searchParam = $search !== '' ? $search : null;

        if ($filter === 'calendar' && ($month === null || $month === '' || $year === null || $year === '')) {
            $month = now()->month;
            $year = now()->year;
        }

        if ($folderId && $folderId !== 'all') {
            $month = null;
            $year = null;
            $priority = null;
            $creationDate = null;
        }

        $notes = $this->personalNoteService->getNotes($user->id, $filter, $timeFilter, $month, $year, $folderId, $priority, $creationDate, $searchParam);

        $foldersAll = $this->personalNoteService->getFolders($user->id);
        $folders = $searchParam
            ? $this->personalNoteService->getFolders($user->id, $searchParam)
            : $foldersAll;

        $archivedFoldersAll = $filter === 'archive'
            ? $this->personalNoteService->getArchivedFolders($user->id)
            : collect();
        $archivedFolders = ($filter === 'archive' && $searchParam)
            ? $this->personalNoteService->getArchivedFolders($user->id, $searchParam)
            : $archivedFoldersAll;

        if ($request->ajax()) {
            if ($folderId) {
                $currentFolder = \App\Models\PersonalNoteFolder::withTrashed()
                    ->where('user_id', $user->id)
                    ->find($folderId);
                $partialHtml = $filter === 'calendar'
                    ? view('agenda.personal.partials.calendar_grid', compact('notes', 'filter', 'month', 'year'))->render()
                    : view('agenda.personal.partials.notes_grid', ['notes' => $notes, 'filter' => $filter === 'archive' ? 'archive' : 'folder'])->render();

                $response = [
                    'html' => $partialHtml,
                    'folder' => $currentFolder ? ['id' => $currentFolder->id, 'name' => $currentFolder->name, 'icon' => $currentFolder->icon] : null,
                ];

                if ($filter === 'archive') {
                    $response['archived_folders_html'] = view('agenda.personal.partials.archived_folders_grid', ['folders' => $archivedFolders])->render();
                    $response['folders'] = $archivedFoldersAll;
                }

                return response()->json($response);
            }
            if ($filter === 'folders_json') {
                return response()->json([
                    'html' => view('agenda.personal.partials.folders_grid', compact('folders'))->render(),
                    'folders' => $foldersAll,
                ]);
            }
            if ($filter === 'calendar') {
                return view('agenda.personal.partials.calendar_grid', compact('notes', 'filter', 'month', 'year'))->render();
            }
            if ($filter === 'archive') {
                return response()->json([
                    'html' => view('agenda.personal.partials.notes_grid', compact('notes', 'filter'))->render(),
                    'archived_folders_html' => view('agenda.personal.partials.archived_folders_grid', ['folders' => $archivedFolders])->render(),
                    'folders' => $archivedFoldersAll,
                ]);
            }
            if (in_array($filter, ['all', 'folders', 'today', 'upcoming', 'priorities', 'folder'])) {
                return response()->json([
                    'html' => view('agenda.personal.partials.notes_grid', compact('notes', 'filter'))->render(),
                    'folders' => $foldersAll,
                    'folders_html' => view('agenda.personal.partials.folders_grid', compact('folders'))->render(),
                ]);
            }

            return view('agenda.personal.partials.notes_grid', compact('notes', 'filter'))->render();
        }

        return view('agenda.personal.index', [
            'notes' => $notes,
            'folders' => $folders,
            'foldersAll' => $foldersAll,
            'archivedFolders' => $archivedFolders,
        ]);
    }

    public function store(Request $request)
    {
        $normalizedPassword = trim((string) $request->input('password', ''));
        $shouldEncrypt = $request->boolean('is_encrypted') && $normalizedPassword !== '';
        $request->merge([
            'is_encrypted' => $shouldEncrypt,
            'password' => $shouldEncrypt ? $normalizedPassword : null,
        ]);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'folder_id' => 'nullable|exists:personal_note_folders,id',
            'is_encrypted' => 'boolean',
            'password' => ['nullable', 'string', Password::min(10)->letters()->mixedCase()->numbers()],
            'color' => 'nullable|string|max:20',
            'priority' => 'required|in:'.implode(',', PersonalNote::PRIORITY_VALUES),
            'reminder_at' => 'nullable|date',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable',
            'attachments' => 'nullable|array|max:20',
            'attachments.*' => 'file|max:10240|mimes:'.implode(',', self::ATTACHMENT_ALLOWED_EXTENSIONS),
        ]);

        $note = $this->personalNoteService->createNote($validated, $request->user()->id);

        if ($request->hasFile('attachments')) {
            $newImages = collect($request->file('attachments'))->filter(fn ($f) => $this->isImageAttachment($f->getClientOriginalExtension()));
            $newDocs = collect($request->file('attachments'))->reject(fn ($f) => $this->isImageAttachment($f->getClientOriginalExtension()));

            if ($newImages->count() > 10 || $newDocs->count() > 10) {
                return response()->json(['success' => false, 'message' => 'Máximo 10 imágenes y 10 archivos por nota.'], 422);
            }

            foreach ($request->file('attachments') as $file) {
                $path = $file->store('personal-notes/attachments', 'secure_shared');
                $note->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $this->isImageAttachment($file->getClientOriginalExtension()) ? 'image' : 'document',
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        if ($request->ajax()) {
            return response()->json(['success' => true, 'note' => $note->load('attachments')]);
        }

        return redirect()->route('personal-agenda.index')->with('toast', 'Nota personal creada.');
    }

    public function archive(Request $request, PersonalNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 403);
        $this->personalNoteService->archiveNote($note);

        return response()->json(['success' => true]);
    }

    public function emptyTrash(Request $request)
    {
        $deleted = $this->personalNoteService->emptyTrash($request->user()->id);

        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    public function restore(Request $request, $id)
    {
        $note = PersonalNote::withTrashed()->findOrFail($id);
        abort_unless($note->user_id === $request->user()->id, 403);

        if ($note->trashed()) {
            $note->restore();
        } else {
            $this->personalNoteService->unarchiveNote($note);
        }

        return response()->json(['success' => true]);
    }

    public function update(Request $request, PersonalNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $normalizedPassword = trim((string) $request->input('password', ''));
        $shouldEncrypt = $request->boolean('is_encrypted') && $normalizedPassword !== '';
        $request->merge([
            'is_encrypted' => $shouldEncrypt,
            'password' => $shouldEncrypt ? $normalizedPassword : null,
        ]);

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'folder_id' => 'nullable|exists:personal_note_folders,id',
            'is_encrypted' => 'boolean',
            'password' => ['nullable', 'string', Password::min(10)->letters()->mixedCase()->numbers()],
            'color' => 'nullable|string|max:20',
            'priority' => 'required|in:'.implode(',', PersonalNote::PRIORITY_VALUES),
            'reminder_at' => 'nullable|date',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable',
            'attachments' => 'nullable|array|max:20',
            'attachments.*' => 'file|max:10240|mimes:'.implode(',', self::ATTACHMENT_ALLOWED_EXTENSIONS),
        ]);

        $this->personalNoteService->updateNote($note, $validated);

        if ($request->hasFile('attachments')) {
            $existingImages = $note->attachments()->where('file_type', 'image')->count();
            $existingDocs = $note->attachments()->where('file_type', 'document')->count();
            $newImages = collect($request->file('attachments'))->filter(fn ($f) => $this->isImageAttachment($f->getClientOriginalExtension()));
            $newDocs = collect($request->file('attachments'))->reject(fn ($f) => $this->isImageAttachment($f->getClientOriginalExtension()));

            if (($existingImages + $newImages->count()) > 10 || ($existingDocs + $newDocs->count()) > 10) {
                return response()->json(['success' => false, 'message' => 'Máximo 10 imágenes y 10 archivos por nota.'], 422);
            }

            foreach ($request->file('attachments') as $file) {
                $path = $file->store('personal-notes/attachments', 'secure_shared');
                $note->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $this->isImageAttachment($file->getClientOriginalExtension()) ? 'image' : 'document',
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        if ($request->ajax()) {
            return response()->json(['success' => true, 'note' => $note->load('attachments')]);
        }

        return redirect()->route('personal-agenda.index')->with('toast', 'Nota actualizada.');
    }

    public function destroy(Request $request, PersonalNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 403);
        $note->delete();

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('personal-agenda.index')->with('toast', 'Nota eliminada.');
    }

    public function storeFolder(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
        ]);

        $folder = $this->personalNoteService->createFolder($validated, $request->user()->id);

        return response()->json(['success' => true, 'folder' => $folder]);
    }

    public function updateFolder(Request $request, \App\Models\PersonalNoteFolder $folder)
    {
        abort_unless($folder->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
        ]);

        $this->personalNoteService->updateFolder($folder, $validated);

        return response()->json(['success' => true, 'folder' => $folder->fresh()]);
    }

    public function archiveFolder(Request $request, \App\Models\PersonalNoteFolder $folder)
    {
        abort_unless($folder->user_id === $request->user()->id, 403);

        $this->personalNoteService->archiveFolder($folder);

        return response()->json(['success' => true]);
    }

    public function restoreFolder(Request $request, int $folderId)
    {
        $folder = \App\Models\PersonalNoteFolder::onlyTrashed()
            ->where('user_id', $request->user()->id)
            ->whereKey($folderId)
            ->firstOrFail();

        $this->personalNoteService->restoreFolder($folder);

        return response()->json(['success' => true]);
    }

    public function toggleFolderPin(Request $request, \App\Models\PersonalNoteFolder $folder)
    {
        abort_unless($folder->user_id === $request->user()->id, 403);

        $result = $this->personalNoteService->toggleFolderPin($folder);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'No se pudo actualizar la fijación.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'pinned' => $result['pinned'],
            'pinned_count' => $result['pinned_count'],
        ]);
    }

    public function destroyFolder(Request $request, \App\Models\PersonalNoteFolder $folder)
    {
        abort_unless($folder->user_id === $request->user()->id, 403);
        $deleteNotes = $request->boolean('delete_notes');
        $this->personalNoteService->deleteFolder($folder, $deleteNotes);

        return response()->json(['success' => true]);
    }

    public function decrypt(Request $request, PersonalNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $request->validate([
            'password' => 'required|string',
        ]);

        if (! Hash::check($request->password, $note->password_verify_hash)) {
            return response()->json(['success' => false, 'message' => 'Contraseña incorrecta.'], 422);
        }

        $decryptedContent = $this->personalNoteService->decryptContent($note->content, $request->password);

        if ($decryptedContent === null) {
            return response()->json([
                'success' => false,
                'message' => 'No fue posible descifrar la nota con la contraseña proporcionada.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'content' => $decryptedContent,
        ]);
    }

    public function moveToFolder(Request $request, PersonalNote $note)
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $request->validate([
            'folder_id' => 'nullable|exists:personal_note_folders,id'
        ]);

        $note->update(['folder_id' => $request->folder_id]);

        return response()->json(['success' => true]);
    }

    public function deleteAttachment(Request $request, PersonalNoteAttachment $attachment)
    {
        abort_unless($attachment->note->user_id === $request->user()->id, 403);

        [$disk, $path] = $this->resolveAttachmentStorage($attachment);
        if ($disk !== null && $path !== null) {
            Storage::disk($disk)->delete($path);
        }

        $attachment->delete();

        return response()->json(['success' => true]);
    }

    public function serveAttachment(Request $request, PersonalNoteAttachment $attachment): BinaryFileResponse
    {
        abort_unless($attachment->note->user_id === $request->user()->id, 403);

        [$disk, $path] = $this->resolveAttachmentStorage($attachment);
        abort_unless($disk !== null && $path !== null, 404);

        $absolutePath = Storage::disk($disk)->path($path);
        $downloadName = $this->safeAttachmentDownloadName($attachment);

        if ($this->isInlineAttachment($downloadName)) {
            return response()->file($absolutePath, [
                'Content-Disposition' => 'inline; filename="'.$downloadName.'"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->download($absolutePath, $downloadName, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function resolveAttachmentStorage(PersonalNoteAttachment $attachment): array
    {
        $normalizedPath = ltrim(str_replace('\\', '/', trim((string) $attachment->file_path)), '/');
        if ($normalizedPath === '' || ! str_starts_with($normalizedPath, 'personal-notes/attachments/')) {
            return [null, null];
        }

        foreach (['secure_shared', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($normalizedPath)) {
                return [$disk, $normalizedPath];
            }
        }

        return [null, null];
    }

    private function safeAttachmentDownloadName(PersonalNoteAttachment $attachment): string
    {
        $name = trim((string) $attachment->file_name);
        if ($name === '') {
            $name = basename((string) $attachment->file_path);
        }

        $sanitized = preg_replace('/[^\w.\- ]+/u', '_', $name) ?? 'adjunto';

        return trim($sanitized) !== '' ? $sanitized : 'adjunto';
    }

    private function isInlineAttachment(string $fileName): bool
    {
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));

        return in_array($extension, self::INLINE_ATTACHMENT_EXTENSIONS, true);
    }

    private function isImageAttachment(?string $extension): bool
    {
        return in_array(strtolower((string) $extension), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
    }
}
