<tr class="{{ $isChild ? 'child-row' : '' }}">
    <td>
        <span class="cat-pill" style="background: {{ $cat->color }};">{{ $cat->name }}</span>
        @if($cat->description)
            <div style="font-size:11px; color:#6A6A70; margin-top:2px; max-width:380px;">{{ $cat->description }}</div>
        @endif
    </td>
    <td><span class="code-mono">{{ $cat->code }}</span></td>
    <td style="font-variant-numeric:tabular-nums;">{{ $cat->expenses_count ?? 0 }}</td>
    <td>
        @if($cat->is_active)<span class="status-pill on">ACTIVE</span>@else<span class="status-pill off">INACTIVE</span>@endif
    </td>
    <td>
        <div class="row-actions">
            @php
                $editPayload = json_encode([
                    'id'          => $cat->id,
                    'name'        => $cat->name,
                    'code'        => $cat->code,
                    'parent_id'   => $cat->parent_id,
                    'color'       => $cat->color,
                    'sort_order'  => $cat->sort_order,
                    'description' => $cat->description,
                    'is_active'   => (bool) $cat->is_active,
                ], JSON_HEX_APOS | JSON_HEX_QUOT);
            @endphp
            <button type="button" class="btn btn-light" onclick='lhEditCat({!! $editPayload !!})'>{{ translate('Edit') }}</button>
            <form method="POST" action="{{ route('admin.expense-categories.destroy', ['id' => $cat->id]) }}" onsubmit="return confirm('{{ translate('Delete this category? Deactivates if any bills use it.') }}')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-light" style="color:#C82626;">✕</button>
            </form>
        </div>
    </td>
</tr>
