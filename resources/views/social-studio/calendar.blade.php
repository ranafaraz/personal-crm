@extends('layouts.app')
@section('title', 'Content Calendar')

@push('styles')
<style>
.cal-day { min-height: 80px; }
</style>
@endpush

@section('content')
<div class="p-6 space-y-5" x-data="calendar({{ json_encode($scheduled) }})">

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-800">Content Calendar</h1>
        <div class="flex items-center gap-2">
            <button @click="prevMonth()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <span class="text-sm font-semibold text-slate-700 w-32 text-center" x-text="monthLabel"></span>
            <button @click="nextMonth()" class="p-2 rounded-lg hover:bg-slate-100 text-slate-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        {{-- Day headers --}}
        <div class="grid grid-cols-7 border-b border-slate-200">
            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day)
            <div class="text-center text-xs font-semibold text-slate-500 py-2">{{ $day }}</div>
            @endforeach
        </div>
        {{-- Calendar grid --}}
        <div class="grid grid-cols-7">
            <template x-for="(day, idx) in calDays" :key="idx">
                <div class="cal-day border-b border-r border-slate-100 p-1.5"
                     :class="day.isToday ? 'bg-indigo-50' : (day.isCurrentMonth ? 'bg-white' : 'bg-slate-50')">
                    <p class="text-xs font-semibold mb-1"
                       :class="day.isToday ? 'text-indigo-600' : (day.isCurrentMonth ? 'text-slate-700' : 'text-slate-300')"
                       x-text="day.date"></p>
                    <template x-for="post in day.posts" :key="post.id">
                        <a :href="post.url"
                           class="block text-[10px] font-medium px-1 py-0.5 rounded mb-0.5 truncate"
                           :class="{
                               'bg-green-100 text-green-700': post.status === 'published',
                               'bg-indigo-100 text-indigo-700': post.status === 'scheduled',
                               'bg-red-100 text-red-700': post.status === 'failed',
                               'bg-blue-100 text-blue-700': post.status === 'approved',
                           }"
                           :title="post.title"
                           x-text="post.title">
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex gap-4 text-xs">
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-indigo-100"></span>Scheduled</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-blue-100"></span>Approved</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-green-100"></span>Published</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-sm bg-red-100"></span>Failed</span>
    </div>

</div>

@push('scripts')
<script>
function calendar(posts) {
    return {
        today: new Date(),
        current: new Date(),
        posts,
        get monthLabel() {
            return this.current.toLocaleString('default', { month: 'long', year: 'numeric' });
        },
        prevMonth() { this.current = new Date(this.current.getFullYear(), this.current.getMonth() - 1, 1); },
        nextMonth() { this.current = new Date(this.current.getFullYear(), this.current.getMonth() + 1, 1); },
        get calDays() {
            const year  = this.current.getFullYear();
            const month = this.current.getMonth();
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const days = [];

            // Padding before first day
            const prevDays = new Date(year, month, 0).getDate();
            for (let i = firstDay - 1; i >= 0; i--) {
                days.push({ date: prevDays - i, isCurrentMonth: false, isToday: false, posts: [] });
            }

            for (let d = 1; d <= daysInMonth; d++) {
                const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
                const todayStr = `${this.today.getFullYear()}-${String(this.today.getMonth()+1).padStart(2,'0')}-${String(this.today.getDate()).padStart(2,'0')}`;
                const dayPosts = this.posts.filter(p => p.scheduled_at && p.scheduled_at.startsWith(dateStr));
                days.push({ date: d, isCurrentMonth: true, isToday: dateStr === todayStr, posts: dayPosts });
            }

            // Padding after
            const remaining = 42 - days.length;
            for (let d = 1; d <= remaining; d++) {
                days.push({ date: d, isCurrentMonth: false, isToday: false, posts: [] });
            }

            return days;
        }
    };
}
</script>
@endpush
@endsection
