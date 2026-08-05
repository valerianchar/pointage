# Mettre Pointage en ligne

Deux chemins, selon ce que vous préférez céder — de l'argent ou du confort.

| | Coût | Toujours allumé | Effort |
| --- | --- | --- | --- |
| **A — Render + Neon** | rien | non, s'endort après 15 min | ~30 min |
| **B — Oracle Cloud Always Free** | rien, mais carte bancaire vérifiée | oui | ~1 h |
| **C — VPS louté + déploiement continu** | le prix du VPS | oui | ~1 h, puis chaque push déploie |

Le chemin C convient à un VPS acheté chez n'importe qui — IONOS, Hetzner, Scaleway. Il
ajoute une intégration continue : les tests passent, l'image se construit sur GitHub, et
le serveur se contente de la récupérer. Sur une petite machine c'est décisif, le pic de
mémoire de la compilation des assets ne lui incombe plus.

Le chemin A ne demande ni carte bancaire, ni nom de domaine, ni machine : Render
fournit un sous-domaine `*.onrender.com` avec son certificat. En échange, le service
s'endort après quinze minutes d'inactivité et le premier chargement de la journée
prend une bonne minute.

Trois remarques valables dans les deux cas :

- **L'HTTPS n'est pas optionnel.** Sans lui, pas de service worker et pas
  d'installation sur l'écran d'accueil : la PWA ne serait qu'un site web. Les deux
  chemins le fournissent.
- **Les interfaces et les offres changent.** Les libellés exacts des consoles et les
  conditions des offres gratuites évoluent : vérifiez-les au moment de créer les
  comptes plutôt que de me croire sur parole.
- **Vos données financières partent sur une machine exposée à Internet.** Les deux
  procédures ferment les inscriptions, forcent les cookies en HTTPS et coupent le
  mode debug. Faites les sauvegardes : elles ne sont pas automatiques.

---

# Chemin A — Render + Neon, sans rien payer

## A1. La base de données, chez Neon

Sur [neon.tech](https://neon.tech), créez un compte et un projet. Choisissez une
région européenne pour limiter la latence. Dans les détails de connexion, relevez :

- l'hôte, de la forme `ep-quelque-chose-123456.eu-central-1.aws.neon.tech`
- le nom de la base, l'utilisateur et le mot de passe

Neon exige une connexion chiffrée, ce que `render.yaml` prévoit déjà avec
`DB_SSLMODE=require`. Le calcul de la base se met en veille après inactivité et se
réveille tout seul en moins d'une seconde : rien à faire.

## A2. Le service web, chez Render

Sur [render.com](https://render.com), connectez-vous avec votre compte GitHub, puis
**New → Blueprint** et choisissez le dépôt `pointage`. Render lit
[render.yaml](render.yaml) et propose le service avec son offre gratuite.

Renseignez ensuite les variables laissées vides, dans **Environment** :

| Variable | Valeur |
| --- | --- |
| `APP_KEY` | le résultat de `echo "base64:$(openssl rand -base64 32)"` |
| `APP_URL` | l'adresse publique du service, en `https://`, sans barre oblique finale |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | les valeurs relevées chez Neon (`DB_PORT` vaut 5432) |
| `POINTAGE_TASKS_TOKEN` | le résultat de `openssl rand -hex 32` |

`APP_URL` mérite une attention : c'est elle qui décide du schéma des adresses
fabriquées par l'application. Mal renseignée en `http://`, le navigateur refusera
les ressources sur une page servie en HTTPS. Render affiche l'adresse attribuée dès
la création du service ; corrigez la variable puis relancez le déploiement si elle
diffère de ce que vous aviez anticipé.

La première construction prend cinq à dix minutes, le temps d'installer les
dépendances et de compiler les assets. Les migrations s'appliquent au démarrage du
conteneur. Ouvrez ensuite l'adresse : l'écran de connexion doit apparaître, avec le
cadenas du navigateur.

## A3. Les opérations récurrentes

L'offre gratuite de Render n'exécute pas de tâche planifiée. Le dépôt contient donc
un workflow GitHub Actions qui appelle l'application une fois par mois :
[.github/workflows/recurrentes.yml](.github/workflows/recurrentes.yml).

Dans le dépôt GitHub, **Settings → Secrets and variables → Actions**, créez deux
secrets :

| Secret | Valeur |
| --- | --- |
| `POINTAGE_URL` | la même adresse que `APP_URL` |
| `POINTAGE_TOKEN` | la même valeur que `POINTAGE_TASKS_TOKEN` |

Vérifiez-le tout de suite sans attendre le 1er du mois : onglet **Actions** →
*Opérations récurrentes* → **Run workflow**. La réponse attendue est
`{"created":N}`. Le premier appel peut prendre une minute, le temps de réveiller le
service — le workflow réessaie de lui-même.

GitHub désactive les tâches planifiées d'un dépôt resté soixante jours sans
activité. Si vous ne poussez rien pendant deux mois, relancez le workflow à la main
pour les réactiver.

## A4. Créer votre profil, puis fermer la porte

Cliquez sur **Créer un profil** et inscrivez-vous. Puis fermez les inscriptions :
l'application n'a ni vérification d'e-mail ni réinitialisation de mot de passe, et
n'est pas prévue pour accueillir des inconnus.

Dans Render, passez `POINTAGE_REGISTRATION_OPEN` à `false` et relancez le
déploiement. L'écran d'inscription devient inaccessible et son lien disparaît de la
page de connexion. Pour ajouter quelqu'un plus tard, repassez à `true`, inscrivez-le,
puis refermez.

## A5. Installer la PWA

- **Android / Chrome** : menu ⋮ → « Installer l'application ».
- **iOS / Safari** : bouton Partager → « Sur l'écran d'accueil ». Safari n'installe
  que depuis Safari, pas depuis Chrome iOS.

## A6. Sauvegardes

Neon garde un historique permettant de revenir à un instant passé, dont la
profondeur dépend de l'offre — vérifiez la vôtre, elle est courte sur le plan
gratuit. Pour une copie qui vous appartienne, avec `pg_dump` installé localement :

```bash
pg_dump "postgresql://UTILISATEUR:MOTDEPASSE@HOTE/BASE?sslmode=require" | gzip > pointage-$(date +%F).sql.gz
```

Sur du suivi financier, prenez l'habitude de la lancer de temps en temps.

## Limites de ce chemin

Le service dort après quinze minutes sans visite : comptez une minute au premier
chargement. Les offres gratuites de Render et de Neon plafonnent aussi les
ressources et peuvent évoluer. Si l'attente devient pénible, le chemin B donne une
application toujours réveillée.

---

# Chemin B — Oracle Cloud Always Free, toujours allumé

Machine gratuite sans limite de durée, toujours allumée, avec un vrai cron et un
certificat renouvelé automatiquement. Une carte bancaire est demandée à l'inscription
pour vérifier l'identité — Oracle prélève environ un euro puis l'annule.

Ce chemin demande un **nom de domaine**, car un certificat ne s'obtient pas pour une
adresse IP nue. Un sous-domaine gratuit suffit, l'étape B4 en fournit un.

## B1. Créer la machine

Sur [cloud.oracle.com](https://cloud.oracle.com), créez le compte et choisissez une
région proche de vous : elle ne se change plus ensuite. Puis **Compute → Instances →
Create instance** :

| Réglage | Valeur |
| --- | --- |
| Image | Ubuntu 24.04 |
| Shape | `VM.Standard.A1.Flex` — 2 OCPU, 12 Go (ARM) |
| Repli si l'ARM est indisponible | `VM.Standard.E2.1.Micro` — 1 OCPU, 1 Go |
| Clés SSH | téléversez votre clé publique, ou laissez Oracle en générer une et **téléchargez-la** |

L'ARM est bien plus confortable, mais souvent annoncé « out of capacity » dans les
régions populaires : réessayez à un autre moment, ou prenez la micro AMD. Les deux
tiennent l'application ; la micro exige le fichier d'échange de l'étape B3.

Vérifiez que l'instance porte la mention **Always Free eligible** : c'est ce qui
distingue une machine gratuite d'une machine facturée. Notez l'**IP publique**.

## B2. Ouvrir les ports 80 et 443

C'est l'étape qui piège tout le monde : il y a **deux pare-feux**, et oublier le
second donne une machine qui répond au SSH et jamais en HTTP.

**Côté Oracle** — depuis l'instance, suivez le lien du sous-réseau, puis la liste de
sécurité, et ajoutez deux règles d'entrée :

| Source | Protocole | Port |
| --- | --- | --- |
| `0.0.0.0/0` | TCP | 80 |
| `0.0.0.0/0` | TCP | 443 |

**Côté machine** — les images Ubuntu d'Oracle arrivent avec un `iptables` qui rejette
tout le reste :

```bash
ssh ubuntu@VOTRE_IP
```

```bash
sudo iptables -I INPUT -p tcp --dport 80 -j ACCEPT && sudo iptables -I INPUT -p tcp --dport 443 -j ACCEPT && sudo iptables -I INPUT -p udp --dport 443 -j ACCEPT && sudo netfilter-persistent save
```

L'UDP 443 sert le HTTP/3 ; sans lui tout fonctionne, en un peu moins rapide.

## B3. Préparer la machine

Un fichier d'échange, **indispensable sur la micro 1 Go** et sain ailleurs :

```bash
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile && echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

Docker :

```bash
curl -fsSL https://get.docker.com | sudo sh && sudo usermod -aG docker ubuntu
```

Déconnectez-vous, reconnectez-vous pour que le groupe prenne effet, puis vérifiez :

```bash
docker run --rm hello-world
```

## B4. Obtenir un nom de domaine gratuit

Sur [duckdns.org](https://www.duckdns.org), connectez-vous, choisissez un
sous-domaine — par exemple `pointage-valerian` — et renseignez l'IP publique. Votre
adresse devient `pointage-valerian.duckdns.org`.

Vérifiez que le nom pointe bien avant d'aller plus loin, sinon Let's Encrypt
refusera le certificat :

```bash
dig +short pointage-valerian.duckdns.org
```

Si vous avez déjà un domaine, un enregistrement `A` vers l'IP fait aussi bien
l'affaire.

## B5. Déployer

```bash
git clone https://github.com/valerianchar/pointage.git && cd pointage && cp .env.production.example .env
```

Générez la clé de chiffrement et deux mots de passe :

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)"; echo "DB_PASSWORD=$(openssl rand -base64 24)"; echo "DB_ROOT_PASSWORD=$(openssl rand -base64 24)"
```

Reportez-les dans `.env`, puis renseignez votre domaine et votre adresse :

```
APP_URL=https://pointage-valerian.duckdns.org
APP_DOMAIN=pointage-valerian.duckdns.org
TLS_EMAIL=vous@exemple.fr
```

Lancez la pile — cinq à dix minutes la première fois :

```bash
docker compose -f compose.prod.yaml up -d --build
```

Suivez le démarrage ; Caddy demande le certificat au premier accès au domaine :

```bash
docker compose -f compose.prod.yaml logs -f app
```

Ici, le planificateur tourne dans son propre conteneur : ni workflow GitHub ni cron
externe à configurer.

## B6. Créer votre profil, puis fermer la porte

Inscrivez-vous, puis dans `.env` :

```
POINTAGE_REGISTRATION_OPEN=false
```

```bash
docker compose -f compose.prod.yaml up -d
```

## B7. Installer la PWA

Comme à l'étape A5.

## B8. Sauvegardes

Une sauvegarde à la demande :

```bash
cd ~/pointage && set -a && . ./.env && set +a && docker compose -f compose.prod.yaml exec -T mysql mysqldump -u root -p"$DB_ROOT_PASSWORD" "$DB_DATABASE" | gzip > ~/pointage-$(date +%F).sql.gz
```

La même chose chaque nuit à 3 h, conservée trente jours. Créez d'abord le dossier
avec `mkdir -p ~/sauvegardes`, puis `crontab -e` :

```
0 3 * * * cd $HOME/pointage && set -a && . ./.env && set +a && docker compose -f compose.prod.yaml exec -T mysql mysqldump -u root -p"$DB_ROOT_PASSWORD" "$DB_DATABASE" | gzip > $HOME/sauvegardes/pointage-$(date +\%F).sql.gz && find $HOME/sauvegardes -name 'pointage-*.sql.gz' -mtime +30 -delete
```

Une sauvegarde qui ne quitte jamais la machine ne protège pas de la perte de la
machine : copiez-en une de temps en temps sur votre poste avec `scp`.

Restaurer :

```bash
cd ~/pointage && set -a && . ./.env && set +a && gunzip -c ~/sauvegardes/pointage-2026-08-04.sql.gz | docker compose -f compose.prod.yaml exec -T mysql mysql -u root -p"$DB_ROOT_PASSWORD" "$DB_DATABASE"
```

## B9. Mettre à jour

```bash
cd ~/pointage && git pull && docker compose -f compose.prod.yaml up -d --build
```

---

# Chemin C — VPS avec déploiement continu

Chaque poussée sur `main` déclenche
[.github/workflows/deploiement.yml](.github/workflows/deploiement.yml) : la suite de
tests, puis la construction et la publication de l'image, puis la bascule sur le
serveur. Rien n'est déployé si les tests échouent, et le serveur ne compile rien.

Pour un VPS à 2 Go, la pile mesurée tient dans 700 à 750 Mo au repos. La compilation
des assets réclame 326 Mo de plus, mais elle a lieu sur GitHub : le serveur n'en voit
rien.

## C1. Préparer la machine

Commandez une machine avec Ubuntu 24.04. Deux vCores et 2 Go suffisent largement.
Puis, en SSH, le fichier d'échange et Docker — mêmes commandes qu'aux étapes
[B3](#b3-préparer-la-machine).

## C2. Ouvrir les ports 80 et 443

Comme à l'étape [B2](#b2-ouvrir-les-ports-80-et-443), mais l'endroit change selon
l'hébergeur : chez IONOS, les règles se posent dans le pare-feu du panneau
d'administration. Vérifiez ensuite qu'aucun filtrage ne subsiste sur la machine :

```bash
sudo iptables -L INPUT -n | head -20
```

Si vous y voyez une règle `REJECT` ou `DROP`, appliquez les commandes `iptables` de
l'étape B2.

## C3. Créer l'utilisateur de déploiement

La CI se connectera avec sa propre clé et son propre compte, sans mot de passe et
sans privilèges au-delà de Docker.

Sur **votre poste**, une paire de clés dédiée — distincte de celle qui vous sert à
vous connecter :

```bash
ssh-keygen -t ed25519 -f ~/.ssh/pointage-deploiement -C "deploiement-pointage" -N ""
```

Sur le **serveur** :

```bash
sudo adduser --disabled-password --gecos "" deploiement && sudo usermod -aG docker deploiement && sudo install -d -m 700 -o deploiement -g deploiement /home/deploiement/.ssh
```

Recopiez-y la clé publique — le contenu de `~/.ssh/pointage-deploiement.pub` :

```bash
sudo -u deploiement tee /home/deploiement/.ssh/authorized_keys > /dev/null <<'EOF'
ssh-ed25519 AAAA...remplacez-par-votre-clé-publique deploiement-pointage
EOF
sudo chmod 600 /home/deploiement/.ssh/authorized_keys
```

Vérifiez depuis votre poste que la connexion passe :

```bash
ssh -i ~/.ssh/pointage-deploiement deploiement@VOTRE_HOTE 'docker ps'
```

## C4. Le domaine

Comme à l'étape [B4](#b4-obtenir-un-nom-de-domaine-gratuit) : DuckDNS pointant vers
l'IP du VPS, ou votre propre domaine.

## C5. Installer l'application une première fois

En tant qu'utilisateur de déploiement, dans `/srv/pointage` :

```bash
sudo install -d -o deploiement -g deploiement /srv/pointage && sudo -u deploiement git clone https://github.com/valerianchar/pointage.git /srv/pointage
```

Créez le fichier d'environnement, en suivant l'étape [B5](#b5-déployer) pour les
valeurs — clé d'application, mots de passe de base, domaine, adresse de contact :

```bash
sudo -u deploiement cp /srv/pointage/.env.production.example /srv/pointage/.env && sudo -u deploiement nano /srv/pointage/.env
```

Le planificateur tourne ici dans son propre conteneur. **Désactivez le workflow des
récurrentes** (onglet Actions → *Opérations récurrentes* → menu ⋯ → *Disable
workflow*) : sans jeton configuré il échouerait chaque mois pour rien. Vous pouvez
aussi laisser `POINTAGE_TASKS_TOKEN` vide, la route reste alors inexistante.

## C6. Rendre l'image accessible au serveur

Les images publiées sur GHCR sont privées par défaut, même depuis un dépôt public.
Après le premier passage de la CI, sur `github.com/valerianchar?tab=packages` →
`pointage` → *Package settings* → *Change visibility* → **Public**.

L'image ne contient aucun secret : le fichier `.env` est exclu de sa construction,
seuls les modèles d'exemple y figurent.

Si vous préférez la garder privée, authentifiez le serveur une fois pour toutes avec
un jeton personnel ayant la portée `read:packages` :

```bash
sudo -u deploiement docker login ghcr.io -u valerianchar
```

## C7. Les secrets GitHub

Dans **Settings → Secrets and variables → Actions** :

| Secret | Valeur |
| --- | --- |
| `VPS_HOST` | l'adresse ou le nom d'hôte du serveur |
| `VPS_USER` | `deploiement` |
| `VPS_SSH_KEY` | le contenu **entier** de `~/.ssh/pointage-deploiement`, clé privée |
| `VPS_KNOWN_HOSTS` | la sortie de `ssh-keyscan VOTRE_HOTE` |
| `APP_HEALTH_URL` | la même adresse que `APP_URL` |

`VPS_KNOWN_HOSTS` évite que la CI fasse confiance au premier serveur qui répond à
son appel. Récupérez-le depuis votre poste :

```bash
ssh-keyscan VOTRE_HOTE
```

## C8. Premier déploiement

Onglet **Actions** → *Déploiement* → **Run workflow**. Le premier passage échouera
probablement à l'étape de récupération de l'image, tant que le paquet est privé :
faites l'étape C6, puis relancez.

Ensuite, chaque poussée sur `main` déploie. Le workflow attend que l'application
réponde sur `/up` avant de se déclarer satisfait — un déploiement vert signifie que
le site est réellement debout.

## C9. Créer votre profil, puis fermer la porte

Comme à l'étape [B6](#b6-créer-votre-profil-puis-fermer-la-porte). Modifiez `.env` sur
le serveur, puis relancez la pile — ou poussez un commit, ce qui la relancera aussi.

Les sauvegardes de l'étape [B8](#b8-sauvegardes) s'appliquent telles quelles, en
remplaçant `compose.prod.yaml` par `compose.deploy.yaml`.

---

# Dépannage

**Le certificat n'arrive pas (chemin B).** Le domaine doit pointer vers l'IP *avant*
le démarrage, et les ports doivent être ouverts des deux côtés (B2). Let's Encrypt
limite les tentatives par semaine : corrigez la cause avant de relancer en boucle.

```bash
docker compose -f compose.prod.yaml logs app | grep -i acme
```

**La page ne répond pas mais le SSH fonctionne (chemin B).** C'est `iptables`, neuf
fois sur dix. Revoyez B2 et vérifiez avec `sudo iptables -L INPUT -n`.

**MySQL redémarre en boucle sur la micro 1 Go.** Le fichier d'échange manque : B3.
`free -h` doit montrer 2 Go d'échange.

**Les images ou les scripts sont refusés par le navigateur.** `APP_URL` est en
`http://` alors que le site est servi en `https://` : c'est elle qui décide du schéma
des adresses fabriquées. Corrigez-la et relancez.

**« Votre session avait expiré. »** Normal après un redéploiement qui vide les
sessions : la page se recharge avec un jeton neuf, réessayez.

**Le déclencheur des récurrentes répond 404.** Aucun jeton n'est configuré :
renseignez `POINTAGE_TASKS_TOKEN`. Un 403 signifie que le jeton présenté ne
correspond pas.

**Le déploiement échoue sur « manifest unknown » ou « denied » (chemin C).** Le paquet
GHCR est encore privé : étape C6.

**Le déploiement échoue sur l'hôte SSH (chemin C).** `VPS_KNOWN_HOSTS` ne correspond
plus — c'est normal après une réinstallation de la machine, qui change sa clé d'hôte.
Régénérez le secret avec `ssh-keyscan`.

**Lancer une commande à la main.**

```bash
# chemin A : onglet Shell du service dans Render
php artisan transactions:generate-recurring

# chemin B
docker compose -f compose.prod.yaml exec app php artisan transactions:generate-recurring

# chemin C
cd /srv/pointage && docker compose -f compose.deploy.yaml exec app php artisan transactions:generate-recurring
```
