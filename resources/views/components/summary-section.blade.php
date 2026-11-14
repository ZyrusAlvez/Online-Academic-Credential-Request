@props(['heading', 'rows' => [], 'level' => 3])

{{-- A summary section: its heading (an h2 or h3, under the page's "Summary"), then one row per
     entry, label on the left and value on the right (REQ-15-17). A value is a string or a list of
     lines, one per line; a line is a string or a file {name, size, href, type}, its name a link to
     the file viewer. An App\Support\EmptyValue ("Not provided") is grey and italic, so it never
     looks like an entry (REQ-15-19). The slot adds <x-summary-row>s of its own. --}}
@php($headingId = 'summary-'.\Illuminate\Support\Str::slug($heading))
@php($tag = (int) $level === 2 ? 'h2' : 'h3')
<section aria-labelledby="{{ $headingId }}" {{ $attributes->class('rounded-xl border border-secondary/50 p-5 sm:p-6') }}>
    <{{ $tag }} id="{{ $headingId }}" class="text-lg font-semibold text-primary">{{ $heading }}</{{ $tag }}>

    <dl class="mt-4 divide-y divide-zinc-200 text-sm">
        @foreach ($rows as $label => $value)
            <x-summary-row :label="$label">
                @if (is_array($value))
                    <ul role="list" class="space-y-1">
                        @foreach ($value as $line)
                            @if (is_array($line))
                                <li><x-summary-file :href="$line['href']" :download="$line['download'] ?? null" :name="$line['name']" :size="$line['size']" :type="$line['type']" /></li>
                            @else
                                <li>{{ $line }}</li>
                            @endif
                        @endforeach
                    </ul>
                @elseif ($value instanceof \App\Support\EmptyValue)
                    {{-- Nothing was entered: say so in grey italics. --}}
                    <span class="font-normal text-zinc-600 italic" data-empty-value>{{ $value }}</span>
                @else
                    {{ $value }}
                @endif
            </x-summary-row>
        @endforeach
        {{ $slot }}
    </dl>
</section>
