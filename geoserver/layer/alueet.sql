select alue.id, alue.nimi, alue.maisema, alue.historia, alue.nykytila, alue.lisatiedot, alue.keskipiste, alue.aluerajaus from alue
join alue_kyla 
on alue.id = alue_kyla.alue_id
join kyla
on alue_kyla.kyla_id = kyla.id

JOIN kunta ON kyla.kunta_id = kunta.id

WHERE alue.poistettu IS null
AND alue.id::text ILIKE '%alue_id%'::text
AND kyla.nimi ILIKE '%%kyla_nimi%%'
AND kyla.kylanumero ILIKE '%kyla_numero%'

AND kunta.nimi ILIKE '%kunta_nimi%%'
AND kunta.kuntanumero ILIKE '%kunta_numero%'
