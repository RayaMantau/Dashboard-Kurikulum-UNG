@props(['items'])

<nav class="public-breadcrumbs" aria-label="Breadcrumb">
    <ol>
        @foreach($items as $item)
            <li>
                @if(!$loop->first)<span aria-hidden="true">/</span>@endif
                @if(isset($item['url']))
                    <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                @else
                    <span aria-current="page">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
