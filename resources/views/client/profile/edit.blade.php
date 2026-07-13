@extends('layouts.admin')



@section('content')

<div class="mx-auto max-w-2xl space-y-6 px-4 py-10 sm:px-6">

    <div>

        <h1 class="text-2xl font-bold text-slate-900">Ad destinations</h1>

        <p class="mt-2 text-sm text-slate-600">

            Your Facebook Page and business WhatsApp number for click-to-WhatsApp ads.

            Publishing uses the platform Meta account — you do not need to connect Facebook yourself.

        </p>

    </div>



    @if($errors->any())

        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">

            <ul class="space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>

        </div>

    @endif



    <form method="POST" action="{{ route('client.profile.update') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">

        @csrf

        @method('PUT')



        <x-facebook-page-search

            :search-url="route('register.pages.search')"

            :initial-id="old('meta_page_id', $client->meta_page_id)"

            :initial-name="old('meta_page_name', $client->meta_page_name)"

            input-id="profile_meta_page_search"

            label="Facebook Page"

            placeholder="Type your Facebook Page name…"

        />



        <div>

            <label class="mb-1 block text-sm font-medium text-slate-700" for="whatsapp_phone_number">Business WhatsApp</label>

            <input id="whatsapp_phone_number" name="whatsapp_phone_number" type="text" required

                value="{{ old('whatsapp_phone_number', $client->whatsapp_phone_number) }}"

                class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm"

                placeholder="14385551234">

            <p class="mt-1 text-xs text-slate-500">

                @if($client->isWhatsAppVerified())

                    Verified on Meta as <strong>{{ $client->whatsapp_verified_name }}</strong>

                    @if($client->whatsapp_meta_synced_at)

                        (synced {{ $client->whatsapp_meta_synced_at->diffForHumans() }})

                    @endif

                @elseif($client->needsWhatsAppVerification())

                    Pending Meta verification — save to resend SMS or enter code below.

                @else

                    Saved with your registered business name on the platform WABA after SMS verification.

                @endif

            </p>

        </div>



        @if($client->needsWhatsAppVerification())

        <div>

            <label class="mb-1 block text-sm font-medium text-slate-700" for="whatsapp_verification_code">Meta SMS code (optional)</label>

            <input id="whatsapp_verification_code" name="whatsapp_verification_code" type="text" inputmode="numeric"

                value="{{ old('whatsapp_verification_code') }}"

                class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm"

                placeholder="Enter code if you already received it">

        </div>

        @endif



        <button type="submit" class="rounded-xl bg-xander-navy px-6 py-2.5 text-sm font-semibold text-white">Save</button>

    </form>

</div>

@endsection

