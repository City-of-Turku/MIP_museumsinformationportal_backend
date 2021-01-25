select
k.id as kohde_id,
kkk.kunta,
kkk.kyla,
k.muinaisjaannostunnus,
k.nimi,
k.muutnimet,
kohdeajoitus.ajoitustarkenne,
kohdetyyppi.tyyppi,
kohdetyyppi.tarkenne,
--TODO: kohteen laajuus ja laajuuden arviointiperuste???
k.lukumaara,
kohdelaji.nimi_fi as kohdelaji,
k.etaisyystieto,
--TODO: sijaintikuvaus???
k.korkeus_min,
k.korkeus_max,
k.koordselite,
k.peruskarttanimi,
k.peruskarttanumero,
sijainti.sijainti,
sijainti.tuhoutunut,
geometrytype(sijainti) as sijainti_tyyppi,
on_alakohde
from ark_kohde k
left join (
    select ak.id as kohde_id, aks.sijainti as sijainti, aks.tuhoutunut as tuhoutunut, geometrytype(sijainti) as sijainti_tyyppi, false as on_alakohde
    from ark_kohde ak
    left join ark_kohde_sijainti aks on aks.kohde_id = ak.id
    union
    select aka.ark_kohde_id as kohde_id, aas.sijainti as sijainti, false as tuhoutunut, geometrytype(sijainti) as sijainti_tyyppi, true as on_alakohde
    from ark_kohde_alakohde aka
    left join ark_alakohde_sijainti aas on aas.ark_kohde_alakohde_id = aka.id
) sijainti on (sijainti.kohde_id = k.id)
left join (
    select akk.ark_kohde_id, string_agg(ku.nimi, ', ') as kunta, string_agg(ky.nimi, ', ') as kyla, string_agg(ku.nimi_se, ', ') as kunta_se, string_agg(ky.nimi_se, ', ') as kyla_se
    from ark_kohde_kuntakyla akk
    left join kunta ku on akk.kunta_id = ku.id
    left join kyla ky on akk.kyla_id = ky.id
    group by akk.ark_kohde_id
) kkk on (kkk.ark_kohde_id = k.id)
left join (
    select kt.ark_kohde_id, string_agg(tyyppi.nimi_fi, ', ') as tyyppi, string_agg(tyyppi.nimi_se, ', ') as tyyppi_se, string_agg(tark.nimi_fi, ', ') as tarkenne, string_agg(tark.nimi_se, ', ') as tarkenne_se
    from ark_kohde_tyyppi kt
    left join ark_kohdetyyppi tyyppi on tyyppi.id = kt.tyyppi_id
    left join ark_kohdetyyppitarkenne tark on tark.id = kt.tyyppitarkenne_id
    group by kt.ark_kohde_id
) kohdetyyppi on (kohdetyyppi.ark_kohde_id = k.id)
left join (
    select ka.ark_kohde_id, string_agg(ajoitus.nimi_fi || ' - ' || tark.nimi_fi, ', ') as ajoitustarkenne, string_agg(ajoitus.nimi_se || ' - ' || tark.nimi_se, ', ') as ajoitustarkenne_se
    from ark_kohde_ajoitus ka
    left join ajoitus on ajoitus.id = ka.ajoitus_id
    left join ajoitustarkenne tark on tark.id = ka.ajoitustarkenne_id
    group by ka.ark_kohde_id
) kohdeajoitus on (kohdeajoitus.ark_kohde_id = k.id)
left join ark_kohdelaji kohdelaji on kohdelaji.id = k.ark_kohdelaji_id
left join rajaustarkkuus on rajaustarkkuus.id = k.rajaustarkkuus_id
where k.poistettu is null
and k.id::text ILIKE '%ark_kohde_id%'::text