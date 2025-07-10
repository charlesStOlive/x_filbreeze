<h1>Contacts de l’entreprise {{ $company->name }}</h1>

<ul>
    @foreach($contacts as $contact)
        <li>{{ $contact->full_name }} – {{ $contact->email }}</li>
    @endforeach
</ul>

<p style="margin-top: 2em;">Préparé par {{ $user->name }}</p>
