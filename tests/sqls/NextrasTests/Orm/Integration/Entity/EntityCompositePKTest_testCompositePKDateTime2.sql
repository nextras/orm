START TRANSACTION;
INSERT INTO "users" VALUES (DEFAULT);
SELECT CURRVAL('public.users_id_seq');
COMMIT;
START TRANSACTION;
INSERT INTO "user_stats_x" ("user_id", "date", "value") VALUES (1, '2019-01-01'::date, 100);
COMMIT;
SELECT "user_stats_x".* FROM "user_stats_x" AS "user_stats_x" WHERE "user_stats_x"."date" = '2019-01-01'::date;
START TRANSACTION;
UPDATE "user_stats_x" SET "value" = 200 WHERE "user_id" = 1 AND "date" = '2019-01-01'::date;
COMMIT;
SELECT "user_stats_x".* FROM "user_stats_x" AS "user_stats_x" WHERE "user_stats_x"."date" = '2019-01-01'::date;
