@extends('layouts.blank')

@section('content')
    <div class="container">
        <div class="row mt-5">
            <div class="col-2"></div>
            <div class="col-md-8">
                @if(session()->has('error'))
                    <div class="alert alert-danger" role="alert">
                        {{session('error')}}
                    </div>
                @endif
                <div class="mar-ver pad-btm text-center">
                    <h1 class="h3 text-white">{{ translate('Lahab Software Update') }}</h1>
                    @if(env('SOFTWARE_VERSION') == 11.0)
                        <div class="alert alert-danger px-1 mt-2" role="alert">
                            {{ translate('Important Notice: We’ve upgraded the Firebase push notification system to a new and improved version as the old one will be phased out by June 2024.
                                Make sure your system is up-to-date to keep getting all the notification seamlessly please do check the Notification settings in the Admin panel.
                                Thanks for staying connected!.')  }}
                        </div>
                    @endif
                </div>
                <div class="card mt-3">
                    <div class="card-body">
                        <form method="POST" action="{{route('update-system')}}">
                            @csrf
                            <p class="text-center text-muted mb-4">
                                {{ translate('This will run pending database migrations and apply any post-update patches.') }}
                            </p>
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-info">{{ translate('Run Update') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-2"></div>
        </div>
    </div>
@endsection
