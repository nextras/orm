SELECT "books".* FROM "books" AS "books" ORDER BY "books"."price" ASC;
SELECT "authors".* FROM "public"."authors" AS "authors" WHERE "authors"."id" = 1 LIMIT 1;
SELECT "books".* FROM "books" AS "books" WHERE "books"."author_id" IN (1) ORDER BY "books"."id" DESC, "books"."price" DESC;
SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books__MAX" ON (
    "authors"."id" = "books__MAX"."author_id"
  )
GROUP BY
  "authors"."id"
ORDER BY
  MAX("books__MAX"."price") ASC;

SELECT
  "authors".*
FROM
  "public"."authors" AS "authors"
  LEFT JOIN "books" AS "books__MIN" ON (
    "authors"."id" = "books__MIN"."author_id"
  )
GROUP BY
  "authors"."id"
ORDER BY
  MIN("books__MIN"."price") ASC;
