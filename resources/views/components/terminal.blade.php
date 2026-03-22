<style>
    .xterm-helpers { position: absolute !important; overflow: hidden !important; width: 0 !important; height: 0 !important; }
    .xterm-viewport { display: none !important; }
    .twowee-small-screen {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: #0d1117;
        color: #8b949e;
        font-family: monospace;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 2rem;
        box-sizing: border-box;
    }
    @media (max-width: 768px) {
        .twowee-terminal-wrap { display: none !important; }
        .twowee-small-screen { display: flex !important; }
    }
</style>
<div class="twowee-terminal-wrap">
    <twowee-terminal
        ws="{{ $wsUrl }}"
        server="{{ $server }}"
        @if($onexit) onexit="{{ $onexit }}" @endif
        style="display: block; width: {{ $width }}; height: {{ $height }}; position: fixed; top: 0; left: 0;"
    ></twowee-terminal>
    <script src="{{ $scriptUrl }}" defer></script>
</div>
<div class="twowee-small-screen">
    <div>
        <p style="font-size: 1.2rem; color: #cdd9e5; margin-bottom: 1rem;">This application requires a larger screen.</p>
        <p>Please open it on a desktop or laptop computer.</p>
    </div>
</div>
