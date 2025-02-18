SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "public"."authors" AS "translator" ON (
    "books"."translator_id" = "translator"."id"
  )
ORDER BY
  "translator"."name" ASC NULLS FIRST,
  "books"."id" ASC;

SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "public"."authors" AS "translator" ON (
    "books"."translator_id" = "translator"."id"
  )
ORDER BY
  "translator"."name" DESC,
  "books"."id" ASC;

SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "public"."authors" AS "translator" ON (
    "books"."translator_id" = "translator"."id"
  )
ORDER BY
  "translator"."name" ASC,
  "books"."id" ASC;

SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "public"."authors" AS "translator" ON (
    "books"."translator_id" = "translator"."id"
  )
ORDER BY
  "translator"."name" DESC NULLS LAST,
  "books"."id" ASC;
