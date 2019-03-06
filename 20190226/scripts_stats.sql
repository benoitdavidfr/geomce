-- REQUETES CAE - 2019-02-18 --

-- nombre de mesures par année de décision,
copy
(select cast(extract(year from date_decision) as bigint) as annee, count(m.id) as "nb_mesures" from app.procedures pr
inner join mesures m on m.procedure_id=pr.id
inner join mesure_categories mc on mc.id=m.mesure_categorie_id
where substring(mc.code,1,1)='C'
group by annee
order by annee desc)
to '/tmp/nb_mesures_annee_decision.csv' with csv header delimiter ';';



copy
(
-- nombre de mesures dessinées précisément (ndlr : ayant a minima une emprise)
(select 'Mesures ayant a minima une emprise',count(distinct mesure_id) as "total" from mesures m 
inner join emprises_mesures em on em.mesure_id=m.id
inner join mesure_categories mc on mc.id=m.mesure_categorie_id
where substring(mc.code,1,1)='C'
)
union
(
--nombre de mesures géolocalisées à la commune
select 'Mesures géolocalisées uniquement à la commune',count(distinct mesure_id) as "total" from mesures m 
inner join communes_mesures cm on cm.mesure_id=m.id
inner join mesure_categories mc on mc.id=m.mesure_categorie_id
where substring(mc.code,1,1)='C'
and m.id not in (select distinct mesure_id from emprises_mesures)
)
union
(
--nombre de mesures dont le durée est renseignée,
select 'Mesures dont la durée est renseignée', count(*) as "total"  from mesures m
inner join mesure_categories mc on mc.id=m.mesure_categorie_id
where substring(mc.code,1,1)='C'
and duree_prescrite_realisation is not null
)
union
(
--nombre total de projets
select 'Projets ', count(*) as "total"  from projets
)
)
to '/tmp/mesures_requetes.csv' with csv header delimiter ';';

--niveau de classification des mesures
copy
(
select 
case 
when char_length(mc.code)='1' then 'Classe'
when char_length(mc.code)='2' then 'Type'
else 'sous-categorie'
end
as "code", count(m.id) from mesures m 
inner join mesure_categories mc on mc.id=m.mesure_categorie_id
where substring(mc.code,1,1)='C'
group by char_length(mc.code)
)
to '/tmp/mesures_repartition_niveau.csv' with csv header delimiter ';';

--select * from app.mesure_emprise where classe is null

