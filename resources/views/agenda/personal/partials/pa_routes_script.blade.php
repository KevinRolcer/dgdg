<script>
    window.paRoutes = {
        {{-- URL absoluta: si solo se usa /personal-agenda, fetch apunta al dominio raíz y falla (404) con la app en subcarpeta. --}}
        index: @json(route('personal-agenda.index')),
        store: "{{ route('personal-agenda.store') }}",
        foldersStore: "{{ route('personal-agenda.folders.store') }}",
        foldersUpdate: "{{ route('personal-agenda.folders.update', ['folder' => ':id']) }}",
        foldersArchive: "{{ route('personal-agenda.folders.archive', ['folder' => ':id']) }}",
        foldersRestore: "{{ preg_replace('#/[0-9]+/restore$#', '/:id/restore', route('personal-agenda.folders.restore', ['folderId' => 999999])) }}",
        foldersPin: "{{ route('personal-agenda.folders.pin', ['folder' => ':id']) }}",
        foldersDestroy: "{{ route('personal-agenda.folders.destroy', ['folder' => ':id']) }}",
        attachmentsDestroy: "{{ route('personal-agenda.attachments.destroy', ['attachment' => ':id']) }}",
        attachmentsServe: "{{ route('personal-agenda.attachments.serve', ['attachment' => ':id']) }}",
        decrypt: "{{ route('personal-agenda.decrypt', ['note' => ':id']) }}",
        archive: "{{ route('personal-agenda.archive', ['note' => ':id']) }}",
        restore: "{{ route('personal-agenda.restore', ['id' => ':id']) }}",
        move: "{{ route('personal-agenda.move', ['note' => ':id']) }}",
        update: "{{ route('personal-agenda.update', ['note' => ':id']) }}",
        destroy: "{{ route('personal-agenda.destroy', ['note' => ':id']) }}",
        trashEmpty: "{{ route('personal-agenda.trash.empty') }}",
    };
</script>
