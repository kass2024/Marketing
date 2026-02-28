<x-app-layout>

<div class="max-w-5xl mx-auto py-12 px-6">

    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-10">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-10">
            <div>
                <h2 class="text-3xl font-bold text-gray-900">
                    Master Meta Business Connection
                </h2>
                <p class="text-gray-500 mt-2">
                    Manage your platform-wide Meta integration
                </p>
            </div>

            @if(!empty($platformMeta))
                <span class="px-4 py-1 text-sm rounded-full bg-green-100 text-green-700 font-semibold">
                    Connected
                </span>
            @else
                <span class="px-4 py-1 text-sm rounded-full bg-gray-100 text-gray-600 font-semibold">
                    Not Connected
                </span>
            @endif
        </div>

        {{-- Flash Messages --}}
        @foreach (['success','error','info'] as $msg)
            @if(session($msg))
                <div class="mb-6 p-4 rounded-lg
                    @if($msg === 'success') bg-green-50 border-green-200 text-green-700
                    @elseif($msg === 'error') bg-red-50 border-red-200 text-red-700
                    @else bg-blue-50 border-blue-200 text-blue-700
                    @endif
                    border">
                    {{ session($msg) }}
                </div>
            @endif
        @endforeach


        {{-- ================= CONNECTED ================= --}}
        @if(!empty($platformMeta))

            <div class="space-y-4 text-lg text-gray-800">

                <div>
                    <span class="font-semibold">Business Name:</span>
                    {{ $platformMeta->business_name ?? 'N/A' }}
                </div>

                <div>
                    <span class="font-semibold">Business ID:</span>
                    {{ $platformMeta->business_id ?? 'N/A' }}
                </div>

                @if(!empty($platformMeta->token_expires_at))
                    <div>
                        <span class="font-semibold">Token Expires At:</span>
                        {{ \Carbon\Carbon::parse($platformMeta->token_expires_at)->format('d M Y H:i') }}
                    </div>
                @endif

            </div>

            {{-- Disconnect Button --}}
            <div class="mt-10">

                <form method="POST"
                      action="{{ route('admin.meta.disconnect') }}"
                      onsubmit="return confirm('Are you sure you want to disconnect Meta? This will remove the platform token.')">

                    @csrf

                    <button type="submit"
                        class="bg-red-600 text-white px-8 py-3 rounded-xl hover:bg-red-700 transition font-semibold shadow-md">
                        Disconnect Business
                    </button>

                </form>

            </div>

        {{-- ================= NOT CONNECTED ================= --}}
        @else

            <div class="text-gray-600 text-lg mb-8">
                No Meta Business connected yet.
            </div>

            <a href="{{ route('admin.meta.connect') }}"
               class="inline-block bg-blue-600 text-white px-8 py-3 rounded-xl hover:bg-blue-700 transition font-semibold shadow-md">
                Connect Meta Business
            </a>

        @endif

    </div>

</div>

</x-app-layout>