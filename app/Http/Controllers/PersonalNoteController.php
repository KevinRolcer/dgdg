<?php

namespace App\Http\Controllers;

use App\Models\PersonalNote;
use App\Models\PersonalNoteAttachment;
use App\Services\PersonalNoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class PersonalNoteController extends Controller
{
    public function __construct(
        private readonly PersonalNoteService $personalNoteService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $filter = $request->get('filter', 'all');
        $timeFilter = $request->get('time_filter', 'all');
        $month = $request->get('month');
        $year = $request->get('year');
        $folderId = $request->get('folder_id');
        $priority = $request->get('priority');
        $creationDate = $request->get('creation_date');

        if ($filter === 'calendar' && ($month === null || $month === '' || $year === null || $year === '')) {
            $month = now()->month;
            $year = now()->year;
        }

        $notes = $this->personalNoteService->getNotes($user->id, $filter, $timeFilter, $month, $year, $folderId, $priority, $creationDate);
        $folders = $this->personalNoteService->getFolders($user->id);

        if ($request->ajax()) {
            if ($folderId) {
                $currentFolder = \App\Models\PersonalNoteFolder::where('user_id', $user->id)->find($folderId);
                $partialHtml = $filter === 'calendar'
                    ? view('agenda.personal.partials.calendar_grid', compact('notes', 'filter', 'month', 'year'))->render()
                    : view('agenda.personal.partials.notes_grid', ['notes' => $notes, 'filter' => 'folder'])->render();

                return response()->json([
                    'html' => $partialHtml,
                    'folder' => $currentFolder ? ['id' => $currentFolder->id, 'name' => $currentFolder->name, 'icon' => $currentFolder->icon] : null,
                ]);
            }
            if ($filter === 'calendar') {
                return view('agenda.personal.partials.calendar_grid', compact('notes', 'filter', 'month', 'year'))->render();
            }
            return view('agenda.personal.partials.notes_grid', compact('notes', 'filter'))->render();
        }

        return view('agenda.personal.index', compact('notes', 'folders'));
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
            'password' => 'nullable|string|min:4',
            'color' => 'nullable|string|max:20',
            'priority' => 'required|in:none,low,medium,high',
            'reminder_at' => 'nullable|date',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable',
        ]);

        $note = $this->personalNoteService->createNote($validated, $request->user()->id);

        if ($request->hasFile('attachments')) {
            $newImages = collect($request->file('attachments'))->filter(fn ($f) => str_contains($f->getMimeType(), 'image'));
            $newDocs   = collect($request->file('attachments'))->reject(fn ($f) => str_contains($f->getMimeType(), 'image'));

            if ($newImages->count() > 10 || $newDocs->count() > 10) {
                return response()->json(['success' => false, 'message' => 'Máximo 10 imágenes y 10 archivos por nota.'], 422);
            }

            foreach ($request->file('attachments') as $file) {
                $path = $file->store('personal-notes/attachments', 'secure_shared');
                $note->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => str_contains($file->getMimeType(), 'image') ? 'image' : 'document',
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
            'password' => 'nullable|string|min:4',
            'color' => 'nullable|string|max:20',
            'priority' => 'required|in:none,low,medium,high',
            'reminder_at' => 'nullable|date',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable',
        ]);

        $this->personalNoteService->updateNote($note, $validated);

        if ($request->hasFile('attachments')) {
            $existingImages = $note->attachments()->where('file_type', 'image')->count();
            $existingDocs   = $note->attachments()->where('file_type', 'document')->count();
            $newImages = collect($request->file('attachments'))->filter(fn ($f) => str_contains($f->getMimeType(), 'image'));
            $newDocs   = collect($request->file('attachments'))->reject(fn ($f) => str_contains($f->getMimeType(), 'image'));

            if (($existingImages + $newImages->count()) > 10 || ($existingDocs + $newDocs->count()) > 10) {
                return response()->json(['success' => false, 'message' => 'Máximo 10 imágenes y 10 archivos por nota.'], 422);
            }

            foreach ($request->file('attachments') as $file) {
                $path = $file->store('personal-notes/attachments', 'secure_shared');
                $note->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => str_contains($file->getMimeType(), 'image') ? 'image' : 'document',
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

    /**
     * Folder Actions
     */
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

        if (!Hash::check($request->password, $note->password_verify_hash)) {
            return response()->json(['success' => false, 'message' => 'Contraseña incorrecta.'], 422);
        }

        $decryptedContent = $this->personalNoteService->decryptContent($note->content, $request->password);

        if ($decryptedContent === null) {
            // Content might not be properly encrypted — return raw content as fallback
            return response()->json([
                'success' => true,
                'content' => $note->content ?? ''
            ]);
        }

        return response()->json([
            'success' => true,
            'content' => $decryptedContent
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

        foreach (['secure_shared', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($attachment->file_path)) {
                Storage::disk($disk)->delete($attachment->file_path);
                break;
            }
        }

        $attachment->delete();

        return response()->json(['success' => true]);
    }

    public function serveAttachment(Request $request, PersonalNoteAttachment $attachment)
    {
        abort_unless($attachment->note->user_id === $request->user()->id, 403);

        foreach (['secure_shared', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($attachment->file_path)) {
                return response()->file(Storage::disk($disk)->path($attachment->file_path));
            }
        }

        abort(404);
    }
}
