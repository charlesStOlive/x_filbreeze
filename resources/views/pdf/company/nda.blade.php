@extends('pdf.layouts.main')

@section('title', 'Accord de confidentialité – ' . $company->title)

@section('content')
    <div class="font-sans text-sm text-zinc-800 leading-relaxed space-y-6">
        <h1 class="text-2xl font-bold uppercase border-b pb-2">Accord de confidentialité (NDA)</h1>
        <p class="italic">À l’initiative du Prestataire</p>

        <h2 class="text-lg font-semibold mt-6">Entre :</h2>
        <p>
            <strong>{{ $company->title ?? 'INC' }}</strong>,<br>
            Société [forme juridique] – immatriculée au RCS de {{ $company->city ?? 'INC' }} sous le numéro {{ $company->siret ?? 'INC' }},<br>
            dont le siège social est situé {{ $company->address ?? 'INC' }}, {{ $company->cp ?? '' }} {{ $company->city ?? '' }}.<br>
            Représentée par [Nom du représentant], en qualité de [fonction].<br>
            <em>Ci-après désignée “le Client”</em>
        </p>

        <h2 class="text-lg font-semibold mt-6">Et :</h2>
        <p>
            <strong>Notilac</strong>,<br>
            Freelance immatriculé sous le numéro SIRET XXXX, dont l’adresse est : chemin de la passerelle, 69160 Tassin.<br>
            <em>Ci-après désigné “le Prestataire”</em>
        </p>

        <h2 class="text-lg font-semibold mt-6">1. Objet</h2>
        <p>
            Dans le cadre de la mission confiée par le Client au Prestataire, ce dernier sera amené à accéder à des informations, systèmes, fichiers, codes, données techniques, documents ou éléments d’infrastructure informatique sensibles.<br>
            Le présent accord a pour objet de définir les engagements du Prestataire concernant la confidentialité, la non-divulgation et la sécurité des données et accès fournis par le Client.
        </p>

        <h2 class="text-lg font-semibold mt-6">2. Engagements du Prestataire</h2>
        <ul class="list-disc pl-6 space-y-2">
            <li>Ne divulguer aucune information confidentielle ou sensible relative au Client, à ses activités, ses outils, ses méthodes, ou ses données, même après la fin de la mission ;</li>
            <li>Protéger et sécuriser tous les accès (identifiants, mots de passe, tokens, clés API, etc.) qui lui sont confiés ;</li>
            <li>N’utiliser ces accès que dans le cadre strict du projet et ne jamais les partager avec un tiers, même en sous-traitance, sans accord écrit du Client ;</li>
            <li>Respecter l’intégrité des systèmes du Client, sans altérer, copier ou réutiliser les éléments (code source, bases de données, fichiers, configurations, etc.) à d'autres fins que celles prévues contractuellement ;</li>
            <li>Restituer ou supprimer toute donnée ou accès confidentiel sur simple demande du Client ou à la fin du projet.</li>
        </ul>

        <h2 class="text-lg font-semibold mt-6">3. Nature des informations concernées</h2>
        <p>Sont notamment considérées comme confidentielles, sans que cette liste soit limitative :</p>
        <ul class="list-disc pl-6 space-y-2">
            <li>les accès aux serveurs, hébergements, boîtes mail, outils tiers ;</li>
            <li>les données personnelles ou professionnelles des collaborateurs, clients ou partenaires du Client ;</li>
            <li>le code source, scripts, configurations ou toute ressource logicielle liée au projet ;</li>
            <li>les documents stratégiques, commerciaux ou organisationnels.</li>
        </ul>

        <h2 class="text-lg font-semibold mt-6">4. Sécurité et stockage</h2>
        <ul class="list-disc pl-6 space-y-2">
            <li>Utiliser des moyens raisonnables de protection (mots de passe forts, double authentification, stockage chiffré, etc.) ;</li>
            <li>Ne pas stocker durablement d’informations sensibles sur des supports non sécurisés ou personnels ;</li>
            <li>Ne pas utiliser d’outils tiers non validés (stockage cloud, transferts de fichiers) sans autorisation explicite du Client.</li>
        </ul>

        <h2 class="text-lg font-semibold mt-6">5. Durée de l’engagement</h2>
        <p>
            Le présent engagement de confidentialité est valable pendant toute la durée du projet, et se prolonge pour une durée de 3 ans après sa fin, quelle qu’en soit la cause.
        </p>

        <h2 class="text-lg font-semibold mt-6">6. Droit applicable</h2>
        <p>
            Le présent accord est régi par le droit français. Tout litige relatif à son interprétation ou à son exécution sera soumis aux tribunaux compétents du ressort de {{ $company->city ?? 'INC' }}.
        </p>

        <div class="mt-12">
            <p>Fait à {{ $company->city ?? 'INC' }}, le {{ now()->format('d/m/Y') }}</p>
        </div>

        <div class="grid grid-cols-2 gap-8 mt-10 text-sm">
            <div>
                <h3 class="font-semibold uppercase">Le Client</h3>
                <p class="mt-2">Nom : ..............................................</p>
                <p>Fonction : .........................................</p>
                <p class="mt-6">Signature :</p>
            </div>

            <div>
                <h3 class="font-semibold uppercase">Le Prestataire</h3>
                <p class="mt-2">Nom : Notilac</p>
                <p>Fonction : Freelance</p>
                <p class="mt-6">Signature :</p>
            </div>
        </div>
    </div>
@endsection
