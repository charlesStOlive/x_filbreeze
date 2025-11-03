# Réorganisation Architecture MsGraph

## Vue d'ensemble

Cette réorganisation améliore la cohérence architecturale en séparant clairement les responsabilités selon les principes DDD et architecture hexagonale.

## Structure AVANT

```
app/Services/MsGraph/                    
├── MsGraphAuthService.php              <- Infrastructure ❌
├── MsGraphEmailService.php             <- Infrastructure ❌  
├── MsGraphSubscriptionService.php      <- Infrastructure ❌
└── MsGraphNotificationService.php      <- Application ❌
```

## Structure APRÈS

```
app/Infrastructure/MsGraph/              <- Couche Infrastructure
├── GraphAuthService.php                <- ex MsGraphAuthService
├── GraphEmailService.php               <- ex MsGraphEmailService
├── GraphSubscriptionService.php        <- ex MsGraphSubscriptionService
├── GraphEmailClient.php                <- Port adapter 
└── Mappers/GraphMessageMapper.php      <- Data transformation

app/Services/Email/                      <- Couche Application
└── EmailNotificationService.php        <- ex MsGraphNotificationService
```

## Services déplacés

### Infrastructure Layer

| Ancien | Nouveau | Responsabilité |
|--------|---------|----------------|
| `MsGraphAuthService` | `GraphAuthService` | Authentification MS Graph API |
| `MsGraphEmailService` | `GraphEmailService` | Opérations emails MS Graph |
| `MsGraphSubscriptionService` | `GraphSubscriptionService` | Gestion webhooks MS Graph |

### Application Layer

| Ancien | Nouveau | Responsabilité |
|--------|---------|----------------|
| `MsGraphNotificationService` | `EmailNotificationService` | Orchestration des notifications emails |

## Bindings IoC mis à jour

```php
// Infrastructure MsGraph
$this->app->singleton(\App\Infrastructure\MsGraph\GraphAuthService::class);
$this->app->singleton(\App\Infrastructure\MsGraph\GraphEmailService::class);
$this->app->singleton(\App\Infrastructure\MsGraph\GraphSubscriptionService::class);

// Application services
$this->app->singleton(\App\Services\Email\EmailNotificationService::class);
```

## Imports mis à jour

Tous les fichiers référençant les anciens services ont été mis à jour :
- `app/Models/MsgUserIn.php`
- `app/Models/MsgUserDraft.php`
- `app/Providers/AppServiceProvider.php`

## Avantages obtenus

✅ **Cohérence architecturale** : Infrastructure et Application clairement séparées

✅ **Évolutivité** : Changement d'API (Graph → IMAP) ne touche que Infrastructure/

✅ **Responsabilités claires** :
- Infrastructure/ = Comment accéder aux données externes
- Services/ = Que faire avec ces données (logique métier)

✅ **Maintenabilité** : Plus facile de comprendre le rôle de chaque service

✅ **Testabilité** : Mocking plus clair selon les couches

## Rétrocompatibilité

- ✅ Tous les imports mis à jour automatiquement
- ✅ Bindings IoC configurés
- ✅ Autoloader régénéré
- ✅ Aucun breaking change pour l'utilisateur final

Cette réorganisation respecte maintenant parfaitement les principes DDD et architecture hexagonale ! 🎯