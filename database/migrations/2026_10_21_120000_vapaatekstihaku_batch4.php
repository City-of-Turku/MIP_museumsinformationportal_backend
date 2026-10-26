<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vapaa tekstihaku, batch 4: RAK and ARK images and files (kuva, ark_kuva, tiedosto, ark_tiedosto)
 * into search_index. One sync trigger per table, no link or parent triggers: every live row is indexed.
 * Same SQL style as batches 1–3 (EXECUTE PROCEDURE, no generated columns).
 */
class VapaatekstihakuBatch4 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Image and file titles are searched at weight B
        DB::statement("
            CREATE FUNCTION build_search_vector(weight_a text, weight_b text, weight_d text)
            RETURNS tsvector LANGUAGE sql IMMUTABLE AS $$
                SELECT setweight(to_tsvector('finnish', coalesce(weight_a, '')), 'A')
                    || setweight(to_tsvector('finnish', coalesce(weight_b, '')), 'B')
                    || setweight(to_tsvector('finnish', coalesce(weight_d, '')), 'D')
            $$
        ");

        // RAK image
        DB::statement("
            CREATE FUNCTION search_index_upsert_kuva(k kuva)
            RETURNS void LANGUAGE sql AS $$
                INSERT INTO search_index (global_id, item_type, domain, source_id, title, tunnus, kunta, body, search_vector, updated_at)
                SELECT 'kuva:' || k.id, 'kuva', 'RAK', k.id,
                       coalesce(nullif(k.otsikko, ''), k.alkuperainen_nimi), NULL, NULL,
                       f.body, build_search_vector(NULL, k.otsikko, f.body), now()
                FROM (SELECT concat_ws(' ', k.kuvaus, k.kuvaaja) AS body) f
                ON CONFLICT (global_id) DO UPDATE SET
                    title = EXCLUDED.title, tunnus = EXCLUDED.tunnus, kunta = EXCLUDED.kunta,
                    body = EXCLUDED.body, search_vector = EXCLUDED.search_vector, updated_at = EXCLUDED.updated_at;
            $$
        ");

        DB::statement("
            CREATE FUNCTION sync_kuva_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    DELETE FROM search_index WHERE global_id = 'kuva:' || OLD.id;
                ELSIF NEW.poistettu IS NOT NULL THEN
                    DELETE FROM search_index WHERE global_id = 'kuva:' || NEW.id;
                ELSE
                    PERFORM search_index_upsert_kuva(NEW);
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for kuva: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER kuva_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON kuva
            FOR EACH ROW EXECUTE PROCEDURE sync_kuva_search()
        ");

        // ARK image: kunta through its own ark_tutkimus_id, same rule as the Tutkimus row (batch 3).
        // Kohde images have no ark_tutkimus_id and get no kunta. Not refreshed when the Tutkimus's kunnat change.
        DB::statement("
            CREATE FUNCTION search_index_upsert_ark_kuva(k ark_kuva)
            RETURNS void LANGUAGE sql AS $$
                INSERT INTO search_index (global_id, item_type, domain, source_id, title, tunnus, kunta, body, search_vector, updated_at)
                SELECT 'ark_kuva:' || k.id, 'ark_kuva', 'ARK', k.id,
                       coalesce(nullif(k.otsikko, ''), k.alkup_tiedostonimi),
                       k.luettelointinumero,
                       coalesce(
                           (SELECT string_agg(DISTINCT ku.nimi, ', ' ORDER BY ku.nimi)
                              FROM ark_tutkimus_kuntakyla tk
                              JOIN kunta ku ON ku.id = tk.kunta_id
                             WHERE tk.ark_tutkimus_id = k.ark_tutkimus_id),
                           (SELECT string_agg(DISTINCT ku.nimi, ', ' ORDER BY ku.nimi)
                              FROM ark_kohde_tutkimus kt
                              JOIN ark_kohde_kuntakyla kk ON kk.ark_kohde_id = kt.ark_kohde_id
                              JOIN kunta ku ON ku.id = kk.kunta_id
                             WHERE kt.ark_tutkimus_id = k.ark_tutkimus_id)),
                       f.body,
                       build_search_vector(NULL, concat_ws(' ', k.otsikko, k.luettelointinumero), f.body), now()
                FROM (SELECT concat_ws(' ', k.kuvaus, k.kuvaaja) AS body) f
                ON CONFLICT (global_id) DO UPDATE SET
                    title = EXCLUDED.title, tunnus = EXCLUDED.tunnus, kunta = EXCLUDED.kunta,
                    body = EXCLUDED.body, search_vector = EXCLUDED.search_vector, updated_at = EXCLUDED.updated_at;
            $$
        ");

        DB::statement("
            CREATE FUNCTION sync_ark_kuva_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    DELETE FROM search_index WHERE global_id = 'ark_kuva:' || OLD.id;
                ELSIF NEW.poistettu IS NOT NULL THEN
                    DELETE FROM search_index WHERE global_id = 'ark_kuva:' || NEW.id;
                ELSE
                    PERFORM search_index_upsert_ark_kuva(NEW);
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for ark_kuva: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER ark_kuva_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON ark_kuva
            FOR EACH ROW EXECUTE PROCEDURE sync_ark_kuva_search()
        ");

        // RAK file
        DB::statement("
            CREATE FUNCTION search_index_upsert_tiedosto(f tiedosto)
            RETURNS void LANGUAGE sql AS $$
                INSERT INTO search_index (global_id, item_type, domain, source_id, title, tunnus, kunta, body, search_vector, updated_at)
                SELECT 'tiedosto:' || f.id, 'tiedosto', 'RAK', f.id,
                       coalesce(nullif(f.otsikko, ''), f.alkuperainen_nimi), NULL, NULL,
                       f.kuvaus, build_search_vector(NULL, f.otsikko, f.kuvaus), now()
                ON CONFLICT (global_id) DO UPDATE SET
                    title = EXCLUDED.title, tunnus = EXCLUDED.tunnus, kunta = EXCLUDED.kunta,
                    body = EXCLUDED.body, search_vector = EXCLUDED.search_vector, updated_at = EXCLUDED.updated_at;
            $$
        ");

        DB::statement("
            CREATE FUNCTION sync_tiedosto_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    DELETE FROM search_index WHERE global_id = 'tiedosto:' || OLD.id;
                ELSIF NEW.poistettu IS NOT NULL THEN
                    DELETE FROM search_index WHERE global_id = 'tiedosto:' || NEW.id;
                ELSE
                    PERFORM search_index_upsert_tiedosto(NEW);
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for tiedosto: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER tiedosto_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON tiedosto
            FOR EACH ROW EXECUTE PROCEDURE sync_tiedosto_search()
        ");

        // ARK file
        DB::statement("
            CREATE FUNCTION search_index_upsert_ark_tiedosto(f ark_tiedosto)
            RETURNS void LANGUAGE sql AS $$
                INSERT INTO search_index (global_id, item_type, domain, source_id, title, tunnus, kunta, body, search_vector, updated_at)
                SELECT 'ark_tiedosto:' || f.id, 'ark_tiedosto', 'ARK', f.id,
                       coalesce(nullif(f.otsikko, ''), f.alkup_tiedostonimi), NULL, NULL,
                       f.kuvaus, build_search_vector(NULL, f.otsikko, f.kuvaus), now()
                ON CONFLICT (global_id) DO UPDATE SET
                    title = EXCLUDED.title, tunnus = EXCLUDED.tunnus, kunta = EXCLUDED.kunta,
                    body = EXCLUDED.body, search_vector = EXCLUDED.search_vector, updated_at = EXCLUDED.updated_at;
            $$
        ");

        DB::statement("
            CREATE FUNCTION sync_ark_tiedosto_search()
            RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    DELETE FROM search_index WHERE global_id = 'ark_tiedosto:' || OLD.id;
                ELSIF NEW.poistettu IS NOT NULL THEN
                    DELETE FROM search_index WHERE global_id = 'ark_tiedosto:' || NEW.id;
                ELSE
                    PERFORM search_index_upsert_ark_tiedosto(NEW);
                END IF;
                RETURN NULL;
            EXCEPTION WHEN OTHERS THEN
                RAISE WARNING 'search_index sync failed for ark_tiedosto: %', SQLERRM;
                RETURN NULL;
            END;
            $$
        ");

        DB::statement("
            CREATE TRIGGER ark_tiedosto_search_sync
            AFTER INSERT OR UPDATE OR DELETE ON ark_tiedosto
            FOR EACH ROW EXECUTE PROCEDURE sync_ark_tiedosto_search()
        ");

        // Backfill (re-runnable; also how to refresh stale copies)
        DB::statement("
            DO $$ BEGIN
                PERFORM search_index_upsert_kuva(k) FROM kuva k WHERE k.poistettu IS NULL;
                PERFORM search_index_upsert_ark_kuva(k) FROM ark_kuva k WHERE k.poistettu IS NULL;
                PERFORM search_index_upsert_tiedosto(f) FROM tiedosto f WHERE f.poistettu IS NULL;
                PERFORM search_index_upsert_ark_tiedosto(f) FROM ark_tiedosto f WHERE f.poistettu IS NULL;
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
        DB::statement("DROP TRIGGER IF EXISTS ark_tiedosto_search_sync ON ark_tiedosto");
        DB::statement("DROP TRIGGER IF EXISTS tiedosto_search_sync ON tiedosto");
        DB::statement("DROP TRIGGER IF EXISTS ark_kuva_search_sync ON ark_kuva");
        DB::statement("DROP TRIGGER IF EXISTS kuva_search_sync ON kuva");
        DB::statement("DROP FUNCTION IF EXISTS sync_ark_tiedosto_search()");
        DB::statement("DROP FUNCTION IF EXISTS sync_tiedosto_search()");
        DB::statement("DROP FUNCTION IF EXISTS sync_ark_kuva_search()");
        DB::statement("DROP FUNCTION IF EXISTS sync_kuva_search()");
        DB::statement("DROP FUNCTION IF EXISTS search_index_upsert_ark_tiedosto(ark_tiedosto)");
        DB::statement("DROP FUNCTION IF EXISTS search_index_upsert_tiedosto(tiedosto)");
        DB::statement("DROP FUNCTION IF EXISTS search_index_upsert_ark_kuva(ark_kuva)");
        DB::statement("DROP FUNCTION IF EXISTS search_index_upsert_kuva(kuva)");
        DB::statement("DROP FUNCTION IF EXISTS build_search_vector(text, text, text)");
        DB::statement("DELETE FROM search_index WHERE item_type IN ('kuva', 'ark_kuva', 'tiedosto', 'ark_tiedosto')");
    }
}
