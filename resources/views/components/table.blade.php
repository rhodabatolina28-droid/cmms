@props(['id' => '', 'headers' => []])

<table {{ $attributes->merge(['class' => 'gov-table-premium']) }} @if($id) id="{{ $id }}" @endif>
    <thead>
        <tr>
            @foreach($headers as $header)
                <th>{{ $header }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        {{ $slot }}
    </tbody>
</table>
