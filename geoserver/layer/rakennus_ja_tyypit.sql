select r.id, r.kiinteisto_id, r.inventointinumero,  r.purettu, at.nimi_fi as arvoluokka, SQ_rak_rakennustyypit.rakennustyypit_fi, SQ_rak_rakennustyypit.rakennustyypit_se, r.rakennuksen_sijainti as sijainti 
from rakennus r left join (

    select rrt.rakennus_id, string_agg( rt.nimi_fi, ', ') as rakennustyypit_fi, string_agg(rt.nimi_se, ', ') as rakennustyypit_se
    from rakennus_rakennustyyppi rrt, rakennustyyppi rt
    where rrt.rakennustyyppi_id = rt.id
    group by rrt.rakennus_id

) SQ_rak_rakennustyypit on (r.id = SQ_rak_rakennustyypit.rakennus_id)
left join arvotustyyppi at on r.arvotustyyppi_id = at.id
join kiinteisto
on r.kiinteisto_id = kiinteisto.id
join kyla on kiinteisto.kyla_id = kyla.id
join kunta on kyla.kunta_id = kunta.id

where r.poistettu is null
and r.id::text ilike '%rakennus_id%'::text

and kiinteisto.poistettu is null

and kunta.nimi ilike '%kunta_nimi%%'
and kunta.kuntanumero ilike '%kunta_numero%'

and kyla.nimi ilike '%%kyla_nimi%%'
and kyla.kylanumero ilike '%kyla_numero%'
