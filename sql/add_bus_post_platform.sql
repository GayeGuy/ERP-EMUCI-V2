-- Migration : ajout de la plateforme Bus POST
INSERT INTO di_plateformes (code, label, ordre)
VALUES ('bus_post', 'Bus POST', 6)
ON CONFLICT (code) DO NOTHING;
