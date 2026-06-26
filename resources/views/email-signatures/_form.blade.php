@php
    $isEdit = isset($signature);
@endphp

<div class="max-w-3xl">
    <div class="mb-4"><a href="{{ route('email-signatures.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Signatures</a></div>
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <form method="POST" action="{{ $isEdit ? route('email-signatures.update', $signature) : route('email-signatures.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Signature Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name', $signature->name ?? '') }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. Main work signature">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Signature Body</label>
                <x-rich-editor name="body" :value="old('body', $signature->body ?? '')" placeholder="Write your signature text here..." />
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Signature Image</label>
                @if($isEdit && $signature->image_url)
                    <div class="mb-3 flex items-center gap-3">
                        <img src="{{ $signature->image_url }}" alt="{{ $signature->name }}" class="max-h-16 max-w-40 object-contain rounded border border-slate-200">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remove_image" value="1" class="text-indigo-600 rounded">
                            Remove image
                        </label>
                    </div>
                @endif
                <input type="file" name="image" accept="image/*" class="block w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                <p class="text-xs text-slate-400 mt-1">PNG, JPG, GIF, or WebP up to 2 MB.</p>
            </div>

            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_default" value="1" {{ old('is_default', $signature->is_default ?? false) ? 'checked' : '' }} class="text-indigo-600 rounded">
                <span class="text-sm text-slate-700">Use as default signature</span>
            </label>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2 rounded-lg">Save Signature</button>
                <a href="{{ route('email-signatures.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-5 py-2 rounded-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>
