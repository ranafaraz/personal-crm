@extends('layouts.app')
@section('title', 'Inbox')
@section('page-title', 'Inbox')
@section('content')
<div>
    {{-- Filters --}}
    <div class="bg-white border border-slate-200 rounded-xl p-4 mb-5">
        <form method="GET" action="{{ route('inbox.index') }}" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="From, subject..." class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-44">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Review Status</label>
                <select name="review_status" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All</option>
                    <option value="pending" {{ request('review_status') === 'pending' ? 'selected' : '' }}>Pending Review</option>
                    <option value="reviewed" {{ request('review_status') === 'reviewed' ? 'selected' : '' }}>Reviewed</option>
                    <option value="ignored" {{ request('review_status') === 'ignored' ? 'selected' : '' }}>Ignored</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Sentiment</label>
                <select name="sentiment" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All</option>
                    @foreach(['positive','neutral','negative','unknown'] as $s)
                        <option value="{{ $s }}" {{ request('sentiment') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Email Account</label>
                <select name="email_account_id" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Accounts</option>
                    @foreach($emailAccounts as $account)
                        <option value="{{ $account->id }}" {{ request('email_account_id') == $account->id ? 'selected' : '' }}>{{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Label / Tag</label>
                <select name="tag" class="px-2.5 py-1.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Labels</option>
                    @foreach($tags as $tag)
                        <option value="{{ $tag->id }}" {{ request('tag') == $tag->id ? 'selected' : '' }}>{{ $tag->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg">Filter</button>
                <a href="{{ route('inbox.index') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium px-4 py-1.5 rounded-lg">Reset</a>
            </div>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
        @if($messages->isEmpty())
            <div class="text-center py-16">
                <p class="text-slate-500 font-medium">No inbox messages yet.</p>
                <p class="text-sm text-slate-400 mt-1">Sync your email accounts to see replies.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">From</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Subject</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Labels</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Received</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Sentiment</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Matched Contact</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($messages as $msg)
                    @php
                        $sentimentColors = ['positive'=>'green','neutral'=>'slate','negative'=>'red','unknown'=>'gray'];
                        $sc = $sentimentColors[$msg->sentiment] ?? 'gray';
                    @endphp
                    <tr class="hover:bg-slate-50 {{ !$msg->is_read ? 'font-semibold' : '' }}">
                        <td class="px-4 py-3">
                            <p class="text-slate-800 truncate max-w-[160px]">{{ $msg->from_name ?? $msg->from_email }}</p>
                            <p class="text-xs text-slate-400">{{ $msg->from_email }}</p>
                        </td>
                        <td class="px-4 py-3 max-w-xs truncate text-slate-700">{{ $msg->subject ?? '(no subject)' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1" x-data="inboxTagger({{ $msg->id }}, {{ $msg->tags->toJson() }}, {{ $tags->toJson() }})">
                                <template x-for="tag in msgTags" :key="tag.id">
                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-xs bg-indigo-100 text-indigo-700">
                                        <span x-text="tag.name"></span>
                                        <button type="button" @click="detachTag(tag)" class="hover:text-red-600 leading-none">&times;</button>
                                    </span>
                                </template>
                                <div class="relative">
                                    <button type="button" @click.stop="open=!open" class="text-xs text-slate-400 hover:text-indigo-600 px-1.5 py-0.5 rounded hover:bg-slate-100">+ label</button>
                                    <div x-show="open" @click.outside="open=false" class="absolute left-0 top-6 z-30 bg-white border border-slate-200 rounded-lg shadow-lg min-w-[160px] max-h-48 overflow-y-auto text-sm">
                                        <template x-for="tag in availableTags" :key="tag.id">
                                            <div @click="attachTag(tag)" class="px-3 py-1.5 hover:bg-indigo-50 cursor-pointer" x-text="tag.name"></div>
                                        </template>
                                        <div x-show="availableTags.length === 0" class="px-3 py-2 text-slate-400 text-xs">All labels applied</div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">{{ $msg->received_at->format('M j, g:i A') }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $sc }}-100 text-{{ $sc }}-700">{{ ucfirst($msg->sentiment ?? 'unknown') }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if($msg->matchedContact)
                                <a href="{{ route('contacts.show', $msg->matchedContact) }}" class="text-indigo-600 hover:underline text-xs">{{ $msg->matchedContact->full_name }}</a>
                            @else <span class="text-slate-400 text-xs">—</span> @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($msg->review_status === 'pending')
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
                            @elseif($msg->review_status === 'reviewed')
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Reviewed</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Ignored</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right flex items-center justify-end gap-1">
                            <a href="{{ route('inbox.show', $msg) }}" class="text-xs text-indigo-600 hover:underline px-2 py-1 rounded hover:bg-slate-100">View</a>
                            @if($msg->review_status === 'pending')
                                <form method="POST" action="{{ route('inbox.review', $msg) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="review_status" value="reviewed">
                                    <button type="submit" class="text-xs text-green-600 hover:text-green-800 px-2 py-1 rounded hover:bg-green-50">Mark Reviewed</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if($messages->hasPages())
                <div class="px-4 py-3 border-t border-slate-200">{{ $messages->withQueryString()->links() }}</div>
            @endif
        @endif
    </div>
</div>

<script>
function inboxTagger(msgId, initialTags, allTags) {
    return {
        msgId,
        msgTags: initialTags,
        allTags,
        open: false,
        get availableTags() {
            return this.allTags.filter(t => !this.msgTags.find(m => m.id === t.id));
        },
        async attachTag(tag) {
            this.open = false;
            await fetch('{{ route("tags.attach") }}', {
                method: 'POST',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
                body: JSON.stringify({ tag_id: tag.id, taggable_type: 'inbox_message', taggable_id: this.msgId })
            });
            this.msgTags.push(tag);
        },
        async detachTag(tag) {
            await fetch('{{ route("tags.detach") }}', {
                method: 'POST',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
                body: JSON.stringify({ tag_id: tag.id, taggable_type: 'inbox_message', taggable_id: this.msgId })
            });
            this.msgTags = this.msgTags.filter(t => t.id !== tag.id);
        },
    };
}
</script>
@endsection
