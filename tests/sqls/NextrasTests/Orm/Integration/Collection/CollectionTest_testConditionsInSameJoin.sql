SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "public"."authors" AS "author" ON (
    "books"."author_id" = "author"."id"
  )
WHERE
  ("author"."name" = 'Writer 1')
  AND (
    "author"."web" = 'http://example.com/1'
  );
