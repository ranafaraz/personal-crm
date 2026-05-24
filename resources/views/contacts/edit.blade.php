@extends('layouts.app')

@section('title', 'Edit Contact')
@section('page-title', 'Edit Contact')

@section('content')
<div class="max-w-2xl">
    <div class="mb-4">
        <a href="{{ route('contacts.show', $contact) }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Contact</a>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl p-6">
        <form method="POST" action="{{ route('contacts.update', $contact) }}" class="space-y-5">
            @csrf
            @method('PUT')

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">First Name</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $contact->first_name) }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $contact->last_name) }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $contact->email) }}" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $contact->phone) }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Company</label>
                    <input type="text" name="company" value="{{ old('company', $contact->company) }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Industry</label>
                    <input type="text" name="industry" value="{{ old('industry', $contact->industry) }}" list="industry-list" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. Technology, Finance...">
                    <datalist id="industry-list">
                        @foreach(['Technology','Finance','Healthcare','Education','Marketing','Legal','Real Estate','Manufacturing','Retail','Consulting','Media & Entertainment','Non-profit','Government','Transportation','Energy','Construction'] as $ind)
                            <option value="{{ $ind }}">
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Job Title</label>
                    <input type="text" name="job_title" value="{{ old('job_title', $contact->job_title) }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">LinkedIn URL</label>
                    <input type="url" name="linkedin_url" value="{{ old('linkedin_url', $contact->linkedin_url) }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Website</label>
                    <input type="url" name="website" value="{{ old('website', $contact->website) }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">City</label>
                    <input type="text" name="city" value="{{ old('city', $contact->city) }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Country</label>
                    <input type="text" name="country" value="{{ old('country', $contact->country) }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Source</label>
                    <input type="text" name="source" value="{{ old('source', $contact->source) }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                {{-- Tags picker with inline creation --}}
                <div class="md:col-span-2" x-data="tagPicker({{ $tags->toJson() }}, {{ $contact->tags->pluck('id')->toJson() }})">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tags / Labels</label>
                    <div class="border border-slate-300 rounded-lg p-2 min-h-[42px] flex flex-wrap gap-1.5 cursor-text" @click="$refs.tagInput.focus()">
                        <template x-for="tag in selected" :key="tag.id">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700">
                                <span x-text="tag.name"></span>
                                <input type="hidden" :name="'tags[]'" :value="tag.id">
                                <button type="button" @click.stop="removeTag(tag)" class="hover:text-red-600">&times;</button>
                            </span>
                        </template>
                        <input x-ref="tagInput" type="text" x-model="search" @input="filterTags()" @keydown.enter.prevent="confirmTag()" @keydown.backspace="backspaceTag()" placeholder="Add tag..." class="flex-1 min-w-[120px] outline-none text-sm px-1 py-0.5">
                    </div>
                    <div x-show="open && (filtered.length > 0 || search.trim().length > 0)" class="relative z-20">
                        <ul class="absolute top-1 left-0 right-0 bg-white border border-slate-200 rounded-lg shadow-lg max-h-48 overflow-y-auto text-sm">
                            <template x-for="tag in filtered" :key="tag.id">
                                <li @click="addTag(tag)" class="px-3 py-2 hover:bg-indigo-50 cursor-pointer flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-indigo-400 flex-shrink-0"></span>
                                    <span x-text="tag.name"></span>
                                </li>
                            </template>
                            <li x-show="search.trim().length > 0 && !exactMatch()" @click="createTag()" class="px-3 py-2 hover:bg-green-50 cursor-pointer text-green-700 font-medium flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Create "<span x-text="search.trim()"></span>"
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                    <textarea name="notes" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">{{ old('notes', $contact->notes) }}</textarea>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors">Update Contact</button>
                <a href="{{ route('contacts.show', $contact) }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-5 py-2 rounded-lg transition-colors">Cancel</a>
            </div>
        </form>
    </div>
</div>

@include('partials._tag-picker-script')
@endsection
