START TRANSACTION;
INSERT INTO "users" ("id") VALUES (1);
COMMIT;
START TRANSACTION;
INSERT INTO "user_stats" ("user_id", "date", "value") VALUES (1, '2018-09-09 08:09:02.000000'::timestamptz, 100);
COMMIT;
SELECT "user_stats".* FROM "user_stats" AS "user_stats" WHERE ("user_stats"."user_id" = 1) AND ("user_stats"."date" = '2018-09-09 08:09:02.000000'::timestamptz);
START TRANSACTION;
UPDATE "user_stats" SET "value" = 101 WHERE "user_id" = 1 AND "date" = '2018-09-09 08:09:02.000000'::timestamptz;
COMMIT;
