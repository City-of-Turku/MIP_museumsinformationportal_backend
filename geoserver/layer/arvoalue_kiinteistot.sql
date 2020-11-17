select ki.id, ki.nimi, ki.kiinteistotunnus, at.nimi_fi as arvoluokka, kiinteiston_sijainti, aa.id as arvoalue_id
from kiinteisto ki
join arvoalue aa on (ST_Within(ki.kiinteiston_sijainti, aa.aluerajaus) and aa.id::text ILIKE '%arvoalue_id%'::text)
left join arvotustyyppi at on ki.arvotustyyppi_id = at.id
where ki.poistettu is null
