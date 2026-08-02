-- Migration : ajout de la plateforme Bus POST (variante MySQL)
INSERT IGNORE INTO di_plateformes (code, label, ordre)
VALUES ('bus_post', 'Bus POST', 6);
