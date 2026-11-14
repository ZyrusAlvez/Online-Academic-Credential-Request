@props(['href', 'name', 'size', 'type', 'download' => null])

{{-- A file on a summary: its name, a link that opens it in the file viewer, then its size
     (REQ-15-05, 17). --}}
<span {{ $attributes->class('break-all') }}><x-file-link :href="$href" :download="$download" :name="$name" :type="$type">{{ $name }}</x-file-link> ({{ $size }})</span>
