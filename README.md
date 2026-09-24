# Virevo for WooCommerce

Passerelle de paiement **virement instantané (Virevo)** pour WooCommerce — sans
frais de carte. Le client est redirigé vers une page de paiement Virevo, et la
commande est validée automatiquement à réception du virement via un **webhook
signé**.

## Fonctionnalités
- Moyen de paiement au checkout **classique et par blocs** (WooCommerce Blocks).
- Création du paiement via l'**API publique Virevo `/v1`** (clé d'API).
- **Webhook signé** (HMAC-SHA256, anti-rejeu) → `payment_complete()` sur la commande.
- **Mode test** : intégration de bout en bout sans compte vérifié.
- Compatible **HPOS**.

## Installation (dev)
1. Copier ce dossier dans `wp-content/plugins/virevo-for-woocommerce/`.
2. Activer l'extension dans WordPress.
3. WooCommerce → Réglages → Paiements → **Virevo — virement instantané** :
   - mode `test` / `live`,
   - clé d'API (`vrv_test_…` / `vrv_live_…`) depuis le dashboard Virevo → Développeurs,
   - secret de webhook (`whsec_…`) ; enregistrer l'URL `/wp-json/virevo/v1/webhook`
     dans Virevo → Développeurs.

## Flux
1. `process_payment()` appelle `POST /v1/payments` (montant, devise, `reference` =
   ID de commande, `Idempotency-Key`) → met la commande **en attente** et redirige
   vers `payment_url`.
2. À l'encaissement, Virevo POST l'événement `payment.succeeded` signé sur le
   webhook → signature vérifiée → `payment_complete()`.

## Référence API
- Guide : https://virevo.fr/developpeurs
- OpenAPI : https://app.virevo.fr/docs

## Notes / à venir
- En mode test, déclencher `POST /v1/payments/{id}/simulate` pour passer le
  paiement à `succeeded` et tester le webhook.
- Le retour du client après paiement est en place : `return_url` et
  `cancel_url` sont transmis à `POST /v1/payments`. La validation de la commande
  reste portée par le webhook, le retour navigateur n'étant jamais une preuve
  de paiement.
- **Limite connue** : seul `payment.succeeded` est traité. `payment.failed`,
  `payment.canceled` et `payment.refunded` sont acquittés puis ignorés, donc une
  commande dont le paiement échoue reste « en attente » avec son stock réservé.
  À corriger.

## Distribution & mises à jour (pilotes)

Tant que le plugin n'est pas sur WordPress.org / la marketplace Woo, on
auto-distribue un ZIP.

### Construire le ZIP installable
```bash
bash bin/build-zip.sh
# → dist/virevo-for-woocommerce-<version>.zip  (dossier racine propre)
```
Le marchand l'installe via **wp-admin → Extensions → Ajouter → Téléverser**.

### Publier une release (automatisé)
Pousser un tag `vX.Y.Z` déclenche le workflow `.github/workflows/release.yml` qui
construit le ZIP et le **joint à une release GitHub** :
```bash
git tag v0.1.0 && git push origin v0.1.0
```

### Mises à jour automatiques
Le plugin interroge la **dernière release GitHub** et propose la mise à jour
dans l'admin si une version plus récente existe (ZIP de la release comme paquet).
⚠️ Pour des pilotes externes, la release (ou le dépôt) doit être **publique** —
sinon l'API GitHub renvoie 404 et l'updater ne fait rien (dégradation propre).
Alternative privée : héberger le ZIP + un manifeste sur un domaine maîtrisé.

## Licence
GPLv2 or later.
