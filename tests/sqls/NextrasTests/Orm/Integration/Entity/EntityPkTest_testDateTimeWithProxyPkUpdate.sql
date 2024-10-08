START TRANSACTION;
INSERT INTO "time_series" ("date", "value") VALUES ('2022-03-06 03:03:03.000000'::timestamptz, 3);
COMMIT;
SELECT "time_series".* FROM "time_series" AS "time_series" WHERE (("time_series"."date" = '2022-03-06 03:03:03.000000'::timestamptz));
START TRANSACTION;
UPDATE "time_series" SET "value" = 5 WHERE "date" = '2022-03-06 03:03:03.000000'::timestamptz;
COMMIT;
SELECT "time_series".* FROM "time_series" AS "time_series" WHERE (("time_series"."date" = '2022-03-06 03:03:03.000000'::timestamptz));
