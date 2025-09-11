@if (filled($html))
    <div style="max-height: 600px; overflow-y: auto; border: 1px solid #ccc; padding: 1rem; background: #fff; position: relative;">
        @if(app()->environment('local'))
            <!-- Mode développement PDF détecté -->
            <div style="position: absolute; top: 4px; right: 4px; background: #10b981; color: white; padding: 2px 6px; font-size: 11px; border-radius: 3px;">
                PDF DEV
            </div>
        @endif
        {!! $html !!}
    </div>
@else
    <p style="color: #666; font-style: italic;">Aucun contenu HTML à afficher</p>
@endif
