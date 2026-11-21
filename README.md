# decurio CMS – version 1.1 (corporate design & administration trilingue)

Prototype PHP/MySQL autonome installé sur un sous-domaine (par ex. `music.decur.io`) et administré via FTP (FileZilla).

## Nouveautés de cette version

- **Corporate design decurio appliqué partout** : fond `#141414`, texte `#f0f0f0`, sand `#d6b399`, accent `#f05078`. Plus aucune couleur hors charte.
- **Typographie** : Celeste Regular pour le texte courant (Fließtext), Lato Regular pour les headlines (toujours en majuscules via CSS). Le nom du label n'est jamais composé en headline : la page publique et l'admin utilisent le logo (configurable dans Paramètres) ou le mot-symbole « decurio », toujours en minuscules.
- **Styles centralisés** : `assets/css/site.css` (site public) et `assets/css/admin.css` (administration). Fini les styles dupliqués dans chaque page — tout le design se règle à un seul endroit.
- **Administration trilingue** : anglais par défaut, avec bascule **EN / FR / DE** en haut de chaque page (choix mémorisé en session via `?lang=xx`). Le dictionnaire complet se trouve dans `app/translations.php`.
- **Interface 100 % anglaise par défaut**, y compris formulaires Albums et Settings, messages d'erreur, confirmations et intitulés du dashboard.
- **Site public en anglais**, lettre pour lettre.
- **Gabarit d'admin partagé** (`app/ui.php`) : même en-tête, même navigation, même position du sélecteur de langue sur toutes les pages.
- **Correctifs** :
  - `index.php` redéclarait la fonction `e()` déjà définie dans le bootstrap → erreur fatale sur le site public. Corrigé.
  - Cookies de session durcis (`HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS).
  - `uploads/albums/.htaccess` : l'exécution de scripts dans le dossier d'upload est bloquée.

## Structure des fichiers

```
index.php                  Page publique (anglais)
admin/login.php            Connexion (EN/FR/DE)
admin/dashboard.php        Intitulés propres : Total releases / Published / Drafts
admin/albums.php           Formulaire et table en anglais
admin/settings.php         Formulaire en anglais
admin/logout.php
app/bootstrap.php          Session durcie, DB, helpers
app/i18n.php               Sélecteur de langue + helper t()
app/translations.php       Dictionnaire EN / FR / DE
app/ui.php                 Gabarit commun de l'administration
app/config/config.sample.php
assets/css/site.css        Charte graphique du site public
assets/css/admin.css       Charte graphique de l'admin
uploads/albums/.htaccess   Neutralise l'exécution de scripts
database/install.sql       Schéma MySQL (inchangé)
```

## Faire la mise à jour sur le serveur (FileZilla)

1. Sauvegarder l'actuel dossier (ou au minimum `app/config/config.php`, `uploads/`, la base MySQL).
2. Envoyer par FTP les fichiers modifiés et nouveaux, en écrasant :
   - `index.php`
   - tout le dossier `admin/`
   - `app/bootstrap.php`
   - les nouveaux fichiers : `app/i18n.php`, `app/translations.php`, `app/ui.php`, `uploads/albums/.htaccess`
   - les nouveaux dossiers : `assets/css/` (site.css + admin.css)
3. Ne **pas** remplacer `app/config/config.php` ni le contenu de `uploads/albums/`.
4. Aucune modification de base de données n'est nécessaire : le schéma et les tables sont inchangés.

## Polices et logo

Placer les fichiers sous `assets/fonts/` :
- `CelesteOffcPro.ttf` (texte courant)
- `Lato-Regular.ttf` (titres)

Tant que les polices ne sont pas présentes, les fallbacks Georgia/Arial prennent le relais. Vérifier que la licence autorise l'hébergement des fonts sur ce domaine.

Le logo se place sous `assets/images/` puis son chemin se configure dans **Settings → Logo file path** (ex. `assets/images/logo.png`). Il s'affiche alors aussi dans l'en-tête de l'administration et sur la page de connexion.

## Installation (première fois)

1. Créer le sous-domaine, par exemple `music.decur.io`, et associer son dossier racine.
2. Créer une base MySQL et un utilisateur dans le panneau Febas.
3. Importer `database/install.sql` via phpMyAdmin.
4. Copier `app/config/config.sample.php` vers `app/config/config.php`, puis renseigner les accès MySQL. Ne jamais partager ce fichier.
5. Envoyer le contenu du dossier `decurio-cms/` par FTP dans la racine du sous-domaine.
6. Créer `uploads/albums/` si nécessaire et donner les permissions d'écriture (commencer par 755).
7. Ouvrir `https://music.decur.io/install/create-admin.php`, créer un compte avec un mot de passe fort.
8. **Supprimer immédiatement le dossier `install/` du serveur.**
9. Se connecter à `https://music.decur.io/admin/login.php`.

La version PHP de l'hébergement Febas n'étant pas encore connue, vérifiez-la (PHP 8.1+ recommandé, extensions pdo_mysql et fileinfo).

## Avant la mise en production

- HTTPS actif partout.
- Mot de passe admin unique et fort ; supprimer `install/` après création du compte.
- Sauvegardes régulières de MySQL et de `uploads/`.
- Idéalement déplacer les secrets hors de la racine web si Febas le permet.

## Limites connues (prochaines étapes possibles)

- Pas de récupération automatique des liens Spotify/EAN (nécessite le choix d'une API, une clé et une validation humaine des résultats).
- Pas de limitation des tentatives de connexion (rate limiting) ni de journal d'audit.
- Pas de multi-utilisateurs ni de rôles, pas de publication programmée.
- Pas encore de favicon ni d'en-têtes de sécurité (X-Frame-Options, HSTS) — à ajouter via le panneau d'hébergement ou un `.htaccess` racine.
