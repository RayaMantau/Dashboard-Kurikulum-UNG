@props(['items' => []])

<nav class="shell-breadcrumbs" aria-label="Jejak navigasi">
    <ol>
        @foreach($items as $item)
            <li>
                @if(!$loop->first)<span aria-hidden="true" class="breadcrumb-separator">/</span>@endif
                @if(!$loop->last && isset($item['url']))
                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                @else
                    <span @if($loop->last) aria-current="page" @endif>{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
