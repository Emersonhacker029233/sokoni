@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md px-16 py-32 lg:px-24">
    @include('web.account.nav')

    <h1 class="text-xl font-bold">{{ __('site.account_settings') }}</h1>

    @if (session('status'))
        <p class="mt-16 rounded-chip bg-sokoni-success/10 p-12 text-sm text-sokoni-success">{{ session('status') }}</p>
    @endif

    <form action="{{ route('web.account.settings.update') }}" method="post" class="mt-24 space-y-16">
        @csrf
        <div>
            <label for="name" class="text-sm font-medium">{{ __('site.auth_name_label') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required class="input-field mt-4">
            @error('name') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="email" class="text-sm font-medium">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="input-field mt-4">
            @error('email') <p class="mt-4 text-xs text-sokoni-danger">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="locale" class="text-sm font-medium">Language</label>
            <select id="locale" name="locale" class="input-field mt-4">
                <option value="en" @selected($user->locale === 'en')>English</option>
                <option value="sw" @selected($user->locale === 'sw')>Kiswahili</option>
            </select>
        </div>
        <button type="submit" class="btn-primary w-full py-12">Save changes</button>
    </form>
</div>
@endsection
