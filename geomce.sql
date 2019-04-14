-- Schéma de la table de stockage des MCE
-- les MCE géoréférencées précisément ou à la commune
DROP TABLE mce;
CREATE TABLE mce (
  source character varying(8), -- 'CPII', 'CEREMA' ou 'IGNGP'
  date_export character varying(8), -- date de l'export au format YYYYMMDD
  georef character varying(8), -- 'direct' si géoréf direct, 'commune' si géoréf à la commune
  mesure_id integer,
  projet character varying,
  categorie character varying,
  mo character varying,
  communes text[],
  procedure character varying,
  date_decision date,
  classe character varying,
  type character varying,
  cat character varying,
  sscat character varying,
  duree text,
  si_metier character varying,
  numero_dossier character varying,
  geom geography -- en EPSG:4326 lon/lat
);
