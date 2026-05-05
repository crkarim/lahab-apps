@extends('layouts.admin.app')

@section('title', translate('Employee Edit'))

@push('css_or_js')
    <link href="{{asset('public/assets/back-end')}}/css/select2.min.css" rel="stylesheet"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
<div class="content container-fluid">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
        <h2 class="h1 mb-0 d-flex align-items-center gap-2">
            <img width="20" class="avatar-img" src="{{asset('public/assets/admin/img/icons/employee.png')}}" alt="">
            <span class="page-header-title">
                {{translate('Update_Employee')}}
            </span>
        </h2>
    </div>

    <div class="row">
        <div class="col-md-12">
            <form action="{{route('admin.employee.update',[$employee['id']])}}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0 d-flex align-items-center gap-2"><span class="tio-user"></span> {{translate('general_Information')}}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="employee_code">{{ translate('Employee ID') }}</label>
                                    <input type="text" name="employee_code" class="form-control" id="employee_code"
                                        placeholder="{{ translate('e.g. 1001 (matches biometric device User ID)') }}"
                                        value="{{ $employee->employee_code }}" maxlength="20" tabindex="1">
                                    <small class="form-text text-muted">
                                        {{ translate('Must match the User ID enrolled on the ZKTeco / biometric device for auto-attendance to work.') }}
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="name">{{translate('Name')}}</label>
                                    <input type="text" name="name" value="{{$employee['f_name'] . ' ' . $employee['l_name']}}" class="form-control" id="name"
                                        placeholder="{{translate('Ex')}} : {{translate('Md. Al Imrun')}}" tabindex="2">
                                </div>
                                <div class="form-group">
                                    <label for="phone">{{translate('Phone')}}</label>
                                    <input type="tel" value="{{$employee['phone']}}" required name="phone" class="form-control" id="phone"
                                        placeholder="{{translate('Ex')}} : +88017********" tabindex="3">
                                </div>
                                <div class="form-group">
                                    <label for="name">{{translate('Role')}}</label>
                                    <select class="custom-select" name="role_id" tabindex="3">
                                            <option value="0" selected disabled>---{{translate('select_Role')}}---</option>
                                            @foreach($roles as $role)
                                                <option
                                                    value="{{$role->id}}" {{$role['id']==$employee['admin_role_id']?'selected':''}}>{{translate($role->name)}}</option>
                                            @endforeach
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="branch_id">{{ translate('Branch') }}</label>
                                    <select class="custom-select" name="branch_id" id="branch_id">
                                        <option value="" {{ $employee->branch_id === null ? 'selected' : '' }}>{{ translate('No branch — HQ / global access') }}</option>
                                        @foreach(($branches ?? \App\Model\Branch::query()->orderBy('name')->get(['id','name'])) as $b)
                                            <option value="{{ $b->id }}" {{ (int) $employee->branch_id === (int) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">{{ translate('Pick a branch to scope this employee. Leave blank for HQ-wide accounts.') }}</small>
                                </div>

                                <div class="form-group">
                                    <label for="identity_type">{{translate('Identity Type')}}</label>
                                    <select class="custom-select" name="identity_type" id="identity_type" tabindex="4">
                                        <option value="passport" {{$employee->identity_type == 'passport'? 'selected' : ''}}>{{translate('passport')}}</option>
                                        <option value="driving_license" {{$employee->identity_type == 'driving_license'? 'selected' : ''}}>{{translate('driving_License')}}</option>
                                        <option value="nid" {{$employee->identity_type == 'nid'? 'selected' : ''}}>{{translate('NID')}}</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="identity_number">{{translate('identity_Number')}}</label>
                                    <input type="text" name="identity_number" class="form-control" id="identity_number" required value="{{$employee->identity_number}}" tabindex="5">
                                </div>

                                {{-- HRM Phase 2: employment details. Prepopulated from
                                     the admins row; fields default-zero for salary so
                                     payroll math is safe even when the row pre-dates
                                     this UI. --}}
                                <hr class="my-3">
                                <h6 class="mb-3" style="font-weight:700; color:#6A6A70; letter-spacing:1px; font-size:11px; text-transform:uppercase;">
                                    {{ translate('Employment details') }}
                                </h6>

                                <div class="form-group">
                                    <label for="joining_date">{{ translate('Joining date') }}</label>
                                    <input type="date" name="joining_date" id="joining_date" class="form-control" value="{{ optional($employee->joining_date)->format('Y-m-d') }}">
                                </div>

                                {{-- HRM Phase 6: Department + Designation are now
                                     dropdowns from master tables. The free-text
                                     designation below is kept as fallback / override. --}}
                                <div class="form-group">
                                    <label for="department_id">{{ translate('Department') }}</label>
                                    <select class="custom-select" name="department_id" id="department_id">
                                        <option value="">— {{ translate('not assigned') }} —</option>
                                        @foreach($departments as $dp)
                                            <option value="{{ $dp->id }}" {{ (string) $employee->department_id === (string) $dp->id ? 'selected' : '' }}>
                                                {{ $dp->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small style="color:#6A6A70; font-size:11px;">
                                        <a href="{{ route('admin.departments.index') }}" target="_blank">{{ translate('Manage departments') }}</a>
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="designation_id">{{ translate('Designation') }}</label>
                                    <select class="custom-select" name="designation_id" id="designation_id">
                                        <option value="">— {{ translate('not assigned') }} —</option>
                                        @foreach($designations as $des)
                                            @php
                                                $desLabel = $des->name;
                                                if ($des->grade)         $desLabel .= ' · ' . $des->grade;
                                                if ($des->default_basic) $desLabel .= ' · ~Tk ' . number_format((float) $des->default_basic, 0);
                                            @endphp
                                            <option value="{{ $des->id }}"
                                                    data-dept="{{ $des->department_id }}"
                                                    data-basic="{{ $des->default_basic }}"
                                                    {{ (string) $employee->designation_id === (string) $des->id ? 'selected' : '' }}>{{ $desLabel }}</option>
                                        @endforeach
                                    </select>
                                    <small style="color:#6A6A70; font-size:11px;">
                                        <a href="{{ route('admin.designations.index') }}" target="_blank">{{ translate('Manage designations') }}</a>
                                        · {{ translate('Or type a custom title below.') }}
                                    </small>
                                    <input type="text" name="designation" id="designation" class="form-control mt-2" maxlength="100" value="{{ $employee->designation }}" placeholder="{{ translate('Custom title (optional)') }}">
                                </div>

                                <div class="form-group">
                                    <label for="reports_to_admin_id">{{ translate('Reports to') }}</label>
                                    <select class="custom-select" name="reports_to_admin_id" id="reports_to_admin_id">
                                        <option value="">— {{ translate('no direct manager') }} —</option>
                                        @foreach($managers as $m)
                                            @php
                                                $mgrLabel = trim(($m->f_name ?? '') . ' ' . ($m->l_name ?? ''));
                                                if ($m->employee_code) $mgrLabel .= ' · ' . $m->employee_code;
                                                if ($m->designation)   $mgrLabel .= ' · ' . $m->designation;
                                            @endphp
                                            <option value="{{ $m->id }}"
                                                    data-branch="{{ $m->branch_id }}"
                                                    {{ (string) $employee->reports_to_admin_id === (string) $m->id ? 'selected' : '' }}>{{ $mgrLabel }}</option>
                                        @endforeach
                                    </select>
                                    <small style="color:#6A6A70; font-size:11px;">
                                        {{ translate('Drives leave-approval routing. Self + descendants are excluded to prevent cycles.') }}
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="employment_type">{{ translate('Employment type') }}</label>
                                    <select class="custom-select" name="employment_type" id="employment_type">
                                        @php $et = $employee->employment_type ?? 'full_time'; @endphp
                                        <option value="full_time" {{ $et === 'full_time' ? 'selected' : '' }}>{{ translate('Full-time') }}</option>
                                        <option value="part_time" {{ $et === 'part_time' ? 'selected' : '' }}>{{ translate('Part-time') }}</option>
                                        <option value="contract"  {{ $et === 'contract'  ? 'selected' : '' }}>{{ translate('Contract') }}</option>
                                        <option value="intern"    {{ $et === 'intern'    ? 'selected' : '' }}>{{ translate('Intern') }}</option>
                                    </select>
                                </div>

                                {{-- Salary structure — Phase 6.7 gross-distribute.
                                     Existing amounts loaded from admin_salary_lines.
                                     Pre-fills gross from the existing allowance sum
                                     so HR sees what's currently set without a
                                     separate "remembered gross" column. --}}
                                @php
                                    $existingLines       = $employee->salaryLines()->pluck('amount', 'component_id')->toArray();
                                    $allowanceComponents = \App\Models\SalaryComponent::activeAllowances();
                                    $distributionTotal   = (float) $allowanceComponents->sum('default_pct');
                                    $currentGross        = 0.0;
                                    foreach ($allowanceComponents as $ac) {
                                        $currentGross += (float) ($existingLines[$ac->id] ?? 0);
                                    }
                                @endphp

                                <div class="form-group">
                                    <label style="font-weight:700;">{{ translate('Gross monthly salary (Tk)') }}</label>
                                    <div class="form-row align-items-center" style="margin-bottom:8px;">
                                        <div class="col-7">
                                            <input type="number" step="0.01" min="0" id="lh-gross-input"
                                                   class="form-control" value="{{ number_format($currentGross, 2, '.', '') }}"
                                                   placeholder="e.g. 25000.00">
                                        </div>
                                        <div class="col-5">
                                            <button type="button" class="btn btn-primary btn-block" id="lh-distribute-btn">
                                                {{ translate('Distribute →') }}
                                            </button>
                                        </div>
                                    </div>
                                    <small style="color:#6A6A70; font-size:11px;">
                                        @if(abs($distributionTotal - 100) < 0.01)
                                            ✓ {{ translate('Allowance split sums to 100% — distribution will overwrite all allowance lines.') }}
                                        @else
                                            ⚠ {{ translate('Allowance percentages currently sum to') }} {{ number_format($distributionTotal, 2) }}%.
                                            <a href="{{ route('admin.salary-components.index') }}" target="_blank">{{ translate('Tune at /admin/salary-components') }}</a>
                                        @endif
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label style="font-weight:700;">{{ translate('Allowances (Tk/month)') }}</label>
                                    @foreach($allowanceComponents as $c)
                                        <div class="form-row align-items-center" style="margin-bottom:6px;">
                                            <div class="col-7" style="font-size:13px; padding-left:14px;">
                                                {{ $c->name }}
                                                @if($c->default_pct !== null && (float) $c->default_pct > 0)
                                                    <span style="font-size:10px; color:#6A6A70; font-weight:700; margin-left:4px;">
                                                        {{ rtrim(rtrim(number_format((float) $c->default_pct, 2, '.', ''), '0'), '.') }}%
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="col-5">
                                                <input type="number" step="0.01" min="0"
                                                    name="salary_lines[{{ $c->id }}]"
                                                    class="form-control form-control-sm lh-allowance-line"
                                                    data-pct="{{ $c->default_pct ?? 0 }}"
                                                    value="{{ $existingLines[$c->id] ?? 0 }}">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="form-group">
                                    <label style="font-weight:700;">{{ translate('Deductions (Tk/month)') }}</label>
                                    @foreach(\App\Models\SalaryComponent::activeDeductions() as $c)
                                        <div class="form-row align-items-center" style="margin-bottom:6px;">
                                            <div class="col-7" style="font-size:13px; padding-left:14px;">{{ $c->name }}</div>
                                            <div class="col-5">
                                                <input type="number" step="0.01" min="0"
                                                    name="salary_lines[{{ $c->id }}]"
                                                    class="form-control form-control-sm"
                                                    value="{{ $existingLines[$c->id] ?? 0 }}">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="emergency_contact_name">{{ translate('Emergency contact name') }}</label>
                                        <input type="text" name="emergency_contact_name" id="emergency_contact_name" class="form-control" maxlength="120" value="{{ $employee->emergency_contact_name }}">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="emergency_contact_phone">{{ translate('Emergency contact phone') }}</label>
                                        <input type="tel" name="emergency_contact_phone" id="emergency_contact_phone" class="form-control" maxlength="30" value="{{ $employee->emergency_contact_phone }}">
                                    </div>
                                </div>

                                {{-- HRM Phase 7a — Salary disbursement details. --}}
                                <hr class="my-3">
                                <h6 class="mb-3" style="font-weight:700; color:#6A6A70; letter-spacing:1px; font-size:11px; text-transform:uppercase;">
                                    {{ translate('Salary disbursement') }}
                                </h6>
                                @php $pm = $employee->payment_method ?? 'cash'; @endphp

                                <div class="form-group">
                                    <label for="payment_method">{{ translate('Payment method') }}</label>
                                    <select class="custom-select" name="payment_method" id="payment_method"
                                            onchange="lhPayMethodSwitch(this.value)">
                                        <option value="cash"   {{ $pm === 'cash' ? 'selected' : '' }}>{{ translate('Cash') }}</option>
                                        <option value="bank"   {{ $pm === 'bank' ? 'selected' : '' }}>{{ translate('Bank transfer') }}</option>
                                        <option value="mobile" {{ $pm === 'mobile' ? 'selected' : '' }}>{{ translate('Mobile money (bKash / Nagad / Rocket / Upay)') }}</option>
                                        <option value="cheque" {{ $pm === 'cheque' ? 'selected' : '' }}>{{ translate('Cheque') }}</option>
                                    </select>
                                </div>

                                <div id="lh-bank-block" style="display:{{ $pm === 'bank' ? 'block' : 'none' }};">
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>{{ translate('Bank name') }}</label>
                                            <input type="text" name="bank_name" class="form-control" maxlength="80" value="{{ $employee->bank_name }}" placeholder="e.g. Dutch-Bangla Bank">
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label>{{ translate('Branch') }}</label>
                                            <input type="text" name="bank_branch" class="form-control" maxlength="80" value="{{ $employee->bank_branch }}" placeholder="e.g. Gulshan">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>{{ translate('Account holder name') }}</label>
                                        <input type="text" name="bank_account_name" class="form-control" maxlength="120" value="{{ $employee->bank_account_name }}">
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-7">
                                            <label>{{ translate('Account number') }}</label>
                                            <input type="text" name="bank_account_number" class="form-control" maxlength="40" value="{{ $employee->bank_account_number }}">
                                        </div>
                                        <div class="form-group col-md-5">
                                            <label>{{ translate('Routing number') }} <small style="color:#6A6A70; font-weight:500;">({{ translate('BD: 9 digits') }})</small></label>
                                            <input type="text" name="bank_routing_number" class="form-control" maxlength="20" value="{{ $employee->bank_routing_number }}" placeholder="090274421">
                                        </div>
                                    </div>
                                </div>

                                <div id="lh-mobile-block" style="display:{{ $pm === 'mobile' ? 'block' : 'none' }};">
                                    <div class="form-row">
                                        <div class="form-group col-md-5">
                                            <label>{{ translate('Provider') }}</label>
                                            <select name="mobile_provider" class="custom-select">
                                                @php $mp = $employee->mobile_provider ?? ''; @endphp
                                                <option value="" {{ $mp === '' ? 'selected' : '' }}>—</option>
                                                <option value="bkash"  {{ $mp === 'bkash'  ? 'selected' : '' }}>bKash</option>
                                                <option value="nagad"  {{ $mp === 'nagad'  ? 'selected' : '' }}>Nagad</option>
                                                <option value="rocket" {{ $mp === 'rocket' ? 'selected' : '' }}>Rocket</option>
                                                <option value="upay"   {{ $mp === 'upay'   ? 'selected' : '' }}>Upay</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-7">
                                            <label>{{ translate('Wallet number') }}</label>
                                            <input type="tel" name="mobile_wallet_number" class="form-control" maxlength="20" value="{{ $employee->mobile_wallet_number }}" placeholder="01XXXXXXXXX">
                                        </div>
                                    </div>
                                </div>

                                <script>
                                function lhPayMethodSwitch(method) {
                                    document.getElementById('lh-bank-block').style.display   = method === 'bank'   ? 'block' : 'none';
                                    document.getElementById('lh-mobile-block').style.display = method === 'mobile' ? 'block' : 'none';
                                }
                                </script>
                            </div>
                            <div class="col-md-6">
                                <div class="card py-4 px-2">
                                    <div class="mb-4">
                                        <h4 class="mb-0 text-center">{{ translate('Image') }} <span class="text-danger">*</span> </h4>
                                    </div>
                                    <div class="text-center">
                                        <div class="upload-file_custom ratio-1 h-150px mx-auto">
                                            <input type="file" name="image"
                                                    class="upload-file__input single_file_input"
                                                    accept=".{{ implode(',.', array_column(IMAGEEXTENSION, 'key')) }}, |image/*"
                                                    data-maxFileSize="{{ readableUploadMaxFileSize('image') }}">
                                            <label class="upload-file__wrapper w-100 h-100 m-0">
                                                <div class="upload-file-textbox text-center" style="">
                                                    <img class="svg" src="{{asset('public/assets/admin/img/document-upload.svg')}}" alt="img">
                                                    <h6 class="mt-1 tc-clr fw-medium fs-10 lh-base text-center">
                                                        <span class="text-c2">{{ translate('Click to upload') }}</span>
                                                        <br>
                                                        {{ translate('Or drag and drop') }}
                                                    </h6>
                                                </div>
                                                <img class="upload-file-img" loading="lazy" src="{{ $employee->imageFullPath }}"
                                                        data-default-src="" alt="" style="display: none;">
                                            </label>
                                            <div class="overlay-review">
                                                <div
                                                    class="d-flex gap-1 justify-content-center align-items-center h-100">
                                                    <button type="button"
                                                            class="btn icon-btn view_btn">
                                                        <i class="tio-invisible"></i>
                                                    </button>
                                                    <button type="button"
                                                            class="btn icon-btn edit_btn">
                                                        <i class="tio-edit"></i>
                                                    </button>
                                                    <button type="button" class="remove_btn btn icon-btn">
                                                        <i class="tio-delete text-danger"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mb-0 fs-12 mt-20 text-center">
                                        {{ implode(', ', array_column(IMAGEEXTENSION, 'key')) }} {{ readableUploadMaxFileSize('image') }}
                                        <span class="font-medium text-title">(1:1)</span>
                                    </p>
                                </div>

                                <div class="form-group mb-0 mt-4">
                                    <label class="input-label">{{translate('identity_Image')}}</label>
                                    <div class="product--coba">
                                        <div class="row g-2" id="coba">
                                            @foreach($employee->identityImageFullPath as $identification_image)
                                                <div class="two__item w-20p">
                                                    <div class="max-h-140px existing-item">
                                                        <img src="{{$identification_image}}" alt="{{ translate('identity_image') }}">
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0 d-flex align-items-center gap-2"><span class="tio-user"></span> {{translate('account_Information')}}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="email">{{translate('Email')}}</label>
                                    <input type="email" value="{{$employee['email']}}" name="email" class="form-control" id="email"
                                        placeholder="{{translate('Ex')}} : ex@gmail.com" tabindex="7" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="password">{{translate('Password')}}</label>
                                    <small> ( {{translate('input if you want to change')}} )</small>
                                    <div class="input-group input-group-merge">
                                        <input type="password" name="password" class="js-toggle-password form-control form-control input-field" id="password"
                                               placeholder="{{translate('Ex: 8+ Characters')}}"
                                               data-hs-toggle-password-options='{
                                        "target": "#changePassTarget",
                                        "defaultClass": "tio-hidden-outlined",
                                        "showClass": "tio-visible-outlined",
                                        "classChangeTarget": "#changePassIcon"
                                        }' tabindex="8">
                                        <div id="changePassTarget" class="input-group-append">
                                            <a class="input-group-text" href="javascript:">
                                                <i id="changePassIcon" class="tio-visible-outlined"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="confirm_password">{{translate('confirm_Password')}}</label>
                                    <div class="input-group input-group-merge">
                                        <input type="password" name="confirm_password" class="js-toggle-password form-control form-control input-field"
                                               id="confirm_password" placeholder="{{translate('confirm password')}}"
                                               data-hs-toggle-password-options='{
                                                "target": "#changeConPassTarget",
                                                "defaultClass": "tio-hidden-outlined",
                                                "showClass": "tio-visible-outlined",
                                                "classChangeTarget": "#changeConPassIcon"
                                                }' tabindex="9">
                                        <div id="changeConPassTarget" class="input-group-append">
                                            <a class="input-group-text" href="javascript:">
                                                <i id="changeConPassIcon" class="tio-visible-outlined"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- My Lahab staff app access. The toggle is part of the
                     main form (saves on Update). The PIN itself uses a
                     separate inline form below — keeps a half-filled
                     employee form from accidentally wiping a working PIN. --}}
                <div class="card mt-3">
                    <div class="card-header">
                        <h4 class="mb-0">{{ translate('My Lahab — staff app access') }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="app_login_enabled" name="app_login_enabled" value="1" {{ old('app_login_enabled', $employee->app_login_enabled) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="app_login_enabled">
                                        {{ translate('Allow this employee to log in to the My Lahab app') }}
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    {{ translate('When off, the employee cannot log in to the staff app even if they have a PIN.') }}
                                </small>
                            </div>
                            <div class="col-md-6">
                                <div>
                                    <strong>{{ translate('PIN status') }}:</strong>
                                    @if(!empty($employee->app_pin_hash))
                                        <span class="badge bg-success">{{ translate('Set') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ translate('Not set') }}</span>
                                    @endif
                                </div>
                                <small class="text-muted d-block mt-1">
                                    {{ translate('Use the form below to set or reset the 4-6 digit PIN. The PIN is hashed; tell the employee in person.') }}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-3">
                    <button type="reset" class="btn btn-secondary" tabindex="10">{{translate('reset')}}</button>
                    <button type="submit" class="btn btn-primary" tabindex="11">{{translate('Update')}}</button>
                </div>
            </form>

            {{-- PIN reset is a separate POST so a half-filled main form
                 can never wipe a known-good PIN. --}}
            <form action="{{ route('admin.employee.set-app-pin', [$employee['id']]) }}" method="post" class="card mt-3">
                @csrf
                <div class="card-header">
                    <h5 class="mb-0">{{ translate('Set or reset the staff app PIN') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('New PIN (4-6 digits)') }}</label>
                            <input type="text" name="app_pin" class="form-control" inputmode="numeric" pattern="[0-9]{4,6}" maxlength="6" autocomplete="off" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-warning">
                                <i class="tio-key-outlined"></i> {{ translate('Save PIN') }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin/js/vendor.min.js')}}"></script>
    <script src="{{asset('public/assets/admin')}}/js/select2.min.js"></script>
    <script src="{{asset('public/assets/admin/js/image-upload.js')}}"></script>
    <script src="{{ asset('public/assets/admin/js/read-url.js') }}"></script>
    <script src="{{asset('public/assets/admin/js/spartan-multi-image-picker.js')}}"></script>

    <script>
        // HRM Phase 6.7 — Distribute gross across allowance line items by
        // each component's default_pct. Deductions are untouched.
        (function () {
            var btn = document.getElementById('lh-distribute-btn');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var grossEl = document.getElementById('lh-gross-input');
                var gross = parseFloat(grossEl && grossEl.value) || 0;
                if (gross <= 0) {
                    grossEl && grossEl.focus();
                    return;
                }
                if (!confirm('{{ translate("Distribute Tk ") }}' + gross.toFixed(2) + '{{ translate(" across allowance line items? Existing allowance values will be overwritten (deductions are untouched).") }}')) {
                    return;
                }
                var rows = document.querySelectorAll('.lh-allowance-line');
                var allocated = 0, lastNonZeroEl = null;
                rows.forEach(function (input) {
                    var pct = parseFloat(input.getAttribute('data-pct')) || 0;
                    if (pct <= 0) return;
                    var amount = Math.round(gross * pct) / 100;
                    input.value = amount.toFixed(2);
                    allocated += amount;
                    lastNonZeroEl = input;
                });
                if (lastNonZeroEl) {
                    var remainder = Math.round((gross - allocated) * 100) / 100;
                    if (Math.abs(remainder) > 0 && Math.abs(remainder) < 1) {
                        lastNonZeroEl.value = (parseFloat(lastNonZeroEl.value) + remainder).toFixed(2);
                    }
                }
            });
        })();
    </script>

    <script>
        "use strict";

        $(document).ready(function() {
            $('.upload-file__input').on('change', function(event) {
                var file = event.target.files[0];
                var $card = $(event.target).closest('.upload-file');
                var $textbox = $card.find('.upload-file-textbox');
                var $imgElement = $card.find('.upload-file-img');

                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $textbox.hide();
                        $imgElement.attr('src', e.target.result).show();
                    };
                    reader.readAsDataURL(file);
                }
            });
        });


        $(".js-example-theme-single").select2({
            theme: "classic"
        });

        $(".js-example-responsive").select2({
            width: 'resolve'
        });

        $(function () {

            let maxSizeReadable = "{{ readableUploadMaxFileSize('image') }}";
            let maxFileSize = 2 * 1024 * 1024; // default 2MB

            if (maxSizeReadable.toLowerCase().includes('mb')) {
                maxFileSize = parseFloat(maxSizeReadable) * 1024 * 1024;
            } else if (maxSizeReadable.toLowerCase().includes('kb')) {
                maxFileSize = parseFloat(maxSizeReadable) * 1024;
            }

            $("#coba").spartanMultiImagePicker({
                fieldName: 'identity_image[]',
                maxCount: 5,
                rowHeight: '230px',
                groupClassName: 'col-6 col-lg-4 ',
                maxFileSize: maxFileSize,
                placeholderImage: {
                    image: '{{asset('public/assets/admin/img/400x400/img2.jpg')}}',
                    width: '100%'
                },
                dropFileLabel: "Drop Here",
                onAddRow: function (index, file) {

                },
                onRenderedPreview: function (index) {

                },
                onRemoveRow: function (index) {

                },
                onExtensionErr: function (index, file) {
                    toastr.error('Please only input png or jpg type file', {
                        CloseButton: true,
                        ProgressBar: true
                    });
                },
                onSizeErr: function (index, file) {
                    toastr.error('File size must be less than ' + maxSizeReadable, {
                        CloseButton: true,
                        ProgressBar: true
                    });
                }
            });
        });
    </script>

@endpush
