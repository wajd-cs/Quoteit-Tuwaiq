@props(['mine' => false, 'time' => ''])

<div @class(['bubble', 'bubble-mine' => $mine]) dir="auto">
    <span class="whitespace-pre-line">{{ $slot }}</span>
    <span class="bubble-time">{{ $time }}</span>
</div>
