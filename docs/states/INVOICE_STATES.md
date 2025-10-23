# États et Transitions - Modèle Invoice

*Documentation générée automatiquement le 23/10/2025 à 09:08:46*

## Classe d'État

**Classe :** `App\Models\States\Invoice\InvoiceState`

## États Disponibles

| État | Classe | Label | Couleur | Icône | Description |
|------|--------|-------|---------|-------|-------------|
| Canceled | `App\Models\States\Invoice\Canceled` | Abandonné | danger | heroicon-o-x-mark | Abandonné |
| Draft | `App\Models\States\Invoice\Draft` | Brouillon | gray | heroicon-o-pencil | Brouillon. |
| Payed | `App\Models\States\Invoice\Payed` | Payé | success | heroicon-o-check | Enregistrement du paiement. |
| Submited | `App\Models\States\Invoice\Submited` | Soumise | info | heroicon-o-paper-airplane | Facture soumise.X |

## Transitions Disponibles

| Transition | Classe | Label | Icône | Formulaire | Redirection |
|------------|--------|-------|-------|------------|-------------|
| ToCanceled | `App\Models\States\Invoice\ToCanceled` | Abandonner | heroicon-o-x-mark | Non | Non |
| ToPayed | `App\Models\States\Invoice\ToPayed` | Payement reçus | heroicon-o-check | Oui | Non |
| ToSubmited | `App\Models\States\Invoice\ToSubmited` | Soumettre | heroicon-o-paper-airplane | Oui | Non |

### Détails des Transitions avec Formulaires

#### Payement reçus (`ToPayed`)

**Champs du formulaire :**
- **payed_at** : Payé le

#### Soumettre (`ToSubmited`)

**Champs du formulaire :**
- **submited_at** : Validé le

