@if (filled($html))
    <div style="max-width: 600px; max-height: 500px; overflow-y: auto; border: 1px solid #ccc; padding: 1rem; background: #fff;">
        {!! $html !!}
    </div>
@else
    <p style="color: #666; font-style: italic;">Aucun contenu HTML à afficher</p>
@endif
