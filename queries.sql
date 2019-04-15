title: croisement entre mcecpii20190226direct & mceigngp20190226direct

select cpii.num, gp.num, ST_Intersection(cpii.geom, gp.geom)
from mcecpii20190226direct cpii, mceigngp20190226direct gp
where cpii.geom && gp.geom

ERROR:  Error performing intersection: TopologyException: Input geom 0 is invalid: Self-intersection at or near point 269028.62552980316 5100614.6239074478 at 269028.62552980316 5100614.6239074478


-- création de tables simplifiées et agrégées par projet et communes
create table cpii as
select projet, communes, sum(ST_Area(geom))/10000 area_ha, sum(ST_Length(geom))/1000 as length_km
from mcecpii20190226direct
group by projet, communes;

-- table gp simplifiée
drop table gp;
create table gp as
select projet, communes, sum(ST_Area(geom))/10000 area_ha, sum(ST_Length(geom))/1000 as length_km
from mceigngp20190226direct
group by projet, communes;

-- comparaison des 2 tables pour les projets communs
-- projets surfaciques ayant des surfaces différentes
select cpii.projet, cpii.communes, cpii.area_ha cpii_area_ha, gp.area_ha gp_area_ha
from cpii, gp
where cpii.projet=gp.projet and cpii.communes=gp.communes
  and cpii.area_ha + gp.area_ha <> 0 
  and abs((cpii.area_ha-gp.area_ha)/(cpii.area_ha+gp.area_ha)/2) > 0.01

-- projets linéaires ayant des longueurs différentes
select cpii.projet, cpii.communes, cpii.length_km cpii_length_km, gp.length_km gp_length_km
from cpii, gp
where cpii.projet=gp.projet and cpii.communes=gp.communes
  and cpii.length_km + gp.length_km <> 0 
  and abs((cpii.length_km-gp.length_km)/(cpii.length_km+gp.length_km)/2) > 0.01


select sum(area_ha) area_ha from cpii;
area_ha: 29428.3427626876

select sum(area_ha) area_ha from gp;
area_ha: 24297.7717337142

-- mesures cpii absentes de gp
select cpii.projet, cpii.communes, cpii.area_ha, gp.area_ha, cpii.length_km, gp.length_km
from cpii left join gp on cpii.projet=gp.projet and cpii.communes=gp.communes
where gp.area_ha is null or gp.length_km is null

résultat:
  - projet: Ligne électrique Ouest (LEO)
    communes: {"LES AVIRONS","LES TROIS BASSINS","L ETANG SALE","ST LEU","ST LOUIS","ST PAUL"}
    area_ha: 0
    length_km: 11.2
  - projet:  Renappage de zone humide
    communes: {LONGUEVILLE}
    area_ha: 0.0524327871269455
    length_km: 0
  - projet: Création du domaine Center Parcs du bois aux Daims Trois Moutiers (86)
    communes: {NULL}	
    area_ha: 21.1667257207436
    length_km: 0

-- sommes des surfaces et longueurs des mesures cpii absentes de gp
select sum(cpii.area_ha), sum(cpii.length_km)
from cpii left join gp on cpii.projet=gp.projet and cpii.communes=gp.communes
where gp.area_ha is null or gp.length_km is null

résultat:
  area_ha: 21.2
  length_km: 11.2

-- mesures gp ajoutées à cpii
select gp.projet, gp.communes, gp.area_ha, cpii.area_ha, gp.length_km, cpii.length_km
from gp left join cpii on cpii.projet=gp.projet and cpii.communes=gp.communes
where cpii.area_ha is null or cpii.length_km is null

résultat:
  - projet: Renge de zone humide
    communes: {LONGUEVILLE}	
    area_ha: 0.0524327871610954


