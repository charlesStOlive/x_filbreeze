{{--
    Dans le panneau d'aide, un lien « #modal-voyage.periodes » (la forme qui, dans l'admin, ouvre la page dans une fenêtre)
    mène à la page elle-même : /aide/voyage/periodes. L'identifiant d'une page est son chemin, points pour barres obliques.
--}}
<script>
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href^="#modal-"]')

        if (! link) {
            return
        }

        event.preventDefault()

        const page = link.getAttribute('href').slice('#modal-'.length).split('.').map(encodeURIComponent).join('/')

        window.location.assign(@js(url(filament()->getCurrentOrDefaultPanel()->getPath())) + '/' + page)
    })
</script>
