select muisto.prikka_id, muisto.muistot_aihe_id as aihe_id, muisto.kuvaus, muisto.tapahtumapaikka as sijainti, aihe.aiheen_vari 
from muistot_muisto muisto
join muistot_aihe aihe
on muisto.muistot_aihe_id = aihe.prikka_id

where muisto.poistettu is false
AND muisto.ilmiannettu IS FALSE
AND muisto.poistettu_mip IS NULL
AND aihe.prikka_id::text ILIKE '%aihe_id%'::text
