-- Schéma de la table de stockage des MCE
-- les MCE géoréférencées précisément ou à la commune
DROP TABLE mce;
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
  geom geography -- en lon/lat
);
