@props(['value' => '', 'class' => ''])
@php
    $rendered = \App\Support\RichText::toHtml($value);
@endphp
@if(filled($rendered))
    @once
        @push('styles')
        <style>
            .rich-content{line-height:1.55;word-wrap:break-word}
            .rich-content h1{font-size:1.05rem;font-weight:700;color:#1e293b;margin:.7rem 0 .35rem}
            .rich-content h2{font-size:1rem;font-weight:700;color:#1e293b;margin:.7rem 0 .35rem}
            .rich-content h3{font-size:.95rem;font-weight:600;color:#334155;margin:.55rem 0 .25rem}
            .rich-content p{margin:.4rem 0}
            .rich-content ul{list-style:disc;margin:.4rem 0 .4rem 1.25rem}
            .rich-content ol{list-style:decimal;margin:.4rem 0 .4rem 1.25rem}
            .rich-content li{margin:.15rem 0}
            .rich-content strong{color:#1e293b;font-weight:600}
            .rich-content a{color:#4f46e5;text-decoration:underline;word-break:break-all}
            .rich-content code{background:#f1f5f9;padding:.05rem .3rem;border-radius:.25rem;font-size:.85em}
            .rich-content pre{background:#f1f5f9;padding:.6rem .8rem;border-radius:.4rem;overflow:auto;font-size:.85em}
            .rich-content blockquote{border-left:3px solid #e2e8f0;padding-left:.75rem;color:#475569;margin:.5rem 0}
            .rich-content hr{margin:.6rem 0;border-color:#e2e8f0}
            .rich-content .ql-align-center{text-align:center}
            .rich-content .ql-align-right{text-align:right}
            .rich-content .ql-align-justify{text-align:justify}
        </style>
        @endpush
    @endonce
    <div class="rich-content {{ $class }}">{!! $rendered !!}</div>
@endif
