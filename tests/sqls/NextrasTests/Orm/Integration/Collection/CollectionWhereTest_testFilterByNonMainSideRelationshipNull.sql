SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "books" AS "previousPart" ON (
    "books"."id" = "previousPart"."next_part"
  )
WHERE
  "previousPart"."id" IS NULL;

SELECT
  "books".*
FROM
  "books" AS "books"
  LEFT JOIN "books" AS "previousPart" ON (
    "books"."id" = "previousPart"."next_part"
  )
WHERE
  "previousPart"."id" IS NOT NULL;
