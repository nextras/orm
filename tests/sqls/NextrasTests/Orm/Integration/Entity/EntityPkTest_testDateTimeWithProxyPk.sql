START TRANSACTION;
INSERT INTO "logs" ("date", "count") VALUES ('2022-03-06 03:03:03.000000'::timestamptz, 3);
COMMIT;
START TRANSACTION;
UPDATE "logs" SET "count" = 5 WHERE "date" = '2022-03-06 03:03:03.000000'::timestamptz;
COMMIT;
