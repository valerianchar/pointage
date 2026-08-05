# Mettre Pointage en ligne

Deux chemins, selon ce que vous préférez céder — de l'argent ou du confort.

| | Coût | Toujours allumé | Effort |
| --- | --- | --- | --- |
| **A — Render + Neon** | rien | non, s'endort après 15 min | ~30 min |
| **B — Oracle Cloud Always Free** | rien, mais carte bancaire vérifiée | oui | ~1 h |

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

**Lancer une commande à la main.**

```bash
# chemin A : onglet Shell du service dans Render
php artisan transactions:generate-recurring

# chemin B
docker compose -f compose.prod.yaml exec app php artisan transactions:generate-recurring
```
