@props(['name', 'value' => '', 'placeholder' => '', 'required' => false])
@php
    $rteId = 'rte_' . preg_replace('/[^a-z0-9]/i', '_', $name) . '_' . \Illuminate\Support\Str::random(6);
@endphp

{{-- Quill mounts on this div; the paired hidden textarea is what actually POSTs. --}}
<div class="js-rich-editor border border-slate-300 rounded-lg overflow-hidden bg-white focus-within:ring-2 focus-within:ring-indigo-500"
     data-target="{{ $rteId }}"
     data-placeholder="{{ $placeholder }}"></div>
<textarea name="{{ $name }}" id="{{ $rteId }}" class="hidden" @if($required) data-required="1" @endif>{{ $value }}</textarea>

@once
@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<style>
    .js-rich-editor .ql-toolbar.ql-snow { border: none; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 1; background: #fff; }
    .js-rich-editor .ql-container.ql-snow { border: none; font-size: .875rem; }
    /* Cap the editor height so long content scrolls inside the box instead of
       growing the page and pushing the form's Save button below the fold. */
    .js-rich-editor .ql-editor { min-height: 150px; max-height: 360px; overflow-y: auto; }
    .js-rich-editor .ql-editor.ql-blank::before { font-style: normal; color: #94a3b8; }
</style>
@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script>
(function () {
    // Same toolbar the email composer ships, applied to every detailed field.
    var TOOLBAR = [
        [{ header: [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ color: [] }, { background: [] }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['blockquote', 'code-block', 'link'],
        [{ align: [] }],
        ['clean'],
    ];

    function showPlainFallback(holder, textarea) {
        holder.style.display = 'none';
        textarea.classList.remove('hidden');
        textarea.classList.add('w-full', 'px-3', 'py-2', 'border', 'border-slate-300', 'rounded-lg', 'text-sm', 'min-h-[160px]');
        if (textarea.dataset.required) textarea.required = true;
    }

    // Copy each booted editor's HTML back into its hidden textarea on submit.
    function bindFormSync(form) {
        if (form.dataset.rteSyncBound) return;
        form.dataset.rteSyncBound = '1';
        form.addEventListener('submit', function () {
            form.querySelectorAll('.js-rich-editor').forEach(function (h) {
                if (!h.__quill) return;
                var ta = document.getElementById(h.dataset.target);
                if (!ta) return;
                var html = h.__quill.root.innerHTML;
                ta.value = (html === '<p><br></p>') ? '' : html;
            });
        });
    }

    function bootOne(holder) {
        if (holder.dataset.rteBooted) return;
        var textarea = document.getElementById(holder.dataset.target);
        if (!textarea) return;
        holder.dataset.rteBooted = '1';

        var quill;
        try {
            quill = new window.Quill(holder, {
                theme: 'snow',
                placeholder: holder.dataset.placeholder || '',
                modules: { toolbar: TOOLBAR },
            });
        } catch (e) {
            console.error('Quill init failed:', e);
            showPlainFallback(holder, textarea);
            return;
        }

        var initial = textarea.value || '';
        if (initial.trim()) {
            try {
                quill.clipboard.dangerouslyPasteHTML(initial);
            } catch (e) {
                console.error('Quill paste failed:', e);
            }
        }
        holder.__quill = quill;

        var form = textarea.closest('form');
        if (form) bindFormSync(form);
    }

    var attempts = 0;
    function ready() {
        if (typeof window.Quill === 'undefined') {
            // CDN can lag behind page load; retry briefly, then degrade gracefully.
            if (++attempts >= 80) {
                document.querySelectorAll('.js-rich-editor').forEach(function (h) {
                    var ta = document.getElementById(h.dataset.target);
                    if (ta) showPlainFallback(h, ta);
                });
                return;
            }
            setTimeout(ready, 50);
            return;
        }
        document.querySelectorAll('.js-rich-editor').forEach(bootOne);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ready);
    } else {
        ready();
    }
})();
</script>
@endpush
@endonce
