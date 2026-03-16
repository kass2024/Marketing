<x-client.onboarding.layout>

<h2 class="text-2xl font-bold mb-6">
Select Facebook Page
</h2>

<form method="POST" action="{{ route('onboarding.page.store') }}">
@csrf

<select name="page_id" class="w-full border rounded p-3">

<option value="123456">My Business Page</option>

</select>

<button class="mt-6 bg-green-600 text-white px-6 py-3 rounded-lg">
Continue
</button>

</form>

</x-client.onboarding.layout>