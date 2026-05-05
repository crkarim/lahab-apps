@csrf
<div class="row g-3 wa-form">
    <div class="col-md-4">
        <label class="form-label">{{ translate('Name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" maxlength="120" required
               placeholder="e.g. Morning Open"
               value="{{ old('name', $template->name ?? '') }}">
    </div>
    <div class="col-md-2">
        <label class="form-label">{{ translate('Kind') }} <span class="text-danger">*</span></label>
        <select name="kind" class="form-select" required>
            @foreach(['open' => 'Open (start of day)', 'daily' => 'Daily', 'close' => 'Close (end of day)', 'weekly' => 'Weekly'] as $k => $label)
                <option value="{{ $k }}" {{ old('kind', $template->kind ?? 'daily') === $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ translate('Scope') }}</label>
        <select name="branch_id" class="form-select">
            <option value="">{{ translate('Global (all branches)') }}</option>
            @foreach($branches as $b)
                <option value="{{ $b->id }}" {{ (old('branch_id', $template->branch_id ?? null) == $b->id) ? 'selected' : '' }}>{{ $b->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-1">
        <label class="form-label">{{ translate('Sort') }}</label>
        <input type="number" name="sort_order" class="form-control" min="0"
               value="{{ old('sort_order', $template->sort_order ?? 0) }}">
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <div class="form-check form-switch py-2">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                   {{ old('is_active', !isset($template) || $template->is_active) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">{{ translate('Active') }}</label>
        </div>
    </div>

    {{-- Notes hidden behind a disclosure — rarely used. --}}
    <div class="col-md-12">
        <details>
            <summary class="text-muted small" style="cursor:pointer">
                <i class="tio-edit"></i> {{ translate('Notes (optional)') }}
                @if(!empty($template->notes ?? ''))
                    <span class="badge bg-light text-muted ms-1">{{ Illuminate\Support\Str::limit($template->notes, 40) }}</span>
                @endif
            </summary>
            <textarea name="notes" class="form-control mt-2" rows="2"
                      placeholder="Internal notes for the office (not shown to staff)">{{ old('notes', $template->notes ?? '') }}</textarea>
        </details>
    </div>
</div>
