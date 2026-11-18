<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vapaa tekstihaku, batch 2: Alue, Arvoalue and Rakennus sync triggers into search_index.
 * SQL is kept PostgreSQL 10 compatible (EXECUTE PROCEDURE, no generated columns).
 */
class VapaatekstihakuBatch2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Alue
        DB::statement("
            CREATE FUNCTION search_index_upsert_alue(a alue)
            RETURNS void LANGUAGE sql AS $$
                INSERT INTO search_index (global_id, item_type, domain, source_id, title, tunnus, kunta, body, search_vector, updated_at)
                SELECT 'alue:' || a.id, 'alue', 'RAK', a.id,
                       a.nimi, NULL,
                       (SELECT string_agg(DISTINCT ku.nimi, ', ' ORDER BY ku.nimi)
                          FROM alue_kyla ak
                          JOIN kyla ky ON ky.id = ak.kyla_id
                          JOIN kunta ku ON ku.id = ky.kunta_id
                         WHERE ak.alue_id = a.id),
                       f.body, build_search_vector(a.nimi, f.body), now()
                FROM (SELECT concat_ws(' ', a.historia, a.maisema, a.nykytila, a.lisatiedot, a.lahteet) AS body) f
                ON CONFLICT (global_id) DO UPDATE SET
                    title = EXCLUDED.title, tunnus = EXCLUDED.tunnus, kunta = EXCLUDED.kunta,
                    body = EXCLUDED.body, search_vector = EXCLUDED.search_vector, updated_at = EXCLUDED.updated_at;
            $$
        ");

        DB::statement("
            CREATE FUNCTION sync_alue_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    DELETE FROM search_index WHERE global_id = 'alue:' || OLD.id;
                ELSIF NEW.poistettu IS NOT NULL THEN
                    DELETE FROM search_index WHERE global_id = 'alue:' || NEW.id;
                ELSE
                    PERFORM search_index_upsert_alue(NEW);
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for alue: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER alue_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON alue
            FOR EACH ROW EXECUTE PROCEDURE sync_alue_search()
        ");

        DB::statement("
            CREATE FUNCTION sync_alue_kyla_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP IN ('UPDATE', 'DELETE') THEN
                    PERFORM search_index_upsert_alue(a) FROM alue a
                    WHERE a.id = OLD.alue_id AND a.poistettu IS NULL;
                END IF;
                IF TG_OP IN ('INSERT', 'UPDATE') THEN
                    PERFORM search_index_upsert_alue(a) FROM alue a
                    WHERE a.id = NEW.alue_id AND a.poistettu IS NULL;
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for alue_kyla: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER alue_kyla_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON alue_kyla
            FOR EACH ROW EXECUTE PROCEDURE sync_alue_kyla_search()
        ");

        // Arvoalue
        DB::statement("
            CREATE FUNCTION search_index_upsert_arvoalue(a arvoalue)
            RETURNS void LANGUAGE sql AS $$
                INSERT INTO search_index (global_id, item_type, domain, source_id, title, tunnus, kunta, body, search_vector, updated_at)
                SELECT 'arvoalue:' || a.id, 'arvoalue', 'RAK', a.id,
                       concat_ws(' – ', nullif(al.nimi, ''), coalesce(nullif(a.nimi, ''), a.inventointinumero::text)),
                       NULL,
                       (SELECT string_agg(DISTINCT ku.nimi, ', ' ORDER BY ku.nimi)
                          FROM arvoalue_kyla ak
                          JOIN kyla ky ON ky.id = ak.kyla_id
                          JOIN kunta ku ON ku.id = ky.kunta_id
                         WHERE ak.arvoalue_id = a.id),
                       f.body, build_search_vector(a.nimi, f.body), now()
                FROM (SELECT concat_ws(' ', a.kuvaus, a.yhteenveto) AS body) f
                LEFT JOIN alue al ON al.id = a.alue_id
                ON CONFLICT (global_id) DO UPDATE SET
                    title = EXCLUDED.title, tunnus = EXCLUDED.tunnus, kunta = EXCLUDED.kunta,
                    body = EXCLUDED.body, search_vector = EXCLUDED.search_vector, updated_at = EXCLUDED.updated_at;
            $$
        ");

        DB::statement("
            CREATE FUNCTION sync_arvoalue_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    DELETE FROM search_index WHERE global_id = 'arvoalue:' || OLD.id;
                ELSIF NEW.poistettu IS NOT NULL THEN
                    DELETE FROM search_index WHERE global_id = 'arvoalue:' || NEW.id;
                ELSE
                    PERFORM search_index_upsert_arvoalue(NEW);
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for arvoalue: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER arvoalue_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON arvoalue
            FOR EACH ROW EXECUTE PROCEDURE sync_arvoalue_search()
        ");

        DB::statement("
            CREATE FUNCTION sync_arvoalue_kyla_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP IN ('UPDATE', 'DELETE') THEN
                    PERFORM search_index_upsert_arvoalue(a) FROM arvoalue a
                    WHERE a.id = OLD.arvoalue_id AND a.poistettu IS NULL;
                END IF;
                IF TG_OP IN ('INSERT', 'UPDATE') THEN
                    PERFORM search_index_upsert_arvoalue(a) FROM arvoalue a
                    WHERE a.id = NEW.arvoalue_id AND a.poistettu IS NULL;
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for arvoalue_kyla: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER arvoalue_kyla_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON arvoalue_kyla
            FOR EACH ROW EXECUTE PROCEDURE sync_arvoalue_kyla_search()
        ");

        // An Alue rename changes its Arvoalueet's titles
        DB::statement("
            CREATE FUNCTION sync_alue_arvoalue_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                PERFORM search_index_upsert_arvoalue(a) FROM arvoalue a
                WHERE a.alue_id = NEW.id AND a.poistettu IS NULL;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for alue arvoalueet: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER alue_arvoalue_search_sync
            AFTER UPDATE ON alue
            FOR EACH ROW
            WHEN (OLD.nimi IS DISTINCT FROM NEW.nimi)
            EXECUTE PROCEDURE sync_alue_arvoalue_search()
        ");

        // Rakennus
        DB::statement("
            CREATE FUNCTION search_index_upsert_rakennus(r rakennus)
            RETURNS void LANGUAGE sql AS $$
                INSERT INTO search_index (global_id, item_type, domain, source_id, title, tunnus, kunta, body, search_vector, updated_at)
                SELECT 'rakennus:' || r.id, 'rakennus', 'RAK', r.id,
                       nullif(concat_ws(' – ',
                              coalesce(nullif(ki.nimi, ''), ki.kiinteistotunnus),
                              nullif(concat_ws(' ', r.inventointinumero,
                                     (SELECT string_agg(rt.nimi_fi, ', ' ORDER BY rt.nimi_fi)
                                        FROM rakennus_rakennustyyppi rrt
                                        JOIN rakennustyyppi rt ON rt.id = rrt.rakennustyyppi_id
                                       WHERE rrt.rakennus_id = r.id AND rt.poistettu IS NULL)), '')), ''),
                       ki.kiinteistotunnus,
                       (SELECT ku.nimi FROM kyla ky JOIN kunta ku ON ku.id = ky.kunta_id WHERE ky.id = ki.kyla_id),
                       f.body, build_search_vector(NULL, f.body), now()
                FROM (SELECT concat_ws(' ', r.rakennustyyppi_kuvaus, r.erityispiirteet, r.rakennushistoria,
                                       r.sisatilakuvaus, r.muut_tiedot) AS body) f
                LEFT JOIN kiinteisto ki ON ki.id = r.kiinteisto_id
                ON CONFLICT (global_id) DO UPDATE SET
                    title = EXCLUDED.title, tunnus = EXCLUDED.tunnus, kunta = EXCLUDED.kunta,
                    body = EXCLUDED.body, search_vector = EXCLUDED.search_vector, updated_at = EXCLUDED.updated_at;
            $$
        ");

        // Index or remove: same rule as the Rakennus list (live, with a live Kiinteistö that has a kylä)
        DB::statement("
            CREATE FUNCTION search_index_refresh_rakennus(r rakennus)
            RETURNS void LANGUAGE plpgsql AS $$
            BEGIN
                IF r.poistettu IS NULL AND EXISTS (
                    SELECT 1 FROM kiinteisto ki
                    WHERE ki.id = r.kiinteisto_id AND ki.poistettu IS NULL AND ki.kyla_id IS NOT NULL
                ) THEN
                    PERFORM search_index_upsert_rakennus(r);
                ELSE
                    DELETE FROM search_index WHERE global_id = 'rakennus:' || r.id;
                END IF;
            END;
            $$
        ");

        DB::statement("
            CREATE FUNCTION sync_rakennus_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    DELETE FROM search_index WHERE global_id = 'rakennus:' || OLD.id;
                ELSE
                    PERFORM search_index_refresh_rakennus(NEW);
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for rakennus: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER rakennus_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON rakennus
            FOR EACH ROW EXECUTE PROCEDURE sync_rakennus_search()
        ");

        DB::statement("
            CREATE FUNCTION sync_kiinteisto_rakennus_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                PERFORM search_index_refresh_rakennus(r) FROM rakennus r WHERE r.kiinteisto_id = NEW.id;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for kiinteisto rakennukset: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER kiinteisto_rakennus_search_sync
            AFTER UPDATE ON kiinteisto
            FOR EACH ROW
            WHEN (OLD.nimi IS DISTINCT FROM NEW.nimi
               OR OLD.kiinteistotunnus IS DISTINCT FROM NEW.kiinteistotunnus
               OR OLD.kyla_id IS DISTINCT FROM NEW.kyla_id
               OR OLD.poistettu IS DISTINCT FROM NEW.poistettu)
            EXECUTE PROCEDURE sync_kiinteisto_rakennus_search()
        ");

        DB::statement("
            CREATE FUNCTION sync_rakennus_rakennustyyppi_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP IN ('UPDATE', 'DELETE') THEN
                    PERFORM search_index_refresh_rakennus(r) FROM rakennus r WHERE r.id = OLD.rakennus_id;
                END IF;
                IF TG_OP IN ('INSERT', 'UPDATE') THEN
                    PERFORM search_index_refresh_rakennus(r) FROM rakennus r WHERE r.id = NEW.rakennus_id;
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for rakennus_rakennustyyppi: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER rakennus_rakennustyyppi_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON rakennus_rakennustyyppi
            FOR EACH ROW EXECUTE PROCEDURE sync_rakennus_rakennustyyppi_search()
        ");

        // Backfill (re-runnable; also how to refresh stale copies)
        DB::statement("
            DO $$ BEGIN
                PERFORM search_index_upsert_alue(a) FROM alue a WHERE a.poistettu IS NULL;
                PERFORM search_index_upsert_arvoalue(a) FROM arvoalue a WHERE a.poistettu IS NULL;
                PERFORM search_index_refresh_rakennus(r) FROM rakennus r WHERE r.poistettu IS NULL;
            END $$
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("DROP TRIGGER IF EXISTS rakennus_rakennustyyppi_search_sync ON rakennus_rakennustyyppi");
        DB::statement("DROP TRIGGER IF EXISTS kiinteisto_rakennus_search_sync ON kiinteisto");
        DB::statement("DROP TRIGGER IF EXISTS rakennus_search_sync ON rakennus");
        DB::statement("DROP TRIGGER IF EXISTS alue_arvoalue_search_sync ON alue");
        DB::statement("DROP TRIGGER IF EXISTS arvoalue_kyla_search_sync ON arvoalue_kyla");
        DB::statement("DROP TRIGGER IF EXISTS arvoalue_search_sync ON arvoalue");
        DB::statement("DROP TRIGGER IF EXISTS alue_kyla_search_sync ON alue_kyla");
        DB::statement("DROP TRIGGER IF EXISTS alue_search_sync ON alue");
        DB::statement("DROP FUNCTION IF EXISTS sync_rakennus_rakennustyyppi_search()");
        DB::statement("DROP FUNCTION IF EXISTS sync_kiinteisto_rakennus_search()");
        DB::statement("DROP FUNCTION IF EXISTS sync_rakennus_search()");
        DB::statement("DROP FUNCTION IF EXISTS search_index_refresh_rakennus(rakennus)");
        DB::statement("DROP FUNCTION IF EXISTS search_index_upsert_rakennus(rakennus)");
        DB::statement("DROP FUNCTION IF EXISTS sync_alue_arvoalue_search()");
        DB::statement("DROP FUNCTION IF EXISTS sync_arvoalue_kyla_search()");
        DB::statement("DROP FUNCTION IF EXISTS sync_arvoalue_search()");
        DB::statement("DROP FUNCTION IF EXISTS search_index_upsert_arvoalue(arvoalue)");
        DB::statement("DROP FUNCTION IF EXISTS sync_alue_kyla_search()");
        DB::statement("DROP FUNCTION IF EXISTS sync_alue_search()");
        DB::statement("DROP FUNCTION IF EXISTS search_index_upsert_alue(alue)");
        DB::statement("DELETE FROM search_index WHERE item_type IN ('alue', 'arvoalue', 'rakennus')");
    }
}
