<section class="record-maintenance-section" id="record-maintenance">
    <div class="record-maintenance-heading"><div><span class="eyebrow">Archived record controls</span><h3>Restore or permanently remove records</h3><p>Recovery and deletion are centralized here so a database backup can be created before any destructive archive operation.</p></div><a class="secondary-outline-button" href="{{ route('admin.archives.index') }}">Open archive browser</a></div>

    <div class="settings-impact-note"><span>!</span><p><strong>Create a recovery snapshot first</strong>Restoration returns archived records to operational screens. Permanent deletion cannot be undone without restoring a complete database backup.</p></div>

    <div class="record-maintenance-grid">
        @foreach($groups as $group)
            <article class="card record-maintenance-card">
                <header><span>{{ $group['icon'] }}</span><div><h4>{{ $group['label'] }}</h4><small>Archived record maintenance</small></div></header>
                <div class="record-maintenance-count"><strong>{{ number_format($group['archived_count']) }}</strong><span>archived records</span></div>
                <div class="record-maintenance-actions">
                    @if($allowRestore)
                        <form method="POST" action="{{ route('admin.archives.restore', $group['type']) }}" onsubmit="return confirm('Restore all archived {{ strtolower($group['label']) }} to active screens?')">@csrf @method('PATCH')<button class="secondary-outline-button" type="submit" @disabled(!$group['archived_count'])>Restore all archived</button></form>
                    @else
                        <div class="record-maintenance-disabled">Restore is disabled by the archiving workflow.</div>
                    @endif
                    <form method="POST" action="{{ route('admin.archives.destroy', $group['type']) }}" onsubmit="return confirm('Permanently delete all archived {{ strtolower($group['label']) }}? This cannot be undone.')">
                        @csrf @method('DELETE')
                        @if($requireDeleteConfirmation)<label class="field-group"><span>Type DELETE to confirm</span><input name="confirmation" autocomplete="off" required pattern="DELETE" @disabled(!$group['archived_count'])></label>@endif
                        <button class="danger-button" type="submit" @disabled(!$group['archived_count'])>Permanently delete archived</button>
                    </form>
                </div>
            </article>
        @endforeach
    </div>

    <section class="card database-danger-zone">
        <div class="sectioning-toolbar"><div><span class="eyebrow">Destructive data operations</span><h3>Bulk deletion center</h3><p class="muted-cell">Delete archived operational data or account categories only after creating a database recovery snapshot.</p></div></div>
        <form method="POST" action="{{ route('admin.archives.bulk-destroy') }}" data-bulk-delete-form>
            @csrf @method('DELETE')
            <div class="bulk-delete-option-grid">
                @foreach($bulkDeleteTargets as $target)
                    <label class="bulk-delete-option {{ $target['count'] ? '' : 'disabled' }}"><input type="checkbox" name="targets[]" value="{{ $target['target'] }}" @disabled(!$target['count'])><span><strong>{{ $target['label'] }}</strong><small>{{ $target['description'] }}</small></span><b>{{ number_format($target['count']) }}</b></label>
                @endforeach
            </div>
            @error('targets')<small class="field-error">{{ $message }}</small>@enderror
            @error('targets.*')<small class="field-error">{{ $message }}</small>@enderror
            <div class="bulk-delete-confirmation"><div><strong>This action is permanent</strong><p>@if($requireDeleteConfirmation)Type <b>DELETE SELECTED</b>, then confirm the browser warning. @endif A database backup is the only recovery path after deletion.</p></div>@if($requireDeleteConfirmation)<label class="field-group"><span>Confirmation phrase</span><input name="confirmation" autocomplete="off" required pattern="DELETE SELECTED" placeholder="DELETE SELECTED"></label>@endif<button class="danger-button" type="submit">Delete selected categories</button></div>
            @error('confirmation')<small class="field-error">{{ $message }}</small>@enderror
        </form>
    </section>
</section>

<script>
document.querySelector('[data-bulk-delete-form]')?.addEventListener('submit', (event) => {
    const selected = [...event.currentTarget.querySelectorAll('input[name="targets[]"]:checked')];
    if (!selected.length || !window.confirm(`Permanently delete ${selected.length} selected record categor${selected.length === 1 ? 'y' : 'ies'}? This cannot be undone.`)) event.preventDefault();
});
</script>
