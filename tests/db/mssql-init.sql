CREATE TABLE authors (
	id int NOT NULL IDENTITY(1,1),
	name varchar(50) NOT NULL,
	web varchar(100) NOT NULL,
	born date DEFAULT NULL,
	favorite_author_id int,
	PRIMARY KEY(id),
	CONSTRAINT authors_favorite_author FOREIGN KEY (favorite_author_id) REFERENCES authors (id)
);


CREATE TABLE publishers (
	publisher_id int NOT NULL IDENTITY(1,1),
	name varchar(50) NOT NULL,
	PRIMARY KEY(publisher_id)
);


CREATE TABLE tags (
	id int NOT NULL IDENTITY(1,1),
	name varchar(50) NOT NULL,
	is_global char(1) NOT NULL,
	PRIMARY KEY (id)
);

CREATE TABLE eans (
	id int NOT NULL IDENTITY(1,1),
	code varchar(50) NOT NULL,
	PRIMARY KEY(id)
);

CREATE TABLE books (
	id int NOT NULL IDENTITY(1,1),
	author_id int NOT NULL,
	translator_id int,
	title varchar(50) NOT NULL,
	next_part int,
	publisher_id int NOT NULL,
	published_at datetimeoffset NOT NULL,
	printed_at datetimeoffset,
	ean_id int,
	PRIMARY KEY (id),
	CONSTRAINT books_authors FOREIGN KEY (author_id) REFERENCES authors (id),
	CONSTRAINT books_translator FOREIGN KEY (translator_id) REFERENCES authors (id),
	CONSTRAINT books_next_part FOREIGN KEY (next_part) REFERENCES books (id),
	CONSTRAINT books_publisher FOREIGN KEY (publisher_id) REFERENCES publishers (publisher_id),
	CONSTRAINT books_ena FOREIGN KEY (ean_id) REFERENCES eans (id)
);

CREATE INDEX book_title ON books (title);


CREATE TABLE books_x_tags (
	book_id int NOT NULL,
	tag_id int NOT NULL,
	PRIMARY KEY (book_id, tag_id),
	CONSTRAINT books_x_tags_tag FOREIGN KEY (tag_id) REFERENCES tags (id),
	CONSTRAINT books_x_tags_book FOREIGN KEY (book_id) REFERENCES books (id) ON DELETE CASCADE
);


CREATE TABLE tag_followers (
	tag_id int NOT NULL,
	author_id int NOT NULL,
	created_at datetimeoffset NOT NULL,
	PRIMARY KEY (tag_id, author_id),
	CONSTRAINT tag_followers_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT tag_followers_author FOREIGN KEY (author_id) REFERENCES authors (id) ON DELETE CASCADE ON UPDATE CASCADE
);


CREATE TABLE contents (
	id int NOT NULL,
	type varchar(10) NOT NULL,
	thread_id int,
	PRIMARY KEY (id),
	CONSTRAINT contents_thread_id FOREIGN KEY (thread_id) REFERENCES contents (id) ON DELETE NO ACTION ON UPDATE NO ACTIOn
);


CREATE TABLE book_collections (
	id int NOT NULL IDENTITY(1,1),
	name varchar(255) NOT NULL,
	updated_at datetimeoffset NULL,
	PRIMARY KEY (id)
);


CREATE TABLE users (
	id int NOT NULL IDENTITY(1,1),
	PRIMARY KEY(id)
);


CREATE TABLE users_x_users (
	my_friends_id int NOT NULL,
	friends_with_me_id int NOT NULL,
	PRIMARY KEY (my_friends_id, friends_with_me_id),
	CONSTRAINT my_friends_key FOREIGN KEY (my_friends_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT friends_with_me_key FOREIGN KEY (friends_with_me_id) REFERENCES users (id) ON DELETE NO ACTION ON UPDATE NO ACTION
);


-- Needed for AutoupdateMapper, which is not yet supported for SQL Server in ORM
-- CREATE TRIGGER `book_collections_bu_trigger` BEFORE UPDATE ON `book_collections`
-- FOR EACH ROW SET NEW.updated_at = NOW();
--
-- CREATE TRIGGER `book_collections_bi_trigger` BEFORE INSERT ON `book_collections`
-- FOR EACH ROW SET NEW.updated_at = NOW();


CREATE TABLE photo_albums (
	id int NOT NULL IDENTITY(1,1),
	title varchar(255) NOT NULL,
	preview_id int NULL,
	PRIMARY KEY(id)
);

CREATE TABLE photos (
	id int NOT NULL IDENTITY(1,1),
	title varchar(255) NOT NULL,
	album_id int NOT NULL,
	PRIMARY KEY(id),
	CONSTRAINT photos_album_id FOREIGN KEY (album_id) REFERENCES photo_albums (id) ON DELETE CASCADE ON UPDATE CASCADE
);

ALTER TABLE photo_albums
	ADD CONSTRAINT photo_albums_preview_id FOREIGN KEY (preview_id) REFERENCES photos (id) ON DELETE NO ACTION ON UPDATE NO ACTION;
