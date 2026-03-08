<x-app-layout>

<div class="max-w-3xl mx-auto p-6">

<h2 class="text-xl font-semibold mb-4">Bulk WhatsApp Message</h2>

<form method="POST" action="{{ route('admin.bulk.send') }}" enctype="multipart/form-data">
@csrf

<textarea
name="message"
rows="6"
placeholder="Hello {name}, we have an update..."
class="w-full border rounded-lg p-4"></textarea>

<p class="text-sm text-gray-500 mt-2">
Use <b>{name}</b> to personalize message.
</p>

<input type="file" name="attachment" class="mt-3">

<button
class="mt-4 bg-green-600 text-white px-6 py-3 rounded-lg">

Send Bulk Message

</button>

</form>

</div>

</x-app-layout>