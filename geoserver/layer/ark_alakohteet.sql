select
ak.id as alakohde_id,
ak.ark_kohde_id as kohde_id,
ak.nimi,
ak.kuvaus,
ak.koordselite,
ak.korkeus_max,
ak.korkeus_min,
kt.nimi_fi as tyyppi,
ktt.nimi_fi as tyyppitarkenne,
sijainti.sijainti,
geometrytype(sijainti) as sijainti_tyyppi,
kohdelaji.nimi_fi as kohdelaji,
true as on_alakohde,
false as tuhoutunut
from ark_kohde_alakohde ak
left join (
    select ka.ark_kohde_alakohde_id, string_agg(ajoitus.nimi_fi || ' - ' || tark.nimi_fi, ', ') as ajoitustarkenne, string_agg(ajoitus.nimi_se || ' - ' || tark.nimi_se, ', ') as ajoitustarkenne_se
    from ark_alakohde_ajoitus ka
    left join ajoitus on ajoitus.id = ka.ajoitus_id
    left join ajoitustarkenne tark on tark.id = ka.ajoitustarkenne_id
    group by ka.ark_kohde_alakohde_id
) kohdeajoitus on (kohdeajoitus.ark_kohde_alakohde_id = ak.id)
left join ark_kohdetyyppi kt on kt.id = ak.ark_kohdetyyppi_id
left join ark_kohdetyyppitarkenne ktt on ktt.id = ak.ark_kohdetyyppitarkenne_id
left join (
    select aka.id as alakohde_id,
    aas.sijainti as sijainti,
    geometrytype(sijainti) as sijainti_tyyppi
    from ark_kohde_alakohde aka
    left join ark_alakohde_sijainti aas on aas.ark_kohde_alakohde_id = aka.id
) sijainti on (sijainti.alakohde_id = ak.id)
left join ark_kohde k on k.id = ak.ark_kohde_id
left join ark_kohdelaji kohdelaji on kohdelaji.id = k.ark_kohdelaji_id
where ak.id::text ILIKE '%ark_alakohde_id%'::text
