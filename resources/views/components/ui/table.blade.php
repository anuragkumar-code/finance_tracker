@props(['head' => null])

<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'w-full text-sm']) }}>
        {{ $slot }}
    </table>
</div>
