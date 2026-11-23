# decurio CMS – version 1.2 (pages de liens exclusives + bibliothèques)

CMS PHP/MySQL autonome installé sur **http​s://links.decur.io** et administré via FTP (FileZilla).
Page publique en anglais ; administration en anglais, français ou allemand (EN par défaut).

## Nouveautés v1.2

- **Page de liens exclusive par release** : chaque album peut recevoir un numéro de release (3 chiffres, saisi manuellement, ex. 7 → `007`), ce qui crée automatiquement la page **`links.decur.io/007`** : pochette, liens de streaming, logos réseaux sociaux de l'artiste (Instagram, Facebook, TikTok, YouTube — SVG monochromes), musique de release. Basée sur le mod_rewrite Apache (`.htaccess` inclus) ; en cas d'échec du rewrite, la page reste accessible via `links.decur.io/?num=007`.
- **Recherche automatique de liens** (gratuite, dans Albums) : on colle un lien Spotify, le CMS identifie l'album/le titre, puis va chercher les liens Apple Music, Deezer et (si clé YouTube configurée) YouTube Music. Les résultats sont proposés pré-cochés ; rien n'est publié sans enregistrement humain. Les plateformes sans API publique (Tidal, Amazon, Qobuz, Beatport, Napster…) se remplissent manuellement.
- **Bibliothèque de plateformes** (onglet Platforms) : ajouter/retirer, noms personnalisables, cases « preferred » (proposées en premier dans Albums), « custom » (ex. « More info » vers notre site).
- **Bibliothèque de stickers** (onglet Stickers) : PNG/JPG/WebP jusqu'à 2 Mo. Jusqu'à 3 stickers par album, positions haut gauche / haut milieu / haut droit, à cheval sur le bord de la pochette, invisibles quand la carte est retournée.
- **Albums enrichis** : sous-titre, mention « single » discrète, réorganisation de la liste par **glisser-déposer** (flèches ↑/↓ en secours si le CDN est injoignable), nouvelles sorties automatiquement en position 1, les rééditions ne bougent pas.
- **Publication planifiée** : statut « scheduled » + sélecteur de date/heure (calendrier flatpickr, fuseau Europe/Berlin ; sinon sélecteur natif). La sortie apparaît publique dès l'heure passée.
- **Design de la page exclusive personnalisable par release** : fond noir `#141414` / gris `#4d4d4d` / blanc `#f0f0f0` / beige `#d6b399` / rose `#f05078`, ou preset « pochette floutée » (comme une story Instagram). Couleur de la box de liens réglable **indépendamment** du fond (auto ou couleur explicite) + transparence activable séparément.
- **Musique** : bibliothèque de morceaux (onglet Music, découpés automatiquement à 30 s si ffmpeg est disponible, sinon il faut les fournir déjà découpés). Musique d'accueil sur la page publique (mini-player discret, nom défilant) et musique de release sur la page exclusive (bouton sur la pochette). Lecture 30 s max, fondu de sortie 1,5 s, jamais d'autoplay.
- **Divers** : favicon configurable (Settings), champ preview (bouton Preview ↗ même sur les brouillons, protégé par la session admin).
- Le v1.1 reste acquis : corporate design (fond `#141414`, texte `#f0f0f0`, sand `#d6b399`, accent `#f05078`), Celeste Regular en texte courant, Lato Regular en headlines toujours majuscules, « decurio » toujours en minuscules et jamais en headline (logo uniquement), administration trilingue.

## Structure des fichiers

```
index.php                   Page publique + routage /XXX et ?preview=ID
.htaccess                   Réécriture links.decur.io/XXX + protections
admin/login.php             Connexion (EN/FR/DE)
admin/dashboard.php         Total / Published / Scheduled / Drafts
admin/albums.php            Formulaire complet v1.2 + drag & drop + chercheur de liens
admin/platforms.php         Bibliothèque des plateformes
admin/stickers.php          Bibliothèque de stickers (upload)
admin/music.php             Bibliothèque musicale (upload, trim 30 s ffmpeg)
admin/find-links.php        Endpoint AJAX de la recherche automatique
admin/settings.php          Paramètres (dont favicon + musique d'accueil)
admin/logout.php
app/bootstrap.php           Session durcie, DB, fuseau Europe/Berlin
app/i18n.php                Sélecteur de langue + helper t()
app/translations.php        Dictionnaire EN / FR / DE
app/ui.php                  Gabarit de l'admin + helpers (sticker, couleurs, tracks)
app/linkfinder.php          Pipeline gratuit : Spotify → Apple / Deezer / YouTube
app/release-page.php        Gabarit de la page exclusive links.decur.io/XXX
app/config/config.sample.php
assets/css/site.css         Site public (grille, cartes flip, stickers, mini-player)
assets/css/admin.css        Administration
assets/css/release.css      Page exclusive (fonds, box, socials, player)
uploads/albums/.htaccess    Neutralise l'exécution de scripts
uploads/music/.htaccess     idem
uploads/stickers/.htaccess  idem
database/install.sql        Schéma complet (installation neuve)
database/migration-v12.sql  Migration v1.1 → v1.2 (phpMyAdmin, une seule importation)
```

## Faire la mise à jour v1.2 sur le serveur (phpMyAdmin + FileZilla)

1. **Sauvegarder** : export MySQL complet + copie locale des dossiers `app/config/config.php` et `uploads/`.
2. **phpMyAdmin** : importer `database/migration-v12.sql` **une seule fois** (avancé), **avant** d'envoyer les nouveaux fichiers. Ajoute colonnes/tables, garde les données existantes.
3. **FileZilla** : envoyer tout le nouveau contenu du dossier, en écrasant :
   - `index.php` + nouveau `.htaccess` (racine)
   - tout `admin/` et tout `app/`
   - `assets/css/` : site.css, admin.css + **nouveau** release.css
   - les nouveaux dossiers `uploads/music/` et `uploads/stickers/` avec leurs `.htaccess`
   - `database/` peut rester hors serveur (précédemment supprimé), les SQL sont sur GitHub
4. **Ne pas** remplacer `app/config/config.php`, `uploads/albums/` ni les polices sous `assets/fonts/`.
5. Vérifier : `links.decur.io` s'affiche, un album édité puis sauvegardé fonctionne, bouton Preview ↗ dans Albums.

## Activer la recherche automatique de liens (optionnel, gratuit)

Dans `app/config/config.php` (copier les lignes depuis `config.sample.php`) :

```php
'spotify_client_id'     => 'xxxxxxxx',   // dashboard de développement Spotify, app "Web API"
'spotify_client_secret' => 'xxxxxxxx',
'youtube_api_key'       => 'xxxxxxxx',   // console Google Cloud, API "YouTube Data v3" (gratuit)
```

Sans ces clés, l'admin affiche un message clair (« API credentials missing ») et tout se fait manuellement — le CMS reste 100 % fonctionnel. YouTube : si la clé est absente, la plateforme est simplement absente des résultats.

## ffmpeg

Sur l'hébergement partagé, ffmpeg n'est probablement pas disponible. La bibliothèque Music l'indique : si absent, fournir les extraits **déjà découpés à 30 s max** (quality loss : aucun, c'est le fichier d'origine).

## Polices et logo

Placer les fichiers sous `assets/fonts/` :
- `CelesteOffcPro.ttf` (texte courant)
- `Lato-Regular.ttf` (titres)

Tant que les polices ne sont pas présentes, les fallbacks Georgia/Arial prennent le relais. Vérifier que la licence autorise l'hébergement des fonts sur ce domaine.

Le logo se place sous `assets/images/` puis son chemin se configure dans **Settings → Logo file path** (ex. `assets/images/logo.png`). Idem pour le **favicon** (`Settings → Favicon path`).

## Avant la mise en production

- HTTPS actif partout.
- Mot de passe admin unique et fort.
- Sauvegardes régulières de MySQL et de `uploads/`.
- `app/config/config.php` n'est accessible d'aucun navigateur (blocage via `.htaccess`).

## Limites connues

- Plateformes sans API publique (Tidal, Amazon, Qobuz, Beatport, Napster, Pandora…) : liens manuels.
- Pas de limitation des tentatives de connexion (rate limiting), pas de multi-utilisateurs ni de rôles.
- Google Play Music est fermé depuis longtemps : il reste dans la liste au cas où un ancien lien doit être conservé.
- Odesli (lien unique type smart-link automatique pour toutes les plateformes) : service payant à destination commerciale ~25–50 USD/mois ; non intégré.
