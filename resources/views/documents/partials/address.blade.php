@if ($address)
    <div>{{ trim(($address['first_name'] ?? '').' '.($address['last_name'] ?? '')) }}</div>
    @if (! empty($address['company']))<div>{{ $address['company'] }}</div>@endif
    <div>{{ $address['line1'] ?? '' }}</div>
    @if (! empty($address['line2']))<div>{{ $address['line2'] }}</div>@endif
    <div>{{ trim(($address['postal_code'] ?? '').' '.($address['city'] ?? '')) }}@if (! empty($address['region'])), {{ $address['region'] }}@endif</div>
    <div>{{ $address['country_name'] ?? ($address['country_code'] ?? '') }}</div>
    @if (! empty($address['phone']))<div class="muted">{{ $address['phone'] }}</div>@endif
@endif
