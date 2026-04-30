# API DesTest — Documentation

> **Rôle :** Modèle de test pour valider le système de permissions API (Laravel Passport + Spatie Permission).  
> **Préfixe :** `/api/des-tests`  
> **Middlewares :** `auth:api`, `throttle:60,1` (60 requêtes/minute par token)

---

## Authentification

Toutes les routes requièrent un **Bearer Token** Laravel Passport.

### Obtenir un token

```bash
# Via Tinker
sail php artisan tinker
>>> $user = \App\Models\User::find(1);
>>> $token = $user->createToken('mon-token')->accessToken;
>>> echo $token;
```

### Envoyer le token

```http
Authorization: Bearer <access_token>
```

---

## Modèle de données

```json
{
  "id":         1,
  "name":       "Mon test",
  "published":  false,
  "created_at": "2026-04-14T10:00:00.000000Z",
  "updated_at": "2026-04-14T10:00:00.000000Z"
}
```

| Champ        | Type      | Description                         |
|--------------|-----------|-------------------------------------|
| `id`         | integer   | Identifiant unique (auto-increment) |
| `name`       | string    | Nom de la ressource (max 255)       |
| `published`  | boolean   | Statut de publication (défaut false)|
| `created_at` | datetime  | Date de création (ISO 8601)         |
| `updated_at` | datetime  | Date de modification (ISO 8601)     |

---

## Routes

### GET /api/des-tests
Liste toutes les ressources.

**Permission requise :** `destest.viewany`

**Réponse 200 :**
```json
[
  { "id": 1, "name": "Test A", "published": false, "created_at": "...", "updated_at": "..." },
  { "id": 2, "name": "Test B", "published": true,  "created_at": "...", "updated_at": "..." }
]
```

---

### POST /api/des-tests
Crée une nouvelle ressource.

**Permission requise :** `destest.create`

**Headers :**
```
Content-Type: application/json
```

**Body :**
```json
{
  "name": "Mon test"
}
```

| Champ  | Type   | Obligatoire | Validation          |
|--------|--------|-------------|---------------------|
| `name` | string | ✅           | min:1, max:255      |

**Réponse 201 :**
```json
{ "id": 3, "name": "Mon test", "published": false, "created_at": "...", "updated_at": "..." }
```

---

### DELETE /api/des-tests/{id}
Supprime une ressource.

**Permission requise :** `destest.delete`

**Réponse 204 :** *(pas de body)*

---

### POST /api/des-tests/{id}/publish
Publie une ressource (passe `published` à `true`).

**Permission requise :** `destest.publish`

**Réponse 200 :**
```json
{ "id": 1, "name": "Test A", "published": true, "created_at": "...", "updated_at": "..." }
```

---

## Réponses d'erreur

Toutes les erreurs retournent un JSON structuré :

```json
{
  "status":  <http_code>,
  "error":   "<CODE_MACHINE>",
  "message": "Description lisible"
}
```

### Codes d'erreur possibles

| HTTP | `error`              | Cause                                                    |
|------|----------------------|----------------------------------------------------------|
| 401  | `UNAUTHENTICATED`    | Token absent, invalide ou révoqué                        |
| 403  | `FORBIDDEN`          | Token valide mais permission manquante                   |
| 404  | `NOT_FOUND`          | Ressource inexistante (`{id}` introuvable)               |
| 422  | `VALIDATION_ERROR`   | Données invalides (erreurs de validation)                |
| 429  | `TOO_MANY_REQUESTS`  | Throttle dépassé (> 60 req/min)                          |
| 500  | `SERVER_ERROR`       | Erreur serveur inattendue                                |

### Exemple 401
```json
{ "status": 401, "error": "UNAUTHENTICATED", "message": "Unauthenticated." }
```

### Exemple 403
```json
{ "status": 403, "error": "FORBIDDEN", "message": "This action is unauthorized." }
```

### Exemple 404
```json
{ "status": 404, "error": "NOT_FOUND", "message": "Resource not found." }
```

### Exemple 422
```json
{
  "status":  422,
  "error":   "VALIDATION_ERROR",
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

---

## Permissions

Le système utilise Spatie Permission avec support des wildcards.

| Permission        | Accordée par           |
|-------------------|------------------------|
| `destest.*`       | Toutes les actions     |
| `destest.viewany` | Lister                 |
| `destest.create`  | Créer                  |
| `destest.delete`  | Supprimer              |
| `destest.publish` | Publier (action custom)|

### Attribuer toutes les permissions à Super Admin

```bash
sail php artisan permissions:grant-super-admin
```

### Attribuer une permission spécifique à un rôle via Tinker

```bash
sail php artisan tinker
>>> $role = \Spatie\Permission\Models\Role::findByName('manager');
>>> $role->givePermissionTo('destest.viewany');
```

---

## Exemple complet avec curl

```bash
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciO..."

# Lister
curl -s -H "Authorization: Bearer $TOKEN" https://<host>/api/des-tests

# Créer
curl -s -X POST \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Mon test"}' \
  https://<host>/api/des-tests

# Supprimer (id=1)
curl -s -X DELETE \
  -H "Authorization: Bearer $TOKEN" \
  https://<host>/api/des-tests/1

# Publier (id=2)
curl -s -X POST \
  -H "Authorization: Bearer $TOKEN" \
  https://<host>/api/des-tests/2/publish
```

---

## Notes de développement

- Ce contrôleur (`DesTestController`) sert de **référence** pour créer de nouvelles routes API.
- Reproduire le même pattern : `abort_unless(PermissionService::can('prefix.action'), 403)`.
- Les permissions sont déclarées dans le modèle via l'interface `HasApiPermissions` et générées avec `permissions:sync`.
