title: croisement entre mcecpii20190226direct & mceigngp20190226direct
résumé:
  Une première série de comparaisons est fondée sur le calcul de surface et longueur par projet identifié par son nom
  concaténé avec la liste des communes.
  Je constate:
    - 3 mesures cpii absentes de gp
    - 1 mesure ajoutée dans gp / cpii
    - une liste de mesures pour lesquelles les surfaces ou longueur sont différentes
  voir: http://gexplor.fr/geomce/diff.php
  
  Une seconde série de comparaisons est effectuée sur les champs alpha-numériques.
  On constate de nombreuses différences plus ou moins justifiables:
  - chgt du nom du champ type en type_mesure
  - modification des tableaux PostgreSQL
    - modification systmétique de la syntaxe des tableaux
    - les tableaux sont simplifiés en cas de répétitions
    - la sémantique si_metier/numero_dossier a été modifiée

actionsProposées:
  - sur la géométrie, l'IGN doit corriger ses traitements
  - sur les tableaux, retraitement des exports par le Cerema pour obtenir une syntaxe et une sémantique partagée
  - 2 propositions:
   - utiliser la syntaxe JSON très utilisée et bien connue, cela signgigie que les 3 champs seron encodés en JSON
   - modifier la modélisation si_metier/numero_dossier
     - définir un champ PosgreSQL si_metier contenant une liste de valeurs codé {si_metier}:{numero_dossier}
'

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

-- comparaison des champs alpha-numériques
-- des différences de syntaxe mais aussi de sémantique

drop table mcecpii20190226dcount;
create table mcecpii20190226dcount as
  select md5, count(*) nbre
  from mcecpii20190226direct
  group by md5;
  
drop table mceigngp20190226dcount;
create table mceigngp20190226dcount as
  select md5, count(*) nbre
  from mceigngp20190226direct
  group by md5;
  
select *
from mcecpii20190226dcount cpii
  left join mceigngp20190226dcount igngp 
using (md5)
where igngp.md5 is null or (cpii.nbre<>igngp.nbre);

00e68162d5a7c4992127e9bcd5cb6c5c

select * from mcecpii20190226direct where md5='00e68162d5a7c4992127e9bcd5cb6c5c';
num	mesure_id	projet	categorie	mo	communes	procedure	date_decision	classe	type	cat	sscat	duree	si_metier	numero_dossier	md5	geom
2464
	NULL	Extension de la plateforme de traitement de déchets	ENVIRONNEMENT	Syndicat Mixte TRIFYL	{MONTDRAGON}	Dérogation espèces	2016-02-05	Compensation	Création / Renaturation de milieux	Action concernant tous types de milieux	Création ou renaturation d’habitats et d’habitats favorables aux espèces cibles et à leur guilde (à préciser)	Durée non définie dans l'acte	{S3IC,ONAGRE}	{0068.06388,2015-12-28x-01276}	00e68162d5a7c4992127e9bcd5cb6c5c	
2465
	NULL	Extension de la plateforme de traitement de déchets	ENVIRONNEMENT	Syndicat Mixte TRIFYL	{MONTDRAGON}	Dérogation espèces	2016-02-05	Compensation	Création / Renaturation de milieux	Action concernant tous types de milieux	Création ou renaturation d’habitats et d’habitats favorables aux espèces cibles et à leur guilde (à préciser)	Durée non définie dans l'acte	{S3IC,ONAGRE}	{0068.06388,2015-12-28x-01276}	00e68162d5a7c4992127e9bcd5cb6c5c	


select * from mceigngp20190226direct where projet='Extension de la plateforme de traitement de déchets';
num	mesure_id	projet	categorie	mo	communes	procedure	date_decision	classe	type	cat	sscat	duree	si_metier	numero_dossier	md5	geom
2333
	NULL	Extension de la plateforme de traitement de déchets	ENVIRONNEMENT	Syndicat Mixte TRIFYL	{MONTDRAGON}	Dérogation espèces	2016-02-05	Compensation	Création / Renaturation de milieux	Action concernant tous types de milieux	Création ou renaturation d’habitats et d’habitats favorables aux espèces cibles et à leur guilde (à préciser)	Durée non définie dans l'acte	{"ONAGRE","S3IC"}	{"0068.06388","2015-12-28x-01276"}	846d52546753b8fa106514c79c25f036	
2334
	NULL	Extension de la plateforme de traitement de déchets	ENVIRONNEMENT	Syndicat Mixte TRIFYL	{MONTDRAGON}	Dérogation espèces	2016-02-05	Compensation	Création / Renaturation de milieux	Action concernant tous types de milieux	Création ou renaturation d’habitats et d’habitats favorables aux espèces cibles et à leur guilde (à préciser)	Durée non définie dans l'acte	{"ONAGRE","S3IC"}	{"0068.06388","2015-12-28x-01276"}	846d52546753b8fa106514c79c25f036	
2999
	NULL	Extension de la plateforme de traitement de déchets	ENVIRONNEMENT	Syndicat Mixte TRIFYL	{MONTDRAGON}	Dérogation espèces	2016-02-05	Compensation	Création / Renaturation de milieux	Action concernant tous types de milieux	Création ou renaturation d’habitats et d’habitats favorables aux espèces cibles et à leur guilde (à préciser)	Durée non définie dans l'acte	{"ONAGRE","S3IC"}	{"0068.06388","2015-12-28x-01276"}	846d52546753b8fa106514c79c25f036	


