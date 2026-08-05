# Pointage

PWA de suivi de comptes bancaires personnels. On déclare ses comptes, on saisit dépenses
et ajouts, on les tague, et on les **pointe** — c'est-à-dire qu'on les rapproche de son
relevé bancaire. Les opérations récurrentes réapparaissent chaque mois, non pointées.

Deux mises en page pour un même code : mobile (tab bar de six onglets) et desktop
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

### Mise en ligne

[DEPLOIEMENT.md](DEPLOIEMENT.md) décrit deux chemins, tous deux gratuits : **Render +
Neon** — rien à payer, ni carte ni domaine, mais le service s'endort après quinze
minutes — et **Oracle Cloud Always Free** — toujours allumé, avec un vrai cron, au prix
d'un domaine gratuit à déclarer et d'une vérification par carte.

Le premier s'appuie sur [render.yaml](render.yaml) et sur un workflow GitHub Actions
mensuel, puisque l'offre gratuite n'exécute pas de tâche planifiée. Le second sur
[compose.prod.yaml](compose.prod.yaml) — une image qui sert les fichiers, exécute PHP et
obtient son certificat, plus le planificateur et la base.

Un troisième chemin couvre un VPS loué, avec déploiement continu :
[.github/workflows/deploiement.yml](.github/workflows/deploiement.yml) enchaîne les tests,
la publication de l'image sur GHCR et la bascule par SSH, puis attend que `/up` réponde
avant de se déclarer satisfait. Le serveur ne compile rien — il récupère
[l'image déjà prête](compose.deploy.yaml), ce qui lui épargne le pic de mémoire des assets.

La même image sert les deux : elle écoute sur le port imposé par la plateforme quand il y
en a un, et le schéma des URL fabriquées se déduit de `APP_URL`. Aucun en-tête
`X-Forwarded-*` n'est pris pour argent comptant, faute de quoi on pourrait annoncer une
fausse adresse IP et contourner la limite de tentatives de connexion.

`POINTAGE_REGISTRATION_OPEN=false` ferme les inscriptions : l'écran devient inaccessible et
son lien disparaît de la page de connexion. Une instance en ligne n'a en général qu'un
propriétaire, et l'application n'a ni vérification d'e-mail ni réinitialisation de mot de passe.

### Profil de démonstration

Le seeder recrée le jeu de données de la maquette — quatre comptes, leurs tags par défaut,
les opérations du mois et deux mois d'historique pour que le graphe d'évolution ait une pente.

- **demo@pointage.app** / **password**

## Écrans

| Chemin | Écran |
| --- | --- |
| `/connexion`, `/inscription` | Connexion et création de profil |
| `/verrouillage` | Écran de verrouillage (mot de passe ou biométrie) |
| `/` | Accueil — patrimoine, flux du mois, évolution du solde ; grille de widgets sur desktop |
| `/compte/{compte}` | Détail — jauge de pointage, dépenses par tag, crédits, opérations |
| `/ajouter` | Ajouter une opération (dépense ou ajout, récurrente ou non) |
| `/recurrentes` | Instances récurrentes du mois et reste à pointer |
| `/credits` | Crédits par compte et suivi du capital restant |
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
| Crédits | [402 px](docs/apercus/final-credits-mobile.png) | [1440 px](docs/apercus/final-credits-desktop.png) |

## Accueil personnalisable

Sur desktop, l'accueil est une grille de quatre colonnes où chaque widget en occupe une
ou deux. Le bouton « Personnaliser » ouvre la liste des widgets disponibles, et le choix
est enregistré sur le profil — donc suivi d'un appareil à l'autre.

Onze widgets : patrimoine, flux du mois, taux d'épargne, reste à pointer, dépense moyenne
par jour et projection de fin de mois, charge récurrente, capital restant dû, évolution du
solde, dépenses par tag tous comptes, top dépenses du mois, répartition par compte.

Un widget masqué n'est pas seulement caché : ses données ne sont pas calculées. Les flux du
mois et l'évolution du solde restent en revanche toujours envoyés, parce que l'accueil
mobile les affiche et n'est, lui, pas personnalisable — c'est le parti pris de la maquette.

## Crédits

Un crédit appartient à un compte et porte un capital emprunté, un capital restant dû, une
mensualité, une durée et le jour du mois où le prélèvement tombe.

Trois valeurs se déduisent au lieu d'être stockées : la part remboursée (bornée à 0–100 %,
pour qu'un capital saisi de travers ne produise pas de jauge absurde), la prochaine échéance
(le jour de prélèvement à venir, ramené au dernier jour des mois trop courts — un
prélèvement au 31 tombe le 28 février) et le nombre de mensualités restantes au rythme
actuel, qui reste une estimation puisqu'elle ignore les intérêts encore à courir.

La durée se saisit et se conserve en mois — un prêt de dix-huit mois se déclare donc
directement — mais s'affiche en clair : 60 mois se relisent « sur 5 ans », 18 mois
« sur 1 an et 6 mois ». Plafond à 600 mois.

À la déclaration, un seul des deux capitaux suffit — l'autre s'en déduit, et le crédit
démarre alors comme s'il n'avait rien remboursé. Un capital restant supérieur au capital
emprunté est refusé, comme une durée hors du 1–600 ou un jour hors du 1–31. Les crédits
d'un compte apparaissent aussi sur son écran de détail.

Un crédit reste déclaratif : sa mensualité ne crée pas d'opération et ne bouge donc pas le
solde du compte. Pour qu'elle le fasse, il faudrait la brancher sur les modèles récurrents,
qui savent déjà générer une échéance mensuelle à un jour donné.

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

**Session expirée.** Quand le jeton CSRF d'une page ne vaut plus rien — onglet resté ouvert,
sessions vidées —, Laravel répond une page « Page Expired » en HTML qu'une visite Inertia ne
sait pas interpréter : le bouton paraît mort. `App\Exceptions\RetryExpiredSession` renvoie
donc l'utilisateur sur sa page, rechargée avec un jeton frais, et une notification lui dit de
réessayer.

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

116 tests couvrent l'authentification, le verrouillage, le pointage, la création de comptes,
d'opérations, de tags et de crédits, les autorisations entre profils, la génération des
récurrentes, les échéances de crédit, le calcul de chaque widget et l'analyse des montants
saisis.

```bash
./vendor/bin/sail pint
```
