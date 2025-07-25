@if (filled($html))
    <div class="reset-tailwind" style="width: 600px; max-height: 700px; overflow-y: auto; border: 1px solid #ccc; padding: 1rem; background: #fff; ">
        {!! $html !!}
    </div>
@else
    <p style="color: #666; font-style: italic;">Aucun contenu HTML à afficher</p>
@endif
