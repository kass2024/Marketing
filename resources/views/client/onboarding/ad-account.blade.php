<x-client.onboarding.layout>

<h2 class="text-2xl font-bold mb-6">
Select Ad Account
</h2>

<form method="POST" action="{{ route('onboarding.adaccount.store') }}">
@csrf

<select name="ad_account_id" class="w-full border rounded p-3">

<option value="act_123">Ad Account 1</option>
<option value="act_456">Ad Account 2</option>

</select>

<button class="mt-6 bg-green-600 text-white px-6 py-3 rounded-lg">
Continue
</button>

</form>

</x-client.onboarding.layout>