SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1;
SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "publishers" AS "publisher" ON (
    "books"."publisher_id" = "publisher"."publisher_id"
  )
WHERE
  "books"."author_id" IN (1)
ORDER BY
  "publisher"."name" ASC;

SELECT "publishers".* FROM "publishers" AS "publishers" WHERE "publishers"."publisher_id" IN (1, 2);
