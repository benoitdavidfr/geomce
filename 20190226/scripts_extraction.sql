-- @CyrilAeck, script actualisé le 26 février 2019

-- a noter que les tables app.classification_erca et app.eurovoc doivent être importées au préalable pour permettre l'export des données.


-- création d'une table de travail (ndlr : correction) des données emprises
create table geo.corrections as select * from geo.emprises;


-- mise à jour des geoms si invalide
update geo.corrections set geom=st_makevalid(geom) where st_isvalid(geom) is false;

-- forcer le srid à 2154 -> Attention, données Réunion à reprojeter par la suite...
-- reste à faire, forcer une reprojection en fonction des DOMTOM
update geo.corrections set geom=st_setsrid(geom, 2154);

-- Creation d'une table des mesures ayant a minima une emprise
create table app.mesure_emprise as
select p.nom as projet, eu.libelle as categorie, mo.nom as mo, array_agg(distinct co.nom) as communes,
pt.nom as procedure, pr.date_decision,
y.classe, y.type, y.cat, y.sscat,
(m.duree_prescrite_realisation:: bigint)||' '||ut.nom||'(s)'  as duree,
tr.nom as "si_metier", rp.numero_dossier,
st_transform(e.geom,4326) as emprises from app.mesures m
inner join app.emprises_mesures em on em.mesure_id=m.id
left join app.communes_mesures cm on cm.mesure_id=m.id
inner join app.communes co on co.id=cm.commune_id
inner join geo.communes gc on gc.commune_id=co.id
inner join ( select id, geom as geom from geo.corrections) e on e.id=em.emprise_id
inner join app.procedures pr on pr.id=m.procedure_id
inner join app.projets p on p.id=pr.projet_id
join app.projet_categories pc on pc.id=p.projet_categorie_id
join app.eurovoc eu on eu.id=pc.id
left join procedure_references rp on rp.procedure_id=pr.id
left join reference_types tr on tr.id=rp.reference_type_id
inner join (select * from classification_erca where classe='Compensation') y on y.id=m.mesure_categorie_id
left join app.maitrise_ouvrage_adresses moa on p.maitrise_ouvrage_adresse_id=moa.id
left join app.maitrise_ouvrages mo on mo.id=moa.maitrise_ouvrage_id
left join app.procedure_types pt on pt.id=pr.procedure_type_id
left join app.unites ut on ut.id=m.duree_unite_id
where m.id in (select distinct mesure_id from app.emprises_mesures)
group by p.nom, eu.libelle, mo.nom,
pt.nom, pr.date_decision,
y.classe, y.type, y.cat, y.sscat,
(m.duree_prescrite_realisation:: bigint)||' '||ut.nom||'(s)',e.geom,
tr.nom , rp.numero_dossier;


-- correction des durées nulles pour la table des mesures ayant a minima une emprise
update app.mesure_emprise 
set duree='Durée non définie dans l''acte' where duree is null;


-- creation de la table des mesures n'ayant pas d'emprise mais rattachées à a minima une commune


create table mesure_commune as
select p.nom as projet, e.libelle as categorie, mo.nom as mo, array_agg(c.nom) as communes,
pt.nom as procedure, pr.date_decision,
y.classe, y.type, y.cat, y.sscat,
(m.duree_prescrite_realisation:: bigint)||' '||ut.nom||'(s)'  as duree,
tr.nom as "si_metier", rp.numero_dossier,
co.centroide from app.mesures m
inner join app.procedures pr on pr.id=m.procedure_id
inner join app.projets p on p.id=pr.projet_id
left join app.projet_categories pc on pc.id=p.projet_categorie_id
left join app.eurovoc e on e.id=pc.id
left join app.maitrise_ouvrage_adresses moa on p.maitrise_ouvrage_adresse_id=moa.id
left join app.maitrise_ouvrages mo on mo.id=moa.maitrise_ouvrage_id
left join app.procedure_types pt on pt.id=pr.procedure_type_id
inner join (select * from classification_erca where classe='Compensation') y on y.id=m.mesure_categorie_id
left join app.mesure_categories mc4 on mc4.id=m.mesure_categorie_id
left join app.unites ut on ut.id=m.duree_unite_id
left join app.communes_mesures cm on cm.mesure_id=m.id
inner join app.communes c on c.id=cm.commune_id
left join (select commune_id, centroide from geo.communes) co on co.commune_id=cm.commune_id
left join procedure_references rp on rp.procedure_id=pr.id
left join reference_types tr on tr.id=rp.reference_type_id
where m.id not in (select distinct mesure_id from app.emprises_mesures)
group by p.nom, e.libelle, mo.nom,
pt.nom, pr.date_decision,
y.classe, y.type, y.cat , y.sscat, (m.duree_prescrite_realisation:: bigint)||' '||ut.nom||'(s)' ,
co.centroide,tr.nom, rp.numero_dossier;


select * from mesure_commune

select * from geo.communes

-- correction des durées nulles pour les mesures rattachées à un périmètre communal
update app.mesure_commune
set duree='Durée non définie dans l''acte' where duree is null;

