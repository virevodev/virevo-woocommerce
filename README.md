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
- Guide : https://virevo.fr/developpeurs.html
- OpenAPI : https://app.virevo.fr/docs

## Notes / à venir
- En mode test, déclencher `POST /v1/payments/{id}/simulate` pour passer le
  paiement à `succeeded` et tester le webhook.
- Retour automatique du client après paiement (`return_url`) : à ajouter quand
  l'API l'exposera ; aujourd'hui la commande est validée par le webhook.

## Licence
GPLv2 or later.
