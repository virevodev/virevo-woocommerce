=== Virevo for WooCommerce ===
Contributors: virevo
Tags: woocommerce, payment gateway, instant payment, sepa, open banking
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.7.0
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

= Service tiers requis =

Cette extension est une interface vers **Virevo**, un service d'encaissement par
virement instantané exploité par Virevo SAS (France). **Elle ne fonctionne pas
seule** : un compte Virevo et une clé d'API sont nécessaires.

**Ce qui est transmis à Virevo, et quand.** À chaque commande réglée par ce
moyen de paiement, l'extension appelle l'API Virevo (`https://app.virevo.fr`)
pour créer la demande de paiement. Sont envoyés :

* le **montant** et la devise de la commande ;
* le **numéro de commande**, comme référence de rapprochement ;
* les deux **adresses de retour** de votre boutique (page « commande reçue » et
  page de paiement), pour ramener le client après son règlement.

**Aucune donnée personnelle de votre client n'est transmise** par l'extension :
ni nom, ni adresse e-mail, ni adresse postale, ni coordonnées bancaires. Le
client s'authentifie directement auprès de sa banque, sur la page de paiement.

En sens inverse, Virevo appelle l'URL de webhook de votre boutique pour signaler
l'issue du paiement. Chaque appel est signé (HMAC-SHA256) et vérifié avant
d'être pris en compte.

Un remboursement déclenché depuis l'administration WooCommerce transmet à Virevo
l'identifiant du paiement, le montant et, le cas échéant, le motif que vous
saisissez.

* Site du service : https://virevo.fr
* Conditions générales d'utilisation : https://virevo.fr/cgu
* Politique de confidentialité : https://virevo.fr/confidentialite

== Installation ==

1. Installez et activez l'extension.
2. WooCommerce → Réglages → Paiements → « Virevo — virement instantané ».
3. Renseignez votre clé d'API (test ou live) générée dans votre dashboard Virevo
   → Développeurs.
4. Dans Virevo → Développeurs, ajoutez un webhook pointant vers l'URL indiquée
   dans les réglages (`/wp-json/virevo/v1/webhook`) et collez le secret « whsec_… ».

== Frequently Asked Questions ==

= Faut-il un compte Virevo ? =
Oui. L'extension est une interface vers le service Virevo : sans compte ni clé
d'API, elle n'a rien à appeler. La création du compte est gratuite.

= Quelles données de mes clients sont envoyées à Virevo ? =
Aucune. L'extension ne transmet que le montant, la devise, le numéro de commande
et les adresses de retour de votre boutique. Votre client s'authentifie
directement auprès de sa banque.

= Comment tester sans compte vérifié ? =
Choisissez le mode « Test » et utilisez une clé `vrv_test_…`. Les paiements sont
fictifs ; vous pouvez simuler un encaissement depuis l'API pour déclencher le
webhook et valider votre intégration de bout en bout.

= Quelles devises sont supportées ? =
L'euro (EUR) pour le moment. Le virement instantané est un instrument de la zone
euro : un client situé hors de cette zone ne peut pas régler ainsi.

= Pourquoi « Virement instantané » n'apparaît-il pas au checkout ? =
Parce qu'il ne pourrait pas aboutir. L'extension masque le moyen de paiement
tant que la clé du mode actif est absente ou que son préfixe contredit le mode,
et sur une boutique qui n'est pas en euros. Un bandeau dans l'administration
indique lequel de ces cas s'applique.

= Que se passe-t-il si mon client abandonne le paiement ? =
La commande est clôturée automatiquement, et le stock réservé est libéré. Un
paiement refusé passe la commande en « échoué », ce qui laisse le client
réessayer ; une demande annulée ou expirée la passe en « annulé ».

== Changelog ==

= 0.7.0 =
* Les quatre événements de webhook sont traités, et non plus seulement le succès. Un paiement échoué, annulé ou expiré clôt la commande au lieu de la laisser en attente indéfiniment, stock réservé.
* Un remboursement décidé depuis le tableau de bord Virevo crée la ligne de remboursement correspondante dans la boutique.
* Garde anti-boucle : un remboursement lancé depuis l'administration WooCommerce n'est plus compté deux fois quand Virevo le renotifie.
* Une commande déjà payée n'est jamais annulée par une notification tardive.

= 0.6.0 =
* Le moyen de paiement est masqué au checkout, classique et blocs, tant que la clé du mode actif manque, que son préfixe contredit le mode, ou que la boutique n'est pas en euros.
* Une clé dont le préfixe contredit son champ est refusée à l'enregistrement.
* Bandeau d'administration permanent : clé manquante, secret de webhook manquant, ou mode test actif.

= 0.5.1 =
* Vérification de signature : accepte plusieurs `v1=` pendant une rotation du secret de webhook.

= 0.5.0 =
* Remboursements depuis l'admin WooCommerce (total/partiel) → API Virevo. Messages d'erreur API plus clairs.

= 0.4.0 =
* Stock décrémenté à la confirmation du paiement (commande en attente jusqu'au virement) ; meilleure gestion des abandons.

= 0.3.0 =
* Réglage « URL de l'API » (permet de tester contre une API locale).

= 0.2.0 =
* Redirection du client après paiement (return_url) et annulation (cancel_url).

= 0.1.0 =
* Version initiale : passerelle de paiement, checkout classique + blocs,
  webhook signé, mode test.
