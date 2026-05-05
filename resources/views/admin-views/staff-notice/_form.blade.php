@csrf
<div class="row g-2 sn-form">
    {{-- Row 1: title takes the spotlight; scope is a tight dropdown beside it. --}}
    <div class="col-md-10">
        <label class="form-label">{{ translate('Title') }} <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control" maxlength="200" required
               placeholder="e.g. New Eid menu briefing"
               value="{{ old('title', $notice->title ?? '') }}">
    </div>
    <div class="col-md-2">
        <label class="form-label">{{ translate('Scope') }}</label>
        <select name="branch_id" class="form-select">
            <option value="">{{ translate('All') }}</option>
            @foreach($branches as $b)
                <option value="{{ $b->id }}" {{ (old('branch_id', $notice->branch_id ?? null) == $b->id) ? 'selected' : '' }}
                        title="{{ $b->name }}">
                    {{ \Illuminate\Support\Str::limit($b->name, 14) }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Row 2: body, smaller default. --}}
    <div class="col-md-12">
        <label class="form-label">{{ translate('Body') }} <span class="text-danger">*</span></label>
        <textarea name="body" class="form-control" rows="3" required
                  placeholder="What do staff need to know?">{{ old('body', $notice->body ?? '') }}</textarea>
    </div>

    {{-- Row 3: every secondary field on one tight row. --}}
    <div class="col-md-3">
        <label class="form-label">{{ translate('Publish at') }}</label>
        <input type="datetime-local" name="published_at" class="form-control form-control-sm"
               value="{{ old('published_at', isset($notice->published_at) ? $notice->published_at->format('Y-m-d\TH:i') : '') }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ translate('Expires at') }}</label>
        <input type="datetime-local" name="expires_at" class="form-control form-control-sm"
               value="{{ old('expires_at', isset($notice->expires_at) ? $notice->expires_at->format('Y-m-d\TH:i') : '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ translate('Image (optional)') }}</label>
        <input type="file" name="image" accept="image/*" class="form-control form-control-sm">
        @if(!empty($notice->image))
            <small class="text-muted">{{ translate('Current') }}: {{ basename($notice->image) }}</small>
        @endif
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <label class="sn-pill" title="{{ translate('Pin to top of staff inbox') }}">
            <input type="checkbox" name="is_pinned" value="1"
                   {{ old('is_pinned', !empty($notice->is_pinned)) ? 'checked' : '' }}>
            <span><i class="tio-pin"></i> {{ translate('Pin to top') }}</span>
        </label>
    </div>

    {{-- Row 4: push notification toggle on its own highlighted strip. --}}
    <div class="col-md-12">
        <div class="sn-push-row">
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="send_push" name="send_push" value="1" checked>
                <label class="form-check-label" for="send_push">
                    <i class="tio-notifications-on"></i>
                    <strong>{{ translate('Send push notification on save') }}</strong>
                </label>
            </div>
            <small class="text-muted">{{ translate('Staff phones get a heads-up immediately.') }}</small>
        </div>
    </div>
</div>
