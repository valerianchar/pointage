# Pointage

PWA de suivi de comptes bancaires personnels. On déclare ses comptes, on saisit dépenses
et ajouts, on les tague, et on les **pointe** — c'est-à-dire qu'on les rapproche de son
relevé bancaire. Les opérations récurrentes réapparaissent chaque mois, non pointées.

Deux mises en page pour un même code : mobile (tab bar de cinq onglets) et desktop
(sidebar de 248 px). Thème « Nocturne » — sombre, dense, accent violet en trait uniquement.

## Stack

- **Laravel 13** + **Inertia 3** + **Vue 3**
- **Reka UI** pour les primitives accessibles (cases de pointage, interrupteur, onglets,
  boutons radio, jauges, notifications)
- **Tailwind CSS 4** — les jetons du thème sont déclarés en `@theme` dans `resources/css/app.css`
- **Laravel Sail** (MySQL 8.4) pour l'environnement local

## Démarrage

```bash
cp .env.example .env && composer install && ./vendor/bin/sail up -d
```

```bash
./vendor/bin/sail artisan key:generate && ./vendor/bin/sail artisan migrate --seed
```

```bash
npm install && npm run build
```

L'application répond sur **http://localhost:8080**.

En développement, `npm run dev` remplace `npm run build` (serveur Vite sur le port 5174).
Les ports sont réglables dans le `.env` : `APP_PORT`, `VITE_PORT`, `FORWARD_DB_PORT`.

### Profil de démonstration

Le seeder recrée le jeu de données de la maquette — quatre comptes, leurs tags par défaut,
les opérations du mois et deux mois d'historique pour que le graphe d'évolution ait une pente.

- **demo@pointage.app** / **password**

## Écrans

| Chemin | Écran |
| --- | --- |
| `/connexion`, `/inscription` | Connexion et création de profil |
| `/verrouillage` | Écran de verrouillage (mot de passe ou biométrie) |
| `/` | Accueil — patrimoine, flux du mois, évolution du solde, liste des comptes |
| `/compte/{compte}` | Détail — jauge de pointage, dépenses par tag, opérations |
| `/ajouter` | Ajouter une opération (dépense ou ajout, récurrente ou non) |
| `/recurrentes` | Instances récurrentes du mois et reste à pointer |
| `/tags` | Tags d'un compte, avec leur nombre d'opérations |
| `/nouveau-compte` | Déclarer un compte (six types, tags par défaut) |

## Aperçus

Captures prises sur l'application servie par Sail, aux deux largeurs de référence du
handoff — 402 px et 1440 px. Elles sont dans [docs/apercus](docs/apercus).

| Écran | Mobile | Desktop |
| --- | --- | --- |
| Accueil | [402 px](docs/apercus/final-home-mobile.png) | [1440 px](docs/apercus/final-home-desktop.png) |
| Détail de compte | [402 px](docs/apercus/final-account-mobile.png) | [1440 px](docs/apercus/final-account-desktop.png) |
| Ajouter une opération | [402 px](docs/apercus/final-add-mobile.png) | [1440 px](docs/apercus/final-add-desktop.png) |
| Récurrentes | [402 px](docs/apercus/final-recurring-mobile.png) | [1440 px](docs/apercus/final-recurring-desktop.png) |

## Opérations récurrentes

Cocher « Récurrente » à la saisie crée un **modèle** en plus de l'opération du mois. Les mois
suivants, une commande planifiée en génère l'instance, non pointée :

```bash
./vendor/bin/sail artisan transactions:generate-recurring
```

Elle accepte `--month=AAAA-MM` pour rattraper un mois, et se relance sans créer de doublon —
un index unique `(recurring_transaction_id, occurred_on)` le garantit. La planification la
déclenche le 1er de chaque mois à 00 h 05 ; elle suppose donc un `schedule:work` ou une
entrée cron sur `schedule:run`.

## Choix d'implémentation

**Montants en centimes entiers.** Les colonnes `amount_cents` et `initial_balance_cents` sont
des entiers signés : les sommes restent exactes, sans arrondi flottant. Le front divise par 100
et formate en `fr-FR`. La saisie accepte `64,90`, `64.90`, `12 480,30` ou `1.234,56`.

**Le solde n'est jamais stocké.** Il vaut toujours `initial_balance_cents` plus la somme des
opérations, agrégée en base. Aucune dérive possible entre le solde affiché et les opérations.
Le solde saisi à la déclaration d'un compte devient son point de départ.

**Les opérations non pointées ne disparaissent jamais.** L'écran d'un compte affiche le mois
courant *et* tout ce qui reste à pointer des mois précédents — sinon une opération oubliée
deviendrait inatteignable.

**Verrouiller n'est pas se déconnecter.** Le cadenas (mobile) masque l'application derrière
l'écran de verrouillage en gardant la session ouverte ; « Se déconnecter » (desktop) ferme la
session. Le déverrouillage se fait au mot de passe, ou par confirmation biométrique de
l'appareil quand il en est capable (Face ID / Touch ID via WebAuthn).

La biométrie joue ici le rôle d'un verrouillage d'écran : c'est le cookie de session, déjà
authentifié, qui porte l'identité, et la confirmation reste locale à l'appareil. Elle ne
protège que l'affichage. Le contrôle d'accès réel, lui, passe par la connexion.

**Mode confidentialité.** L'icône œil remplace tous les montants par `••••• €`. La préférence
est portée par le profil, donc suivie d'un appareil à l'autre.

## PWA

`public/manifest.webmanifest` et `public/sw.js` rendent l'application installable, avec
raccourcis vers « Ajouter », « Récurrentes » et « Tags ».

Le service worker ne met **pas** les pages HTML en cache : elles portent des soldes et des
opérations, et un cache leur survivrait à la déconnexion. Seuls les assets construits — dont
le nom porte déjà une empreinte, donc immuables — et la coquille hors ligne sont conservés.
Sans réseau, l'application affiche `offline.html` plutôt que des montants périmés. Un accès
hors ligne aux données demanderait une persistance locale chiffrée, qui n'est pas en place.

## Tests

```bash
./vendor/bin/sail artisan test
```

76 tests couvrent l'authentification, le verrouillage, le pointage, la création de comptes,
d'opérations et de tags, les autorisations entre profils, la génération des récurrentes et
l'analyse des montants saisis.

```bash
./vendor/bin/sail pint
```
