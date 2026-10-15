<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vapaa tekstihaku, batch 3: Tutkimus and Löytö sync triggers into search_index.
 * SQL is kept PostgreSQL 10 compatible (EXECUTE PROCEDURE, no generated columns).
 */
class VapaatekstihakuBatch3 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Tutkimus
        DB::statement("
            CREATE FUNCTION search_index_upsert_ark_tutkimus(t ark_tutkimus)
            RETURNS void LANGUAGE sql AS $$
                INSERT INTO search_index (global_id, item_type, domain, source_id, title, tunnus, kunta, body, search_vector, updated_at)
                SELECT 'ark_tutkimus:' || t.id, 'ark_tutkimus', 'ARK', t.id,
                       t.nimi, t.tutkimuksen_lyhenne,
                       -- own kunnat, else its Kohteet's kunnat (what most Tutkimus windows show)
                       coalesce(
                           (SELECT string_agg(DISTINCT ku.nimi, ', ' ORDER BY ku.nimi)
                              FROM ark_tutkimus_kuntakyla tk
                              JOIN kunta ku ON ku.id = tk.kunta_id
                             WHERE tk.ark_tutkimus_id = t.id),
                           (SELECT string_agg(DISTINCT ku.nimi, ', ' ORDER BY ku.nimi)
                              FROM ark_kohde_tutkimus kt
                              JOIN ark_kohde_kuntakyla kk ON kk.ark_kohde_id = kt.ark_kohde_id
                              JOIN kunta ku ON ku.id = kk.kunta_id
                             WHERE kt.ark_tutkimus_id = t.id)),
                       NULL, build_search_vector(concat_ws(' ', t.nimi, t.tutkimuksen_lyhenne), NULL), now()
                ON CONFLICT (global_id) DO UPDATE SET
                    title = EXCLUDED.title, tunnus = EXCLUDED.tunnus, kunta = EXCLUDED.kunta,
                    body = EXCLUDED.body, search_vector = EXCLUDED.search_vector, updated_at = EXCLUDED.updated_at;
            $$
        ");

        DB::statement("
            CREATE FUNCTION sync_ark_tutkimus_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    DELETE FROM search_index WHERE global_id = 'ark_tutkimus:' || OLD.id;
                ELSIF NEW.poistettu IS NOT NULL THEN
                    DELETE FROM search_index WHERE global_id = 'ark_tutkimus:' || NEW.id;
                ELSE
                    PERFORM search_index_upsert_ark_tutkimus(NEW);
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for ark_tutkimus: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER ark_tutkimus_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON ark_tutkimus
            FOR EACH ROW EXECUTE PROCEDURE sync_ark_tutkimus_search()
        ");

        DB::statement("
            CREATE FUNCTION sync_ark_tutkimus_kuntakyla_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP IN ('UPDATE', 'DELETE') THEN
                    PERFORM search_index_upsert_ark_tutkimus(t) FROM ark_tutkimus t
                    WHERE t.id = OLD.ark_tutkimus_id AND t.poistettu IS NULL;
                END IF;
                IF TG_OP IN ('INSERT', 'UPDATE') THEN
                    PERFORM search_index_upsert_ark_tutkimus(t) FROM ark_tutkimus t
                    WHERE t.id = NEW.ark_tutkimus_id AND t.poistettu IS NULL;
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for ark_tutkimus_kuntakyla: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER ark_tutkimus_kuntakyla_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON ark_tutkimus_kuntakyla
            FOR EACH ROW EXECUTE PROCEDURE sync_ark_tutkimus_kuntakyla_search()
        ");

        // Kunta fallback: Tutkimus ↔ Kohde links
        DB::statement("
            CREATE FUNCTION sync_ark_kohde_tutkimus_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP IN ('UPDATE', 'DELETE') THEN
                    PERFORM search_index_upsert_ark_tutkimus(t) FROM ark_tutkimus t
                    WHERE t.id = OLD.ark_tutkimus_id AND t.poistettu IS NULL;
                END IF;
                IF TG_OP IN ('INSERT', 'UPDATE') THEN
                    PERFORM search_index_upsert_ark_tutkimus(t) FROM ark_tutkimus t
                    WHERE t.id = NEW.ark_tutkimus_id AND t.poistettu IS NULL;
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for ark_kohde_tutkimus: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER ark_kohde_tutkimus_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON ark_kohde_tutkimus
            FOR EACH ROW EXECUTE PROCEDURE sync_ark_kohde_tutkimus_search()
        ");

        // Kunta fallback: a Kohde's kunnat change → its Tutkimukset
        DB::statement("
            CREATE FUNCTION sync_ark_kohde_kuntakyla_tutkimus_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP IN ('UPDATE', 'DELETE') THEN
                    PERFORM search_index_upsert_ark_tutkimus(t)
                    FROM ark_tutkimus t JOIN ark_kohde_tutkimus kt ON kt.ark_tutkimus_id = t.id
                    WHERE kt.ark_kohde_id = OLD.ark_kohde_id AND t.poistettu IS NULL;
                END IF;
                IF TG_OP IN ('INSERT', 'UPDATE') THEN
                    PERFORM search_index_upsert_ark_tutkimus(t)
                    FROM ark_tutkimus t JOIN ark_kohde_tutkimus kt ON kt.ark_tutkimus_id = t.id
                    WHERE kt.ark_kohde_id = NEW.ark_kohde_id AND t.poistettu IS NULL;
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for ark_kohde_kuntakyla tutkimukset: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER ark_kohde_kuntakyla_tutkimus_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON ark_kohde_kuntakyla
            FOR EACH ROW EXECUTE PROCEDURE sync_ark_kohde_kuntakyla_tutkimus_search()
        ");

        // Löytö
        // The Löytö's Tutkimus, through its yksikkö or (irtolöytö) directly through its tutkimusalue
        DB::statement("
            CREATE FUNCTION search_ark_loyto_tutkimus_id(l ark_loyto)
            RETURNS bigint LANGUAGE sql STABLE AS $$
                SELECT ta.ark_tutkimus_id::bigint  -- the column is integer; PG<13 rejects a mismatch
                FROM ark_tutkimusalue ta
                WHERE ta.id = coalesce(
                    (SELECT y.ark_tutkimusalue_id FROM ark_tutkimusalue_yksikko y
                      WHERE y.id = l.ark_tutkimusalue_yksikko_id),
                    l.ark_tutkimusalue_id)
            $$
        ");

        DB::statement("
            CREATE FUNCTION search_index_upsert_ark_loyto(l ark_loyto)
            RETURNS void LANGUAGE sql AS $$
                INSERT INTO search_index (global_id, item_type, domain, source_id, title, tunnus, kunta, body, search_vector, updated_at)
                SELECT 'ark_loyto:' || l.id, 'ark_loyto', 'ARK', l.id,
                       l.luettelointinumero,
                       (SELECT t.tutkimuksen_lyhenne FROM ark_tutkimus t WHERE t.id = search_ark_loyto_tutkimus_id(l)),
                       NULL,
                       l.kuvaus, build_search_vector(l.luettelointinumero, l.kuvaus), now()
                ON CONFLICT (global_id) DO UPDATE SET
                    title = EXCLUDED.title, tunnus = EXCLUDED.tunnus, kunta = EXCLUDED.kunta,
                    body = EXCLUDED.body, search_vector = EXCLUDED.search_vector, updated_at = EXCLUDED.updated_at;
            $$
        ");

        DB::statement("
            CREATE FUNCTION sync_ark_loyto_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    DELETE FROM search_index WHERE global_id = 'ark_loyto:' || OLD.id;
                ELSIF NEW.poistettu IS NOT NULL THEN
                    DELETE FROM search_index WHERE global_id = 'ark_loyto:' || NEW.id;
                ELSE
                    PERFORM search_index_upsert_ark_loyto(NEW);
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for ark_loyto: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER ark_loyto_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON ark_loyto
            FOR EACH ROW EXECUTE PROCEDURE sync_ark_loyto_search()
        ");

        // A Tutkimus's lyhenne is shown on its Löydöt
        DB::statement("
            CREATE FUNCTION sync_ark_tutkimus_loyto_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                PERFORM search_index_upsert_ark_loyto(l)
                FROM ark_loyto l
                LEFT JOIN ark_tutkimusalue_yksikko y ON y.id = l.ark_tutkimusalue_yksikko_id
                JOIN ark_tutkimusalue ta ON ta.id = coalesce(y.ark_tutkimusalue_id, l.ark_tutkimusalue_id)
                WHERE ta.ark_tutkimus_id = NEW.id AND l.poistettu IS NULL;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for ark_tutkimus loydot: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER ark_tutkimus_loyto_search_sync
            AFTER UPDATE ON ark_tutkimus
            FOR EACH ROW
            WHEN (OLD.tutkimuksen_lyhenne IS DISTINCT FROM NEW.tutkimuksen_lyhenne)
            EXECUTE PROCEDURE sync_ark_tutkimus_loyto_search()
        ");

        // Backfill (re-runnable; also how to refresh stale copies)
        DB::statement("
            DO $$ BEGIN
                PERFORM search_index_upsert_ark_tutkimus(t) FROM ark_tutkimus t WHERE t.poistettu IS NULL;
                PERFORM search_index_upsert_ark_loyto(l) FROM ark_loyto l WHERE l.poistettu IS NULL;
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
        DB::statement("DROP TRIGGER IF EXISTS ark_tutkimus_loyto_search_sync ON ark_tutkimus");
        DB::statement("DROP TRIGGER IF EXISTS ark_loyto_search_sync ON ark_loyto");
        DB::statement("DROP TRIGGER IF EXISTS ark_kohde_kuntakyla_tutkimus_search_sync ON ark_kohde_kuntakyla");
        DB::statement("DROP TRIGGER IF EXISTS ark_kohde_tutkimus_search_sync ON ark_kohde_tutkimus");
        DB::statement("DROP TRIGGER IF EXISTS ark_tutkimus_kuntakyla_search_sync ON ark_tutkimus_kuntakyla");
        DB::statement("DROP TRIGGER IF EXISTS ark_tutkimus_search_sync ON ark_tutkimus");
        DB::statement("DROP FUNCTION IF EXISTS sync_ark_tutkimus_loyto_search()");
        DB::statement("DROP FUNCTION IF EXISTS sync_ark_loyto_search()");
        DB::statement("DROP FUNCTION IF EXISTS search_index_upsert_ark_loyto(ark_loyto)");
        DB::statement("DROP FUNCTION IF EXISTS search_ark_loyto_tutkimus_id(ark_loyto)");
        DB::statement("DROP FUNCTION IF EXISTS sync_ark_kohde_kuntakyla_tutkimus_search()");
        DB::statement("DROP FUNCTION IF EXISTS sync_ark_kohde_tutkimus_search()");
        DB::statement("DROP FUNCTION IF EXISTS sync_ark_tutkimus_kuntakyla_search()");
        DB::statement("DROP FUNCTION IF EXISTS sync_ark_tutkimus_search()");
        DB::statement("DROP FUNCTION IF EXISTS search_index_upsert_ark_tutkimus(ark_tutkimus)");
        DB::statement("DELETE FROM search_index WHERE item_type IN ('ark_tutkimus', 'ark_loyto')");
    }
}
