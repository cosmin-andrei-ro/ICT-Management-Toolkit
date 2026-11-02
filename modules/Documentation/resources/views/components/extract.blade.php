@props(['text'])
{{-- ts_headline marks matches with [[ ]]: Markdown signs are dropped, the text escaped, then the markers become <mark>. --}}
<p class="text-xs text-gray-600">{!! str_replace(['[[', ']]'], ['<mark>', '</mark>'], e(preg_replace(['/(?<!\[)[*_`#>|]+(?!\])/', '/\s+/'], ['', ' '], (string) $text))) !!}</p>
