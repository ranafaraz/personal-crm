@extends('layouts.app')
@section('title', 'Email Templates')
@section('page-title', 'Email Templates')
@section('content')
<div>
    <div class="flex items-center justify-between mb-5">
        <p class="text-sm text-slate-500">{{ $templates->total() }} templates</p>
        <a href="{{ route('email-templates.create') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-4 py-2 rounded-lg flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Template
        </a>
    </div>
    @if($templates->isEmpty())
        <div class="bg-white border border-slate-200 rounded-xl text-center py-16">
            <p class="text-slate-500 font-medium">No email templates yet.</p>
            <a href="{{ route('email-templates.create') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:underline">Create your first template</a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($templates as $template)
            @php $typeColors = ['initial_outreach'=>'blue','follow_up'=>'orange','thank_you'=>'green','networking'=>'purple','other'=>'slate']; $tc = $typeColors[$template->type] ?? 'slate'; @endphp
            <div class="bg-white border border-slate-200 rounded-xl p-5 flex flex-col gap-3 hover:shadow-sm transition-shadow">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $tc }}-100 text-{{ $tc }}-700 mb-2 inline-block">{{ ucwords(str_replace('_',' ',$template->type)) }}</span>
                        <h3 class="text-sm font-semibold text-slate-800">{{ $template->name }}</h3>
                        <p class="text-xs text-slate-500 mt-1 truncate">{{ $template->subject }}</p>
                    </div>
                    <span class="text-xs text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full flex-shrink-0">Used {{ $template->times_used }}x</span>
                </div>
                <p class="text-xs text-slate-600 line-clamp-3 leading-relaxed">{{ Str::limit(strip_tags($template->body), 150) }}</p>
                @if($template->variables && count($template->variables) > 0)
                    @php $variables = array_slice((array) $template->variables, 0, 5); @endphp
                    <div class="flex flex-wrap gap-1">
                        @foreach($variables as $var)
                            <span class="text-xs bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded font-mono">{{ '{' . '{' . $var . '}' . '}' }}</span>
                        @endforeach
                    </div>
                @endif
                <div class="flex gap-2 pt-1 border-t border-slate-100">
                    <a href="{{ route('email-templates.edit', $template) }}" class="text-xs text-slate-600 hover:text-indigo-600 px-2 py-1 rounded hover:bg-slate-100 flex-1 text-center">Edit</a>
                    <form method="POST" action="{{ route('email-templates.duplicate', $template) }}">
                        @csrf
                        <button type="submit" class="text-xs text-slate-600 hover:text-indigo-600 px-2 py-1 rounded hover:bg-slate-100">Duplicate</button>
                    </form>
                    <form method="POST" action="{{ route('email-templates.destroy', $template) }}" onsubmit="return confirm('Delete this template?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50">Delete</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @if($templates->hasPages())
            <div class="mt-4">{{ $templates->links() }}</div>
        @endif
    @endif
</div>
@endsection
