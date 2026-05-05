@extends('layouts.admin.app')

@section('title', translate('Staff Notices'))

@push('css_or_js')
    <link rel="stylesheet" href="{{asset('public/assets/admin/plugins/datatables/dataTables.bootstrap4.min.css')}}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="page-header-title">
                    <i class="tio-announcement"></i> {{ translate('Staff Notices') }}
                </h1>
                <a class="btn btn-primary" href="{{ route('admin.staff-notice.add-new') }}">
                    <i class="tio-add"></i> {{ translate('New notice') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ translate('All notices') }} ({{ $notices->total() }})</h5>
                <form action="" method="get" class="d-flex">
                    <input type="search" name="search" value="{{ $search }}" class="form-control me-2" placeholder="{{ translate('Search title') }}">
                    <button class="btn btn-outline-secondary">{{ translate('Search') }}</button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-borderless align-middle mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Title') }}</th>
                            <th>{{ translate('Scope') }}</th>
                            <th>{{ translate('Published') }}</th>
                            <th>{{ translate('Expires') }}</th>
                            <th>{{ translate('Pinned') }}</th>
                            <th class="text-end">{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($notices as $n)
                            <tr>
                                <td>{{ $n->id }}</td>
                                <td>
                                    <strong>{{ $n->title }}</strong>
                                    <div class="small text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($n->body), 80) }}</div>
                                </td>
                                <td>
                                    @if($n->branch_id)
                                        <span class="badge bg-info">{{ $branches->firstWhere('id', $n->branch_id)?->name ?? '#' . $n->branch_id }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ translate('Global') }}</span>
                                    @endif
                                </td>
                                <td>{{ optional($n->published_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                <td>{{ optional($n->expires_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                <td>{!! $n->is_pinned ? '<i class="tio-pin text-warning"></i>' : '' !!}</td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.staff-notice.edit', [$n->id]) }}">
                                        <i class="tio-edit"></i>
                                    </a>
                                    <form action="{{ route('admin.staff-notice.destroy', [$n->id]) }}" method="post" class="d-inline" onsubmit="return confirm('Delete this notice?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="tio-delete"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">{{ translate('No notices yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $notices->links() }}</div>
        </div>
    </div>
@endsection
