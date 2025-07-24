@extends('pdf.layouts.main')

@section('title', 'Accord de confidentialité – ' . $company->title)

@section('content')
    <div class="pt-4 pb-8 -ml-2">
        <img class="object-cover h-16" src="{{ asset('images/logo.png') }}" />
    </div>

    <div
        class="prose prose-sm prose-zinc 
            prose-headings:mt-4 prose-headings:mb-1
            prose-p:my-1 prose-ul:my-1
            prose-li:my-0 prose-li:mb-1
            font-sans text-zinc-800
            leading-snug
             max-w-none">
        <h1 class="text-2xl font-bold uppercase border-b pb-2">Accord de confidentialité</h1>
        <p class="italic">À l’initiative du Prestataire</p>

        <h2>Entre :</h2>
        <p>
            <strong>{{ $company->title ?? 'INC' }}</strong>, {{ $company?->type->label() }} – immatriculée au RCS de
            {{ $company->city ?? 'INC' }} sous le numéro
            {{ $company->siret ?? 'INC' }},
            dont le siège social est situé {{ $company->address ?? 'INC' }},
            {{ $company->cp ?? '' }}
            {{ $company->city ?? '' }}.
            Représentée par [Nom du représentant].<br>
            <em>Ci-après désignée “le Client”</em>
        </p>

        <h2>Et :</h2>
        <p>
            <strong>Notilac Charles Saint Olive</strong>,
            Freelance immatriculé sous le numéro SIRET 95388940900017, dont l’adresse est : 12 chemin de la passerelle,
            69160
            Tassin.<br>
            <em>Ci-après désigné “le Prestataire”</em>
        </p>

        <h2>1. Objet</h2>
        <p>
            Dans le cadre de la mission confiée par le Client au Prestataire, ce dernier sera amené à accéder à des
            informations, systèmes, fichiers, codes, données techniques, documents ou éléments d’infrastructure informatique
            sensibles.<br>
            Le présent accord a pour objet de définir les engagements du Prestataire concernant la confidentialité, la
            non-divulgation et la sécurité des données et accès fournis par le Client.
        </p>

        <h2>2. Engagements du Prestataire</h2>
        <ul>
            <li>Ne divulguer aucune information confidentielle ou sensible relative au Client, à ses activités, ses outils,
                ses méthodes, ou ses données, même après la fin de la mission ;</li>
            <li>Protéger et sécuriser tous les accès (identifiants, mots de passe, tokens, clés API, etc.) qui lui sont
                confiés ;</li>
            <li>N’utiliser ces accès que dans le cadre strict du projet et ne jamais les partager avec un tiers, même en
                sous-traitance, sans accord écrit du Client ;</li>
            <li>Respecter l’intégrité des systèmes du Client, sans altérer, copier ou réutiliser les éléments (code source,
                bases de données, fichiers, configurations, etc.) à d'autres fins que celles prévues contractuellement ;
            </li>
            <li>Restituer ou supprimer toute donnée ou accès confidentiel sur simple demande du Client ou à la fin du
                projet.</li>
        </ul>

        <h2>3. Nature des informations concernées</h2>
        <p>Sont notamment considérées comme confidentielles, sans que cette liste soit limitative :</p>
        <ul>
            <li>les accès aux serveurs, hébergements, boîtes mail, outils tiers ;</li>
            <li>les données personnelles ou professionnelles des collaborateurs, clients ou partenaires du Client ;</li>
            <li>le code source, scripts, configurations ou toute ressource logicielle liée au projet ;</li>
            <li>les documents stratégiques, commerciaux ou organisationnels.</li>
        </ul>

        <h2>4. Sécurité et stockage</h2>
        <ul>
            <li>Utiliser des moyens raisonnables de protection (mots de passe forts, double authentification, stockage
                chiffré, etc.) ;</li>
            <li>Ne pas stocker durablement d’informations sensibles sur des supports non sécurisés ou personnels ;</li>
            <li>Ne pas utiliser d’outils tiers non validés (stockage cloud, transferts de fichiers) sans autorisation
                explicite du Client.</li>
        </ul>

        <h2>5. Durée de l’engagement</h2>
        <p>
            Le présent engagement de confidentialité est valable pendant toute la durée du projet, et se prolonge pour une
            durée de 3 ans après sa fin, quelle qu’en soit la cause.
        </p>

        <h2>6. Droit applicable</h2>
        <p>
            Le présent accord est régi par le droit français. Tout litige relatif à son interprétation ou à son exécution
            sera soumis aux tribunaux compétents du ressort de {{ $company->city ?? 'INC' }}.
        </p>

        <div class="mt-12">
            <p>Fait à Lyon, le {{ now()->format('d/m/Y') }}</p>
        </div>

        <div class="grid grid-cols-2 gap-8 mt-4 text-sm">
            <div>
                <p class="font-semibold uppercase">Le Client</p>
                <p class="mt-2">Nom : {{ $contact->full_name ?? '.............................................' }}</p>
                <p class="mt-6">Signature :</p>
            </div>

            <div class="flex justify-arround items-start">
                <div>
                    <p class="font-semibold uppercase">Le Prestataire</p>
                    <p class="mt-2">Nom : Notilac - Charles Saint Olive</p>
                    <p class="mt-6">Signature :</p>
                </div>

                <div class="ml-4">
                    <img src="{{ asset('images/signfirm.jpg') }}" alt="Signature" class="h-20 object-contain" />
                </div>
            </div>
        </div>
    </div>
@endsection
