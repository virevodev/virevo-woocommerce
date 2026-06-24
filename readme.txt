=== Virevo for WooCommerce ===
Contributors: virevo
Tags: woocommerce, payment, virement, instant payment, sepa
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Encaissez par virement instantané (Virevo) dans WooCommerce, sans frais de carte.

== Description ==

Virevo for WooCommerce ajoute un moyen de paiement « virement instantané » au
checkout (classique et par blocs). À la commande, le client est redirigé vers
une page de paiement Virevo ; la commande est validée automatiquement à
réception du virement, via un webhook signé (HMAC-SHA256).

* Frais réduits par rapport à la carte.
* Aucun terminal, aucun abonnement.
* Mode test (bac à sable) pour intégrer sans compte vérifié.
* Compatible HPOS et checkout par blocs.

Vous gardez la maîtrise des fonds : Virevo n'est jamais dépositaire, le virement
arrive directement sur votre IBAN.

== Installation ==

1. Installez et activez l'extension.
2. WooCommerce → Réglages → Paiements → « Virevo — virement instantané ».
3. Renseignez votre clé d'API (test ou live) générée dans votre dashboard Virevo
   → Développeurs.
4. Dans Virevo → Développeurs, ajoutez un webhook pointant vers l'URL indiquée
   dans les réglages (`/wp-json/virevo/v1/webhook`) et collez le secret « whsec_… ».

== Frequently Asked Questions ==

= Comment tester sans compte vérifié ? =
Choisissez le mode « Test » et utilisez une clé `vrv_test_…`. Les paiements sont
fictifs ; vous pouvez simuler un encaissement depuis l'API pour déclencher le
webhook et valider votre intégration de bout en bout.

= Quelles devises sont supportées ? =
L'euro (EUR) pour le moment.

== Changelog ==

= 0.2.0 =
* Redirection du client après paiement (return_url) et annulation (cancel_url).

= 0.1.0 =
* Version initiale : passerelle de paiement, checkout classique + blocs,
  webhook signé, mode test.
