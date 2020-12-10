select ta.id, ta.sijainti, ta.sijainti_piste, t.id as tutkimus_id, tl.nimi_fi as tutkimuslaji
from ark_tutkimusalue ta
left join ark_tutkimus t on t.id = ta.ark_tutkimus_id
left join ark_tutkimuslaji tl on tl.id = t.ark_tutkimuslaji_id
where ta.poistettu is null
and (ta.sijainti is not null or ta.sijainti_piste is not null)
and t.id::text ILIKE '%tutkimus_id%'::text
