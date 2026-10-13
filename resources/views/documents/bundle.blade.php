@extends('documents.layout')

{{-- Several documents in one PDF: each starts on a new page with its own header. --}}
@section('content')
@foreach ($docs as $doc)
<div @class(['page-break' => ! $loop->last])>
    @include('documents.partials.header', $doc['data'])
    @include($doc['body'], $doc['data'])
</div>
@endforeach
@endsection
