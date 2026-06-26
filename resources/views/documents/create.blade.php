@extends('layouts.app')
@section('title', 'Upload Document')
@section('page-title', 'Upload Document')
@section('breadcrumbs')
    <x-breadcrumbs :items="[
        ['label' => 'Documents', 'url' => route('documents.index')],
        ['label' => 'Upload Document'],
    ]" />
@endsection
@section('content')
<div class="max-w-xl">
    <div class="mb-4"><a href="{{ route('documents.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Documents</a></div>
    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Document Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. My Resume 2024">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Document Type</label>
                <select name="document_type" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    @foreach(['resume'=>'Resume','cover_letter'=>'Cover Letter','proposal'=>'Proposal','portfolio'=>'Portfolio','reference'=>'Reference','other'=>'Other'] as $val=>$label)
                        <option value="{{ $val }}" {{ old('document_type') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">File <span class="text-red-500">*</span></label>
                <input type="file" name="file" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <p class="text-xs text-slate-400 mt-1">Max 20MB. PDF, DOC, DOCX, TXT, images accepted.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Link to Opportunity (optional)</label>
                <select name="opportunity_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">None</option>
                    @foreach($opportunities ?? [] as $opp)
                        <option value="{{ $opp->id }}" {{ (old('opportunity_id') == $opp->id || request('opportunity_id') == $opp->id) ? 'selected' : '' }}>{{ Str::limit($opp->title, 60) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Description (optional)</label>
                <x-rich-editor name="description" :value="old('description')" placeholder="What is this document about?" />
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2 rounded-lg">Upload</button>
                <a href="{{ route('documents.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-5 py-2 rounded-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
