SELECT kiinteisto.id, kiinteisto.kiinteistotunnus, kiinteisto.nimi, kiinteisto.kiinteiston_sijainti, kiinteisto.osoite, kiinteisto.postinumero, kiinteisto.paikkakunta, kiinteisto.aluetyyppi, kiinteisto.lisatiedot, kiinteisto.palstanumero,
at.nimi_fi as arvoluokka
FROM kiinteisto
left join arvotustyyppi at on kiinteisto.arvotustyyppi_id = at.id
JOIN kyla ON kiinteisto.kyla_id = kyla.id 
JOIN kunta ON kyla.kunta_id = kunta.id

WHERE kiinteisto.poistettu IS null
AND kiinteisto.id::text ILIKE '%kiinteisto_id%'::text

AND kunta.nimi ILIKE '%kunta_nimi%%'
AND kunta.kuntanumero ILIKE '%kunta_numero%'

AND kyla.nimi ILIKE '%%kyla_nimi%%'
AND kyla.kylanumero ILIKE '%kyla_numero%'