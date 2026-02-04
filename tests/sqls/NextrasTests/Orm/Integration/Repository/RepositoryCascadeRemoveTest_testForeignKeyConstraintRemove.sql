SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" = 1 LIMIT 1;
SELECT "books".* FROM "books" AS "books" WHERE "books"."publisher_id" IN (1);
