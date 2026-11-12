<div class="doc-header">
    <table class="grid"><tr>
        <td>
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt="">
            @else
                <span class="store">{{ $seller['name'] }}</span>
            @endif
        </td>
        <td style="text-align: right;">
            <h1>{{ $title }}</h1>
            <div class="muted">{{ $reference }} · {{ $date }}</div>
        </td>
    </tr></table>
</div>
