START TRANSACTION;
INSERT INTO "users" ("id") VALUES (123);
COMMIT;
START TRANSACTION;
INSERT INTO "users" ("id") VALUES (124);
INSERT INTO "users_x_users" ("friends_with_me_id", "my_friends_id") VALUES (124, 123);
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
  "users_x_users"."my_friends_id" IN (123);

SELECT "users".* FROM "users" AS "users" WHERE (("users"."id" IN (124)));
SELECT
  "users_x_users"."my_friends_id",
  "users_x_users"."friends_with_me_id"
FROM
  "users" AS "users"
  LEFT JOIN "users_x_users" AS "users_x_users" ON (
    "users_x_users"."my_friends_id" = "users"."id"
  )
WHERE
  "users_x_users"."friends_with_me_id" IN (123);
