<style>
    .xterm-helpers { position: absolute !important; overflow: hidden !important; width: 0 !important; height: 0 !important; }
    .xterm-viewport { display: none !important; }
</style>
<twowee-terminal
    ws="{{ $wsUrl }}"
    server="{{ $server }}"
    @if($onexit) onexit="{{ $onexit }}" @endif
    style="display: block; width: {{ $width }}; height: {{ $height }}; position: fixed; top: 0; left: 0;"
></twowee-terminal>
<script src="{{ $scriptUrl }}" defer></script>
