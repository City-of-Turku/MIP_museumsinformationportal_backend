select arvoalue.id, arvoalue.nimi, arvoalue.kuvaus, arvoalue.inventointinumero, arvoalue.yhteenveto, arvoalue.keskipiste, arvoalue.aluerajaus, arvoalue.alue_id, at.nimi_fi as arvoluokka from arvoalue
left join arvotustyyppi at on arvoalue.arvotustyyppi_id = at.id
join arvoalue_kyla on arvoalue.id = arvoalue_kyla.arvoalue_id
join kyla on arvoalue_kyla.kyla_id = kyla.id
join kunta ON kyla.kunta_id = kunta.id
WHERE arvoalue.poistettu IS null
AND arvoalue.id::text ILIKE '%arvoalue_id%'::text
AND arvoalue.alue_id::text ILIKE '%alue_id%'::text
AND kyla.nimi ILIKE '%%kyla_nimi%%'
AND kyla.kylanumero ILIKE '%kyla_numero%'
AND kunta.nimi ILIKE '%kunta_nimi%%'
AND kunta.kuntanumero ILIKE '%kunta_numero%'
