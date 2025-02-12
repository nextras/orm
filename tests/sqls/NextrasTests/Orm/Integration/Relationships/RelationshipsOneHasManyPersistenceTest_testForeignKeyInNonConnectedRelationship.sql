START TRANSACTION;
INSERT INTO "users" ("id") VALUES (1);
INSERT INTO "user_stats" ("user_id", "date", "value") VALUES (1, '2021-12-14 21:03:00.000000'::timestamptz, 3);
INSERT INTO "users" ("id") VALUES (2);
INSERT INTO "users_x_users" ("my_friends_id", "friends_with_me_id") VALUES (2, 1);
COMMIT;
SELECT
  "users_x_users"."friends_with_me_id",
  "users_x_users"."my_friends_id"
FROM
  "users" AS "users"
  LEFT JOIN "users_x_users" AS "users_x_users" ON (
    "users_x_users"."friends_with_me_id" = "users"."id"
  )
WHERE
  "users_x_users"."my_friends_id" IN (1);

SELECT
  "users_x_users"."my_friends_id",
  "users_x_users"."friends_with_me_id"
FROM
  "users" AS "users"
  LEFT JOIN "users_x_users" AS "users_x_users" ON (
    "users_x_users"."my_friends_id" = "users"."id"
  )
WHERE
  "users_x_users"."friends_with_me_id" IN (1);

SELECT "users".* FROM "users" AS "users" WHERE "users"."id" IN (2);
START TRANSACTION;
DELETE FROM "users_x_users" WHERE ("my_friends_id", "friends_with_me_id") IN ((2, 1));
DELETE FROM "users" WHERE "id" = 1;
ROLLBACK;
SELECT "users".* FROM "users" AS "users" WHERE "users"."id" IN (1, 2);
SELECT "user_stats".* FROM "user_stats" AS "user_stats" WHERE ("user_stats"."user_id", "user_stats"."date") IN ((1, '2021-12-14 21:03:00.000000'::timestamptz));
SELECT
  "users_x_users"."my_friends_id",
  "users_x_users"."friends_with_me_id"
FROM
  "users" AS "users"
  LEFT JOIN "users_x_users" AS "users_x_users" ON (
    "users_x_users"."my_friends_id" = "users"."id"
  )
WHERE
  "users_x_users"."friends_with_me_id" IN (1, 2);

SELECT "users".* FROM "users" AS "users" WHERE "users"."id" IN (2);
