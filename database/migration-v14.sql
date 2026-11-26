-- decurio CMS v1.4 — migration (à importer UNE FOIS dans phpMyAdmin, avant d'envoyer les fichiers)
-- 1) Date d'affichage "ALBUM OUT" sur la main page et la page release
ALTER TABLE albums ADD COLUMN release_date DATE NULL DEFAULT NULL AFTER subtitle;
-- 2) Comptes de réseaux sociaux supplémentaires (plusieurs comptes par plateforme)
ALTER TABLE albums ADD COLUMN extra_socials_json TEXT NULL DEFAULT NULL AFTER socials_json;
