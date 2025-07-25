<div>
    <h2>Sujet du mail</h2>

    <p>Bonjour,</p>

    @if ($options['show_intro'] ?? false)
        <p>
            Ceci est une introduction automatique générée par le système.
        </p>
    @endif

    @if ($options['show_details'] ?? false)
        <ul>
            <li>Client : {{ $company->title ?? '...' }}</li>
            <li>Slug : {{ $company->slug ?? '...' }}</li>
            <li>Date : {{ $company->created_at?->format('d/m/Y') ?? '...' }}</li>
            
        </ul>
    @endif

    <p>
        Cordialement,<br>
        {{ $user?->name ?? config('app.name') }}
    </p>

    <div class="footer">
        Cet e-mail a été généré automatiquement. Merci de ne pas y répondre.
    </div>
</div>
