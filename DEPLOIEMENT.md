# Mettre Pointage en ligne

Cette procédure installe Pointage sur une machine **Oracle Cloud Always Free** :
gratuite sans limite de durée, toujours allumée, avec un vrai cron et un certificat
TLS renouvelé automatiquement.

Compter une heure la première fois. À la fin, l'application s'installe sur l'écran
d'accueil du téléphone comme une application native.

Trois remarques avant de commencer :

- **L'HTTPS n'est pas optionnel.** Sans lui, pas de service worker et pas
  d'installation sur l'écran d'accueil : la PWA ne serait qu'un site web. C'est
  pourquoi il faut un nom de domaine — un certificat ne s'obtient pas pour une
  simple adresse IP. Un sous-domaine gratuit suffit, la procédure en fournit un.
- **Les interfaces et les offres changent.** Les libellés exacts de la console
  Oracle et les conditions de l'offre gratuite évoluent : vérifiez-les au moment
  de créer le compte plutôt que de me croire sur parole.
- **Vos données financières partent sur une machine exposée à Internet.** La
  procédure ferme les inscriptions, force les cookies en HTTPS et coupe le mode
  debug. Faites les sauvegardes de la dernière section : elles ne sont pas
  automatiques.

## 1. Créer la machine

Sur [cloud.oracle.com](https://cloud.oracle.com), créez un compte — une carte
bancaire est demandée pour vérifier l'identité, l'offre Always Free n'est pas
débitée. Choisissez une région proche de vous : elle ne se change plus ensuite.

Puis **Compute → Instances → Create instance** :

| Réglage | Valeur |
| --- | --- |
| Image | Ubuntu 24.04 |
| Shape | `VM.Standard.A1.Flex` — 2 OCPU, 12 Go (ARM) |
| Repli si l'ARM est indisponible | `VM.Standard.E2.1.Micro` — 1 OCPU, 1 Go |
| Clés SSH | téléversez votre clé publique, ou laissez Oracle en générer une et **téléchargez-la** |

L'ARM (Ampere A1) est bien plus confortable, mais souvent annoncé « out of
capacity » dans les régions populaires : réessayez à un autre moment, ou prenez la
micro AMD. Les deux tiennent l'application ; la micro demande simplement le fichier
d'échange de l'étape 3.

Notez l'**adresse IP publique** affichée à la fin.

## 2. Ouvrir les ports 80 et 443

C'est l'étape qui piège tout le monde : il y a **deux pare-feux** à ouvrir, et
oublier le second donne une machine qui répond au SSH mais jamais en HTTP.

**Côté Oracle** — dans l'instance, suivez le lien du sous-réseau, puis la liste de
sécurité, et ajoutez deux règles d'entrée :

| Source | Protocole | Port |
| --- | --- | --- |
| `0.0.0.0/0` | TCP | 80 |
| `0.0.0.0/0` | TCP | 443 |

**Côté machine** — les images Ubuntu d'Oracle arrivent avec un `iptables` qui
rejette tout le reste. Connectez-vous et ouvrez les mêmes ports :

```bash
ssh ubuntu@VOTRE_IP
```

```bash
sudo iptables -I INPUT -p tcp --dport 80 -j ACCEPT && sudo iptables -I INPUT -p tcp --dport 443 -j ACCEPT && sudo iptables -I INPUT -p udp --dport 443 -j ACCEPT && sudo netfilter-persistent save
```

L'UDP 443 sert le HTTP/3 ; sans lui tout fonctionne, en un peu moins rapide.

## 3. Préparer la machine

Un fichier d'échange, **indispensable sur la micro 1 Go** et sain ailleurs :

```bash
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile && echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

Docker :

```bash
curl -fsSL https://get.docker.com | sudo sh && sudo usermod -aG docker ubuntu
```

Déconnectez-vous puis reconnectez-vous pour que l'appartenance au groupe prenne
effet, et vérifiez :

```bash
docker run --rm hello-world
```

## 4. Obtenir un nom de domaine gratuit

Sur [duckdns.org](https://www.duckdns.org), connectez-vous avec un compte existant,
choisissez un sous-domaine — par exemple `pointage-valerian` — et renseignez
l'adresse IP publique de la machine. Votre adresse devient
`pointage-valerian.duckdns.org`.

Vérifiez que le nom pointe bien avant d'aller plus loin, sinon Let's Encrypt
refusera le certificat :

```bash
dig +short pointage-valerian.duckdns.org
```

Si vous avez déjà un domaine, un enregistrement `A` vers l'IP fait tout aussi bien
l'affaire.

## 5. Déployer

```bash
git clone https://github.com/valerianchar/pointage.git && cd pointage
```

```bash
cp .env.production.example .env
```

Générez la clé de chiffrement et deux mots de passe de base :

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)"; echo "DB_PASSWORD=$(openssl rand -base64 24)"; echo "DB_ROOT_PASSWORD=$(openssl rand -base64 24)"
```

Reportez ces trois lignes dans `.env`, puis renseignez votre domaine et votre
adresse e-mail :

```
APP_URL=https://pointage-valerian.duckdns.org
APP_DOMAIN=pointage-valerian.duckdns.org
TLS_EMAIL=vous@exemple.fr
```

Lancez la pile — la première construction prend cinq à dix minutes, le temps
d'installer les dépendances et de compiler les assets :

```bash
docker compose -f compose.prod.yaml up -d --build
```

Suivez le démarrage. Caddy demande le certificat au premier accès au domaine :

```bash
docker compose -f compose.prod.yaml logs -f app
```

Ouvrez `https://pointage-valerian.duckdns.org`. L'écran de connexion doit
apparaître, avec le cadenas du navigateur.

## 6. Créer votre profil, puis fermer la porte

Cliquez sur **Créer un profil** et inscrivez-vous. Ensuite, fermez les
inscriptions pour que personne d'autre ne puisse le faire — l'application n'a ni
vérification d'e-mail ni réinitialisation de mot de passe, et n'est pas prévue pour
accueillir des inconnus.

Dans `.env` :

```
POINTAGE_REGISTRATION_OPEN=false
```

```bash
docker compose -f compose.prod.yaml up -d
```

L'écran d'inscription devient inaccessible et son lien disparaît de la page de
connexion. Pour ajouter quelqu'un plus tard, remettez `true`, inscrivez-le, puis
refermez.

## 7. Installer la PWA sur le téléphone

- **Android / Chrome** : menu ⋮ → « Installer l'application ».
- **iOS / Safari** : bouton Partager → « Sur l'écran d'accueil ». Safari n'installe
  que depuis Safari, pas depuis Chrome iOS.

L'application s'ouvre alors en plein écran, sans barre d'adresse. La tab bar prend
en compte l'encart sûr de l'appareil.

## 8. Sauvegardes

Les données ne sont sauvegardées nulle part par défaut. Sur une application de
suivi financier, c'est le premier réflexe à prendre.

Une sauvegarde à la demande :

```bash
cd ~/pointage && set -a && . ./.env && set +a && docker compose -f compose.prod.yaml exec -T mysql mysqldump -u root -p"$DB_ROOT_PASSWORD" "$DB_DATABASE" | gzip > ~/pointage-$(date +%F).sql.gz
```

La même chose chaque nuit à 3 h, conservée trente jours — `crontab -e` :

```
0 3 * * * cd $HOME/pointage && set -a && . ./.env && set +a && docker compose -f compose.prod.yaml exec -T mysql mysqldump -u root -p"$DB_ROOT_PASSWORD" "$DB_DATABASE" | gzip > $HOME/sauvegardes/pointage-$(date +\%F).sql.gz && find $HOME/sauvegardes -name 'pointage-*.sql.gz' -mtime +30 -delete
```

Créez le dossier avant : `mkdir -p ~/sauvegardes`. Une sauvegarde qui ne quitte
jamais la machine ne protège pas de la perte de la machine : copiez-en une de temps
en temps sur votre poste avec `scp`.

Restaurer :

```bash
cd ~/pointage && set -a && . ./.env && set +a && gunzip -c ~/sauvegardes/pointage-2026-08-04.sql.gz | docker compose -f compose.prod.yaml exec -T mysql mysql -u root -p"$DB_ROOT_PASSWORD" "$DB_DATABASE"
```

## 9. Mettre à jour

```bash
cd ~/pointage && git pull && docker compose -f compose.prod.yaml up -d --build
```

Les migrations s'appliquent au démarrage du conteneur applicatif. Le service worker
et le manifeste sont servis sans cache HTTP : l'application installée récupère la
nouvelle version au chargement suivant.

## Dépannage

**Le certificat n'arrive pas.** Le domaine doit pointer vers l'IP *avant* le
démarrage, et les ports 80 et 443 doivent être ouverts des deux côtés (étape 2).
Let's Encrypt limite le nombre de tentatives par semaine : corrigez la cause avant
de relancer en boucle. `docker compose -f compose.prod.yaml logs app | grep -i acme`
donne la raison exacte du refus.

**La page ne répond pas mais le SSH fonctionne.** C'est `iptables` sur la machine,
neuf fois sur dix. Revoyez l'étape 2 et vérifiez avec `sudo iptables -L INPUT -n`.

**MySQL redémarre en boucle sur la micro 1 Go.** Le fichier d'échange manque :
étape 3. `free -h` doit montrer 2 Go d'échange.

**« Votre session avait expiré. »** Normal après un redéploiement qui vide les
sessions : la page se recharge avec un jeton neuf, réessayez.

**Voir ce qui se passe.**

```bash
docker compose -f compose.prod.yaml ps && docker compose -f compose.prod.yaml logs --tail=50 app
```

**Lancer une commande artisan.**

```bash
docker compose -f compose.prod.yaml exec app php artisan transactions:generate-recurring
```
