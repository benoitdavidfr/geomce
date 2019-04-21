title: requêtes SQL

select *
from mcecpii20190226direct
where ST_Area(geom) < 0

select ST_SRID(emprises) from mesure_emprise

select ST_AsText(ST_ConvexHull(emprises)) from mesure_emprise

select * from mesure_emprise
where ST_Area(emprises) = (select max(ST_Area(emprises)) from mesure_emprise)

select projet, ST_Area(geom)/10000 from mcecpii20190226direct
where ST_Area(geom) = (select max(ST_Area(geom)) from mcecpii20190226direct)

select projet, ST_Area(geom)/10000 area from mcecpii20190226direct
order by area desc

===

-- création de tables simplifiées et agrégées par projet et communes
drop table if exists dela;
create table dela as
select projet, communes, sum(ST_Area(geom))/10000 area_ha, sum(ST_Length(geom))/1000 as length_km
from mcecpii20190226direct
group by projet, communes;

drop table if exists delb;
create table delb as
select projet, communes, sum(ST_Area(wkb_geometry))/10000 area_ha, sum(ST_Length(wkb_geometry))/1000 as length_km
from ogrgeojson
group by projet, communes;

-- création de tables simplifiées et agrégées par projet et communes
drop table if exists dela;
create table dela as
select projet, sum(ST_Area(geom))/10000 area_ha, sum(ST_Length(geom))/1000 as length_km
from mcecpii20190226direct
group by projet;

drop table if exists delb;
create table delb as
select projet, sum(ST_Area(the_geog))/10000 area_ha, sum(ST_Length(the_geog))/1000 as length_km
from ogrgeojson
group by projet;

===
-- compréhension de ogrgeojson
3005 lignes

select sum(ST_Area(the_geog))/10000 area_ha, sum(ST_Length(the_geog))/1000 length_km
from ogrgeojson;

area_ha: 29427.0533144574
length_km: 289.461778379654

-- géométries distinctes
drop table mg2;
create table mg2 as
select distinct md5(the_geog) id, the_geog
from ogrgeojson
-- 2792

select sum(ST_Area(the_geog))/10000 area_ha, sum(ST_Length(the_geog))/1000 length_km
from mg2;

area_ha: 27767
length_km: 289
===

mesure_emprise: 6290
ogrgeojson: 3005

-- géométries distinctes
drop table mg;
create table mg as
select distinct md5(ST_AsEWKB(emprises)) id, emprises, ST_GeogFromWKB(emprises) geog
from mesure_emprise
-- 5248 lignes

select sum(ST_Area(geog))/10000 area_ha, sum(ST_Length(geog))/1000 length_km
from mg;
area_ha: 17741
length_km: 197

Mesure définie par: projet, mo, communes, procedure, type, geom

drop table mpmcptg;
create table mpmcptg as
select distinct md5(concat(projet, mo, communes, procedure, type, ST_AsEWKB(emprises))) id, projet, mo, communes, procedure, type, ST_GeogFromWKB(emprises) geog
from mesure_emprise
-- 5268 ligne(s)

-- mesures ayant même géométrie
select a.id, b.*
from mpmcptg a, mpmcptg b
where a.id<>b.id and a.id < b.id and md5(ST_AsEWKB(a.geom))=md5(ST_AsEWKB(b.geom))
-- 20 lignes

-- vérification que les autres champs sont fonctionnellement dépendants
categorie
date_decision
classe
cat
sscat
duree
si_metier
numero_dossier

select distinct projet, mo, communes, procedure, type, emprises, categorie
from mesure_emprise
-- 5268 ok

select distinct projet, mo, communes, procedure, type, emprises, date_decision
from mesure_emprise
-- 5268 ok

select distinct projet, mo, communes, procedure, type, emprises, classe
from mesure_emprise
-- 5268 ok

select distinct projet, mo, communes, procedure, type, emprises, cat
from mesure_emprise
-- 5268 ok

select distinct projet, mo, communes, procedure, type, emprises, sscat
from mesure_emprise
-- 5268 ok

select distinct projet, mo, communes, procedure, type, emprises, duree
from mesure_emprise
-- 5269 ligne(s) KO

select a.duree, b.duree, b.*
from mesure_emprise a
join mesure_emprise b using(projet, mo, communes, procedure, type, emprises)
where a.duree<>b.duree
-- Je considère que c'est une erreur !

-- mesures définies dans plusieurs SI métier
select distinct projet, mo, communes, procedure, type, emprises, si_metier
from mesure_emprise
-- 5567
select a.si_metier, b.si_metier, b.*
from mesure_emprise a
join mesure_emprise b using(projet, mo, communes, procedure, type, emprises)
where a.si_metier<>b.si_metier

-- mesures correspondant à plusieurs mesures dans SI métier
select distinct projet, mo, communes, procedure, type, emprises, numero_dossier
from mesure_emprise
-- 6289

select a.si_metier, a.numero_dossier, b.si_metier, b.numero_dossier, b.*
from mesure_emprise a
join mesure_emprise b using(projet, mo, communes, procedure, type, emprises)
where a.numero_dossier<>b.numero_dossier and a.si_metier=b.si_metier

===


-- création de tables simplifiées et agrégées par projet et communes
drop table if exists dela;
create table dela as
select projet, sum(ST_Area(geog))/10000 area_ha, sum(ST_Length(geog))/1000 as length_km
from mpmcptg
group by projet;
-- 656 lignes

drop table if exists delb;
create table delb as
select projet, sum(ST_Area(the_geog))/10000 area_ha, sum(ST_Length(the_geog))/1000 as length_km
from ogrgeojson
group by projet;
-- 831 projets

select 