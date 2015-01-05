DROP SCHEMA IF EXISTS public CASCADE;
CREATE SCHEMA public;


CREATE TABLE "authors" (
	"id" SERIAL4 NOT NULL,
	"name" varchar(50) NOT NULL,
	"web" varchar(100) NOT NULL,
	"born" date DEFAULT NULL,
	PRIMARY KEY("id")
);


CREATE TABLE "publishers" (
	"id" SERIAL4 NOT NULL,
	"name" varchar(50) NOT NULL,
	PRIMARY KEY("id")
);


CREATE TABLE "tags" (
	"id" SERIAL4 NOT NULL,
	"name" varchar(50) NOT NULL,
	PRIMARY KEY ("id")
);


CREATE TABLE "books" (
	"id" SERIAL4 NOT NULL,
	"author_id" int NOT NULL,
	"translator_id" int,
	"title" varchar(50) NOT NULL,
	"next_part" int,
	"publisher_id" int NOT NULL,
	PRIMARY KEY ("id"),
	CONSTRAINT "books_authors" FOREIGN KEY ("author_id") REFERENCES authors ("id"),
	CONSTRAINT "books_translator" FOREIGN KEY ("translator_id") REFERENCES authors ("id"),
	CONSTRAINT "books_next_part" FOREIGN KEY ("next_part") REFERENCES books ("id"),
	CONSTRAINT "books_publisher" FOREIGN KEY ("publisher_id") REFERENCES publishers ("id")
);

CREATE INDEX "book_title" ON "books" ("title");


CREATE TABLE "books_x_tags" (
	"book_id" int NOT NULL,
	"tag_id" int NOT NULL,
	PRIMARY KEY ("book_id", "tag_id"),
	CONSTRAINT "books_x_tags_tag" FOREIGN KEY ("tag_id") REFERENCES "tags" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT "books_x_tags_book" FOREIGN KEY ("book_id") REFERENCES "books" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE "tag_followers" (
	"tag_id" int NOT NULL,
	"author_id"  int NOT NULL,
	"created_at"  timestamp NOT NULL,
	PRIMARY KEY ("tag_id", "author_id"),
	CONSTRAINT "tag_followers_tag"  FOREIGN KEY ("tag_id") REFERENCES "tags" ("id") ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT "tag_followers_author" FOREIGN KEY ("author_id") REFERENCES "authors" ("id") ON DELETE CASCADE ON UPDATE CASCADE
);
