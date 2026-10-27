@props(['url'])
{{-- Matches the sign-in pages: crest over the app name in UPHSL navy. --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/uphsl-logo-email.png') }}" class="logo" width="60" height="80" alt="University of Perpetual Help System">
<span class="brand">{!! $slot !!}</span>
</a>
</td>
</tr>
