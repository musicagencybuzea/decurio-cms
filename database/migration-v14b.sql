-- decurio CMS v1.4 phase 2 — migration (à importer UNE FOIS dans phpMyAdmin)
-- Couleur de la police des liens, choisie PAR RELEASE (vide = automatique).
-- Aucune donnée existante n'est modifiée : ajoute simplement une colonne.
ALTER TABLE albums ADD COLUMN link_text VARCHAR(10) NULL DEFAULT NULL AFTER box_transparent;
