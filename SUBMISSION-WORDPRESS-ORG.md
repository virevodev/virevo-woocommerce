# Dépôt sur WordPress.org — dossier de soumission

> **Préparé le 2026-09-23.** À relire en entier avant de cliquer sur « Add your
> plugin » : une extension refusée repart en fin de file d'attente, et la file se
> compte en semaines.

---

## 0. Le bloquant qui commande tout

La directive du dépôt officiel est explicite :

> « Les extensions qui ne donnent accès qu'à un bac à sable (*sandbox*) sont
> également des versions d'essai, et ne sont pas autorisées. »

Aujourd'hui, Virevo ne peut fonctionner qu'en **mode test** : les identifiants
Oxlin ne sont pas arrivés, aucun paiement réel n'est possible. Une soumission
maintenant serait refusée sur ce motif, et le motif est imparable.

**Rien ne part avant que l'encaissement réel fonctionne en production.** Tout ce
qui suit peut être préparé d'ici là, et c'est le but de ce document.

---

## 1. Fait

- **`Stable tag` aligné sur la version du plugin** (0.5.1). Il était resté à
  0.5.0, et c'est le `Stable tag` qui commande la version **réellement servie**
  par WordPress.org : le dépôt aurait publié l'ancienne sans rien signaler.
  `bin/build-zip.sh` refuse désormais de construire si les deux divergent.
- **Divulgation du service tiers** dans le `readme.txt`, obligatoire pour une
  extension qui sert d'interface à un service externe : ce qui est envoyé, quand,
  et les liens vers les CGU et la politique de confidentialité.
  L'argument est solide ici : **aucune donnée personnelle du client final ne
  transite**, seulement le montant, la devise, le numéro de commande et les deux
  adresses de retour de la boutique.
- **Entrée de changelog 0.5.1**, qui manquait.
- **Mots-clés revus** : le dépôt n'en retient que **cinq**, les suivants sont
  ignorés. Ce sont donc cinq choix, pas une liste.
- **Deux questions ajoutées à la FAQ** : le compte Virevo obligatoire, et les
  données transmises. Ce sont les deux premières questions d'un relecteur.
- **L'auto-mise à jour GitHub est retirée du paquet officiel.** Voir §2.

---

## 2. L'auto-mise à jour, et pourquoi elle sort du paquet

`includes/class-virevo-updater.php` interroge les releases GitHub pour proposer
une mise à jour dans l'admin. C'est indispensable tant qu'on auto-distribue un
ZIP à des pilotes, et **interdit sur WordPress.org** : la directive 8 proscrit
« servir des mises à jour, ou installer des extensions, des thèmes ou des
modules depuis des serveurs autres que ceux de WordPress.org ».

Le paquet officiel se construit donc sans ce fichier :

```bash
bash bin/build-zip.sh --wporg     # → dist/…-<version>-wporg.zip, sans l'updater
bash bin/build-zip.sh             # → paquet d'auto-distribution, avec l'updater
```

Le chargeur teste l'existence du fichier avant de l'utiliser : les deux paquets
fonctionnent, sans branche ni constante à gérer.

---

## 3. Reste à faire avant de soumettre

### Bloquant

1. **Les paiements réels doivent fonctionner** (cf. §0). Rien avant ça.
2. **Créer le compte WordPress.org** qui portera l'extension. Le `readme.txt`
   annonce `Contributors: virevo`, et **ce compte n'existe pas** :
   `profiles.wordpress.org/virevo` renvoie 404, `virevodev` aussi. Le nom déclaré
   doit être un identifiant réel, et c'est ce compte qui soumet.
3. **Tester sur un WordPress à jour.** Le `readme.txt` annonce `Tested up to:
   6.6` alors que la version courante est **7.1**. Le dépôt affiche un
   avertissement au-delà de trois versions majeures d'écart, et finit par sortir
   l'extension des résultats de recherche. **Ne pas monter ce chiffre sans avoir
   réellement testé** : ce serait une fausse déclaration, sur le champ le plus
   facile à vérifier.

### Fortement conseillé

4. **Traiter les quatre événements du webhook.** La version actuelle ne gère que
   `payment.succeeded` : une commande dont le paiement échoue reste en attente
   pour toujours, stock réservé. Ce n'est pas un motif de refus, c'est un motif
   d'avis à une étoile, et un avis ne se retire pas. La FAQ le dit franchement
   en attendant, ce qui vaut mieux que de le taire.
5. **Produire les visuels.** Ils ne vont **pas** dans le ZIP, mais dans le
   dossier `assets/` du SVN, à la racine, à côté de `trunk/` et `tags/` :
   - `icon-256x256.png` (1 Mo maximum, mais viser quelques dizaines de Ko),
   - `banner-772x250.png` et `banner-1544x500.png` pour le double densité,
   - `screenshot-1.png`, `screenshot-2.png`… décrites dans une section
     `== Screenshots ==` du `readme.txt`.
   Attention aux marques : ni logo WooCommerce ni logo WordPress dans ces images.
6. **Relire le code avec les yeux d'un relecteur** : échappement des sorties,
   validation des entrées, vérification des droits et des nonces sur tout ce qui
   écrit. Le contrôle est humain et il regarde ça en premier.

---

## 4. La soumission, une fois débloqué

1. Se connecter sur WordPress.org avec le compte de l'étape 2.
2. Déposer le ZIP sur **https://wordpress.org/plugins/developers/add/**
   (le paquet `--wporg`, quelques dizaines de kilo-octets ici).
3. Attendre la **revue humaine**. Elle se compte en semaines, et elle revient
   avec une liste de corrections plutôt qu'un refus sec : on corrige, on renvoie.
4. À l'approbation, un dépôt **SVN** est ouvert. Pas git : c'est la surprise
   classique, et le dépôt GitHub reste la source de vérité en amont.

---

## 5. Publier, ensuite

La structure SVN est plate et tient en trois dossiers :

```
/trunk       le code courant
/tags/0.5.1  une copie figée par version publiée
/assets      les visuels (hors paquet, jamais dans trunk/assets)
```

Le mécanisme à comprendre, parce qu'il surprend tout le monde :

- WordPress.org lit **`trunk/readme.txt`** et y cherche `Stable tag` ;
- si `Stable tag` vaut un numéro de version, c'est **`/tags/<ce numéro>/`** qui
  est servi aux utilisateurs, pas `trunk` ;
- publier une version = copier le code dans `/tags/x.y.z/`, **puis** mettre à
  jour `Stable tag` dans `trunk/readme.txt`. Dans cet ordre.

Oublier la seconde étape laisse les utilisateurs sur l'ancienne version, sans
aucun message d'erreur. C'est exactement le défaut corrigé au §1, qui se
reproduira à chaque publication si le garde-fou de `bin/build-zip.sh` est
contourné.

---

## 6. Les autres places de marché

Résumé, le détail est dans `PLUGINS.md` du dépôt principal :

| Canal | Coût | Ce qu'il faut savoir |
|---|---|---|
| **WordPress.org** | gratuit | Le bon premier canal. Revue humaine, pas de partage de revenu. |
| **WooCommerce Marketplace** | 30 % du chiffre | Circuit distinct de WordPress.org. Candidature de vendeur, examen du modèle économique. Inutile pour une extension gratuite. |
| **PrestaShop Addons** | 99 € par an et par module | Validation automatique d'abord, rapport de conformité, 3 dépôts par jour maximum. |
| **Adobe Commerce** | — | Programme EQP, revue manuelle par un ingénieur, formulaires fiscaux W-8 / W-9. Le plus lourd, pour le plus petit parc. |
| **Shopify** | — | Sans objet : statut Partenaire Paiements sur invitation, service en production exigé. |
