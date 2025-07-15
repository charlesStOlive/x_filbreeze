@if (filled($html))
    <div style="max-height: 600px; overflow-y: auto; border: 1px solid #ccc; padding: 1rem; background: #fff;">
        {!! $html !!}
    </div>
@else
    <p style="color: #666; font-style: italic;">Aucun contenu HTML à afficher</p>
@endif
