@props(['rating' => 0.0, 'size' => '16'])

<span class="inline-flex items-center" aria-label="{{ number_format($rating, 1) }} out of 5">
    @for ($i = 1; $i <= 5; $i++)
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="{{ $i <= round($rating) ? '#FAC902' : '#E6E6E1' }}" style="width: {{ $size }}px; height: {{ $size }}px;">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.368 2.447a1 1 0 00-.363 1.118l1.287 3.957c.3.922-.755 1.688-1.538 1.118l-3.367-2.447a1 1 0 00-1.176 0l-3.367 2.447c-.783.57-1.838-.196-1.538-1.118l1.287-3.957a1 1 0 00-.363-1.118L2.062 9.385c-.783-.57-.38-1.81.588-1.81h4.162a1 1 0 00.95-.69l1.287-3.958z" />
        </svg>
    @endfor
    <span class="ml-4 text-xs font-medium text-sokoni-black/70">{{ number_format($rating, 1) }}</span>
</span>
