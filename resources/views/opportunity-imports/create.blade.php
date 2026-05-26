@extends('layouts.app')
@section('title', 'Import Opportunities')
@section('page-title', 'Import Opportunities from CSV')
@section('content')
<div class="max-w-xl">
    <div class="mb-4"><a href="{{ route('opportunity-imports.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Back to Imports</a></div>
    <div class="bg-white border border-slate-200 rounded-xl p-6 space-y-5">
        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-sm text-blue-800">
            <p class="font-semibold mb-1">CSV Format</p>
            <p>Your CSV should include columns like: <code class="font-mono bg-blue-100 px-1 rounded">title</code>, <code class="font-mono bg-blue-100 px-1 rounded">type</code>, <code class="font-mono bg-blue-100 px-1 rounded">organization</code>, <code class="font-mono bg-blue-100 px-1 rounded">status</code>, <code class="font-mono bg-blue-100 px-1 rounded">priority</code>, <code class="font-mono bg-blue-100 px-1 rounded">deadline</code></p>
            <p class="mt-1">The <code class="font-mono bg-blue-100 px-1 rounded">title</code> column is required. Column headers are auto-detected.</p>
            <p class="mt-2"><strong>Link to contacts:</strong> add a <code class="font-mono bg-blue-100 px-1 rounded">contact_emails</code> column with one or more emails separated by <code>;</code>. Existing contacts are linked by email; any unknown email creates a stub contact (first_name from the local part, company from this opportunity's organization) and links it automatically. Email is the unique key per user.</p>
            <p class="mt-2"><strong>Pre-load emails:</strong> add a <code class="font-mono bg-blue-100 px-1 rounded">draft_email</code> column with the initial outreach body and one draft is created per linked contact in your <em>Drafts</em> tab. Add a <code class="font-mono bg-blue-100 px-1 rounded">followup_email</code> column and one is queued in <em>Scheduled</em> per contact for delivery 5 days later. Both use your default sending account.</p>
            <p class="mt-2 text-xs">Valid types: <strong>job, scholarship, research, grant, networking</strong></p>
            <p class="text-xs">Valid statuses: <strong>draft, active, waiting_reply, replied, interview, offer, rejected</strong></p>
            <p class="text-xs">Valid priorities: <strong>urgent, high, medium, low</strong></p>
        </div>
        <div>
            <a href="{{ route('opportunity-imports.template') }}" class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium border border-indigo-200 bg-indigo-50 hover:bg-indigo-100 px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download CSV Template
            </a>
        </div>
        <form method="POST" action="{{ route('opportunity-imports.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">CSV File <span class="text-red-500">*</span></label>
                <input type="file" name="csv_file" accept=".csv,text/csv" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <p class="text-xs text-slate-400 mt-1">Max 10MB. CSV format required.</p>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold px-5 py-2 rounded-lg">Upload & Import</button>
                <a href="{{ route('opportunity-imports.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-5 py-2 rounded-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
