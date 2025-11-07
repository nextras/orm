SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "public"."authors" AS "author" ON (
    "books"."author_id" = "author"."id"
  )
WHERE
  SUBSTRING("author"."name", 1, 3) = 'Wri';

SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "public"."authors" AS "author" ON (
    "books"."author_id" = "author"."id"
  )
WHERE
  SUBSTRING("author"."name", 1, 8) = 'Writer 1';
