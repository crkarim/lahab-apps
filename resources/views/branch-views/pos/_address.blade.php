@if (session()->has('address'))
    @php
        $address = session()->get('address')
    @endphp

    <div class="d-flex mb-1 gap-2">
        <span class="label min-w-60px fs-13 w-60px d-block">{{ translate('Name') }}</span>
        <span>:</span>
        <span class="fs-14 text-dark">{{ $address['contact_person_name'] }}</span>
    </div>
    <div class="d-flex mb-1 gap-2">
        <span class="label min-w-60px fs-13 w-60px d-block">{{ translate('Contact') }}</span>
        <span>:</span>
        <span class="fs-14 text-dark">{{ $address['contact_person_number'] }}</span>
    </div>
    @if( $address['area_name'] != null)
        <div class="d-flex mb-1 gap-2">
            <span class="label min-w-60px fs-13 w-60px d-block"> {{ translate('Area') }} </span>
            <span>:</span>
            <span class="fs-14 text-dark">{{ $address['area_name'] }}</span>
        </div>
    @endif
    <div class="d-flex mb-1 gap-2">
        <span class="label min-w-60px fs-13 w-60px d-block">{{ translate('Floor') }}</span>
        <span>:</span>
        <span class="fs-14 text-dark">#{{ $address['floor'] }}</span>
    </div>
    <div class="d-flex mb-1 gap-2">
        <span class="label min-w-60px fs-13 w-60px d-block">{{ translate('House') }}</span>
        <span>:</span>
        <span class="fs-14 text-dark">#{{ $address['house'] }}</span>
    </div>
    <div class="d-flex mb-1 gap-2">
        <span class="label min-w-60px fs-13 w-60px d-block">{{ translate('Road') }}</span>
        <span>:</span>
        <span class="fs-14 text-dark">#{{ $address['road'] }}</span>
    </div>
    <div class="location border-top pt-1 mt-3">
        <i class="tio-poi text-dark"></i>
        <span class="fs-13 text-title">
            {{ $address['address'] }}
        </span>
    </div>

@endif
