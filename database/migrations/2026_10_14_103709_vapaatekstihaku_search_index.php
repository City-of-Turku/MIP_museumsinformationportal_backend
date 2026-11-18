<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vapaa tekstihaku, batch 1: search_index + Kiinteistö and Kohde sync triggers.
 * SQL is kept PostgreSQL 10 compatible (EXECUTE PROCEDURE, no generated columns).
 */
class VapaatekstihakuSearchIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("
            CREATE TABLE search_index (
                global_id     text PRIMARY KEY,
                item_type     text NOT NULL,
                domain        text NOT NULL CHECK (domain IN ('RAK', 'ARK')),
                source_id     bigint NOT NULL,
                title         text,
                tunnus        text,
                kunta         text,
                body          text,
                access_tags   text[] NOT NULL DEFAULT '{}',
                search_vector tsvector NOT NULL,
                updated_at    timestamptz NOT NULL DEFAULT now(),
                UNIQUE (item_type, source_id)
            )
        ");

        DB::statement("CREATE INDEX search_index_vector_gin ON search_index USING GIN (search_vector)");

        DB::statement("
            CREATE FUNCTION build_search_vector(weight_a text, weight_d text)
            RETURNS tsvector LANGUAGE sql IMMUTABLE AS $$
                SELECT setweight(to_tsvector('finnish', coalesce(weight_a, '')), 'A')
                    || setweight(to_tsvector('finnish', coalesce(weight_d, '')), 'D')
            $$
        ");

        // Kiinteistö
        DB::statement("
            CREATE FUNCTION search_index_upsert_kiinteisto(k kiinteisto)
            RETURNS void LANGUAGE sql AS $$
                INSERT INTO search_index (global_id, item_type, domain, source_id, title, tunnus, kunta, body, search_vector, updated_at)
                SELECT 'kiinteisto:' || k.id, 'kiinteisto', 'RAK', k.id,
                       f.title, k.kiinteistotunnus,
                       (SELECT ku.nimi FROM kyla ky JOIN kunta ku ON ku.id = ky.kunta_id WHERE ky.id = k.kyla_id),
                       f.body, build_search_vector(f.weight_a, f.body), now()
                FROM (SELECT coalesce(nullif(k.nimi, ''), k.kiinteistotunnus) AS title,
                             concat_ws(' ', k.nimi, k.kiinteistotunnus)        AS weight_a,
                             concat_ws(' ', k.osoite, k.postinumero, k.lahteet, k.lisatiedot,
                                       k.perustelut_yhteenveto, k.asutushistoria,
                                       k.lahiymparisto, k.pihapiiri)            AS body) f
                ON CONFLICT (global_id) DO UPDATE SET
                    title = EXCLUDED.title, tunnus = EXCLUDED.tunnus, kunta = EXCLUDED.kunta,
                    body = EXCLUDED.body, search_vector = EXCLUDED.search_vector, updated_at = EXCLUDED.updated_at;
            $$
        ");

        DB::statement("
            CREATE FUNCTION sync_kiinteisto_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    DELETE FROM search_index WHERE global_id = 'kiinteisto:' || OLD.id;
                ELSIF NEW.poistettu IS NOT NULL THEN
                    DELETE FROM search_index WHERE global_id = 'kiinteisto:' || NEW.id;
                ELSE
                    PERFORM search_index_upsert_kiinteisto(NEW);
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for kiinteisto: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER kiinteisto_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON kiinteisto
            FOR EACH ROW EXECUTE PROCEDURE sync_kiinteisto_search()
        ");

        // Kohde
        DB::statement("
            CREATE FUNCTION search_index_upsert_ark_kohde(k ark_kohde)
            RETURNS void LANGUAGE sql AS $$
                INSERT INTO search_index (global_id, item_type, domain, source_id, title, tunnus, kunta, body, search_vector, updated_at)
                SELECT 'ark_kohde:' || k.id, 'ark_kohde', 'ARK', k.id,
                       k.nimi, k.muinaisjaannostunnus::text,
                       (SELECT string_agg(DISTINCT ku.nimi, ', ' ORDER BY ku.nimi)
                          FROM ark_kohde_kuntakyla kk JOIN kunta ku ON ku.id = kk.kunta_id
                         WHERE kk.ark_kohde_id = k.id),
                       k.kuvaus, build_search_vector(concat_ws(' ', k.nimi, k.muinaisjaannostunnus), k.kuvaus), now()
                ON CONFLICT (global_id) DO UPDATE SET
                    title = EXCLUDED.title, tunnus = EXCLUDED.tunnus, kunta = EXCLUDED.kunta,
                    body = EXCLUDED.body, search_vector = EXCLUDED.search_vector, updated_at = EXCLUDED.updated_at;
            $$
        ");

        DB::statement("
            CREATE FUNCTION sync_ark_kohde_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    DELETE FROM search_index WHERE global_id = 'ark_kohde:' || OLD.id;
                ELSIF NEW.poistettu IS NOT NULL THEN
                    DELETE FROM search_index WHERE global_id = 'ark_kohde:' || NEW.id;
                ELSE
                    PERFORM search_index_upsert_ark_kohde(NEW);
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for ark_kohde: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER ark_kohde_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON ark_kohde
            FOR EACH ROW EXECUTE PROCEDURE sync_ark_kohde_search()
        ");

        // Kohde's kunnat live in a link table
        DB::statement("
            CREATE FUNCTION sync_ark_kohde_kuntakyla_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP IN ('UPDATE', 'DELETE') THEN
                    PERFORM search_index_upsert_ark_kohde(k) FROM ark_kohde k
                    WHERE k.id = OLD.ark_kohde_id AND k.poistettu IS NULL;
                END IF;
                IF TG_OP IN ('INSERT', 'UPDATE') THEN
                    PERFORM search_index_upsert_ark_kohde(k) FROM ark_kohde k
                    WHERE k.id = NEW.ark_kohde_id AND k.poistettu IS NULL;
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for ark_kohde_kuntakyla: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER ark_kohde_kuntakyla_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON ark_kohde_kuntakyla
            FOR EACH ROW EXECUTE PROCEDURE sync_ark_kohde_kuntakyla_search()
        ");

        // Grant only to the app roles that exist in this environment
        DB::statement("
            DO $$
            DECLARE
                r text;
            BEGIN
                FOREACH r IN ARRAY ARRAY['mip_rw', 'mip_rdr'] LOOP
                    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = r) THEN
                        EXECUTE format('GRANT SELECT, INSERT, UPDATE, DELETE ON search_index TO %I', r);
                    END IF;
                END LOOP;
            END $$
        ");

        // Backfill (re-runnable; also how to refresh stale copies)
        DB::statement("
            DO $$ BEGIN
                PERFORM search_index_upsert_kiinteisto(k) FROM kiinteisto k WHERE k.poistettu IS NULL;
                PERFORM search_index_upsert_ark_kohde(k) FROM ark_kohde k WHERE k.poistettu IS NULL;
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
        DB::statement("DROP TRIGGER IF EXISTS ark_kohde_kuntakyla_search_sync ON ark_kohde_kuntakyla");
        DB::statement("DROP TRIGGER IF EXISTS ark_kohde_search_sync ON ark_kohde");
        DB::statement("DROP TRIGGER IF EXISTS kiinteisto_search_sync ON kiinteisto");
        DB::statement("DROP FUNCTION IF EXISTS sync_ark_kohde_kuntakyla_search()");
        DB::statement("DROP FUNCTION IF EXISTS sync_ark_kohde_search()");
        DB::statement("DROP FUNCTION IF EXISTS sync_kiinteisto_search()");
        DB::statement("DROP FUNCTION IF EXISTS search_index_upsert_ark_kohde(ark_kohde)");
        DB::statement("DROP FUNCTION IF EXISTS search_index_upsert_kiinteisto(kiinteisto)");
        DB::statement("DROP FUNCTION IF EXISTS build_search_vector(text, text)");
        DB::statement("DROP TABLE IF EXISTS search_index");
    }
}
