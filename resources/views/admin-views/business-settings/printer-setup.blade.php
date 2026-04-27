@extends('layouts.admin.app')

@section('title', translate('Receipt Printer'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
            <h2 class="h1 mb-0 d-flex align-items-center gap-2">
                <i class="tio-print" style="font-size:24px; color:#E67E22"></i>
                <span class="page-header-title">
                    {{ translate('Receipt Printer') }}
                </span>
            </h2>
        </div>

        <div class="row g-3 mb-2">
            <div class="col-12 col-xl-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="page-title">{{ translate('Network thermal printer') }}</h4>
                    </div>
                    <div class="card-body p-30">
                        <p class="text-muted mb-4">
                            {{ translate('80mm thermal printer reachable on the local network. The same printer the admin POS uses today; once configured, the waiter app will print receipts here too.') }}
                        </p>

                        <form action="{{ route('admin.business-settings.web-app.printer.update') }}" method="POST" id="printer-form">
                            @csrf

                            <div class="d-flex align-items-center gap-4 mb-4">
                                <div class="custom-radio">
                                    <input type="radio" id="printer-active" name="enabled" value="1" {{ $settings['enabled'] ? 'checked' : '' }}>
                                    <label for="printer-active">{{ translate('Enabled') }}</label>
                                </div>
                                <div class="custom-radio">
                                    <input type="radio" id="printer-inactive" name="enabled" value="0" {{ $settings['enabled'] ? '' : 'checked' }}>
                                    <label for="printer-inactive">{{ translate('Disabled') }}</label>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="printer-ip" class="form-label">{{ translate('Printer IP address') }} *</label>
                                    <input type="text" id="printer-ip" name="ip" class="form-control"
                                           value="{{ $settings['ip'] }}" placeholder="192.168.1.50" required>
                                    <small class="form-text text-muted">{{ translate('LAN address of the thermal printer (check the printer\'s self-test page).') }}</small>
                                </div>

                                <div class="col-6 col-md-3">
                                    <label for="printer-port" class="form-label">{{ translate('Port') }}</label>
                                    <input type="number" id="printer-port" name="port" class="form-control"
                                           value="{{ $settings['port'] ?: 9100 }}" min="1" max="65535">
                                    <small class="form-text text-muted">{{ translate('Default 9100 (raw ESC/POS).') }}</small>
                                </div>

                                <div class="col-6 col-md-3">
                                    <label for="printer-width" class="form-label">{{ translate('Width (chars)') }}</label>
                                    <input type="number" id="printer-width" name="width_chars" class="form-control"
                                           value="{{ $settings['width_chars'] ?: 48 }}" min="24" max="64">
                                    <small class="form-text text-muted">{{ translate('48 for 80mm, 32 for 58mm.') }}</small>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="tio-save mr-1"></i> {{ translate('Save settings') }}
                                </button>
                                <button type="button" id="btn-test-print" class="btn btn-outline-success">
                                    <i class="tio-print mr-1"></i> {{ translate('Print test page') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title d-flex align-items-center gap-2">
                            <i class="tio-info-outined" style="color:#E67E22"></i>
                            {{ translate('How it works') }}
                        </h5>
                        <ul class="text-muted small mb-0 pl-3">
                            <li>{{ translate('The waiter tablet will tell the server to print; the server then opens a TCP socket to the printer.') }}</li>
                            <li>{{ translate('No driver install on the tablet. Tablets only need network access to the printer.') }}</li>
                            <li>{{ translate('If the test page fails, verify the printer IP from its self-test page and that port 9100 is reachable from the server.') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        $(function () {
            $('#btn-test-print').on('click', function () {
                const $btn = $(this);
                const original = $btn.html();
                $btn.prop('disabled', true).html('<i class="tio-loop mr-1"></i> {{ translate('Printing...') }}');

                $.ajax({
                    url: '{{ route('admin.business-settings.web-app.printer.test') }}',
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    data: $('#printer-form').serialize(),
                    success: function (res) {
                        toastr.success(res.message);
                    },
                    error: function (xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Print failed';
                        toastr.error(msg, '', { closeButton: true, timeOut: 6000 });
                    },
                    complete: function () {
                        $btn.prop('disabled', false).html(original);
                    }
                });
            });
        });
    </script>
@endpush
