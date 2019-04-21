-- Schéma de la table de stockage des MCE
-- les MCE géoréférencées précisément ou à la commune
DROP TABLE if exists mce;
CREATE TABLE mce (
  num integer, -- id s'il existe sinon no en sequence
  mesure_id integer, -- id stable issu de la base
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
  md5 character varying, -- md5 de la concaténation des champs hors géométrie
  geom geography -- en lon/lat
);
