@props(['flush' => false])

<div {{ $attributes->merge(['class' => $flush ? '' : 'px-5 py-4']) }}>
    {{ $slot }}
</div>
