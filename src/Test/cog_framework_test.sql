-- Fixture database for the Cog test suite.
--
-- Two suites assert against this exact schema and data set:
--
--   * src/Test/TestDatabase.php - the number of tables, the columns and indexes
--     on `person`, the foreign key on `obj`, and the contents of the first
--     `person` row.
--   * src/Test/TestCodegen.php - the classes the code generator produces from
--     it, which is why the schema deliberately covers a type table (`blog_type`),
--     a type table with extra columns (`priority_type`), an association table
--     (`tag_obj_assn`), a graph association joining one table to itself
--     (`person_person_assn`), a self-referencing foreign key and a foreign key
--     whose column is not named `*_id` (`category`), a one-to-one through a
--     unique foreign key (`person_profile`), a `timestamp` column used for
--     optimistic locking (`blog_post.modification_date`) and a
--     `DEFAULT CURRENT_TIMESTAMP` column (`obj.creation_date`).
--
-- Changing anything here means changing those assertions too.
--
-- Load it with:
--     mysql -u root -p < src/Test/cog_framework_test.sql
--
-- All tables are InnoDB because the suite exercises transaction rollback and
-- reads foreign keys, neither of which MyISAM supports.

DROP DATABASE IF EXISTS `cog_framework_test`;
CREATE DATABASE `cog_framework_test` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cog_framework_test`;

--
-- person: five columns and four indexes (PRIMARY, then `email`, `name`,
-- `email_verified`). testTableIndexes expects `email` to be the second index,
-- so it has to stay the first one declared after the primary key.
--
CREATE TABLE `person` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NOT NULL,
	`email` VARCHAR(255) NOT NULL,
	`email_verified` TINYINT(1) NOT NULL DEFAULT 0,
	`password` VARCHAR(255) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `email` (`email`),
	KEY `name` (`name`),
	KEY `email_verified` (`email_verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- obj: testForeignFieldsForTable expects exactly one foreign key, pointing at
-- `person`. `creation_date` defaults to CURRENT_TIMESTAMP, which cannot be a
-- constant property initializer, so the generated ObjGen gets a constructor
-- that assigns Carbon::now() instead.
--
CREATE TABLE `obj` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`person_id` INT UNSIGNED NOT NULL,
	`label` VARCHAR(255) NOT NULL,
	`creation_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `person_id` (`person_id`),
	CONSTRAINT `obj_person_id` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- blog_type: a type table. The `_type` suffix is what makes the code generator
-- emit a Type class (constants built from the rows) rather than an ORM class,
-- so the rows below become BlogType::POST and BlogType::EDITORIAL.
--
CREATE TABLE `blog_type` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(100) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- blog_post: three foreign keys, one of them to a type table, plus a
-- `timestamp` column. The timestamp column is what drives the generated
-- optimistic locking in save().
--
CREATE TABLE `blog_post` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`obj_id` INT UNSIGNED NOT NULL,
	`type_id` INT UNSIGNED NOT NULL,
	`author_id` INT UNSIGNED NOT NULL,
	`title` VARCHAR(255) NOT NULL,
	`body` TEXT,
	`modification_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	KEY `author_id` (`author_id`),
	KEY `type_id` (`type_id`),
	KEY `obj_id` (`obj_id`),
	CONSTRAINT `blog_post_type_id` FOREIGN KEY (`type_id`) REFERENCES `blog_type` (`id`),
	CONSTRAINT `blog_post_obj_id` FOREIGN KEY (`obj_id`) REFERENCES `obj` (`id`),
	CONSTRAINT `blog_post_author_id` FOREIGN KEY (`author_id`) REFERENCES `person` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- asset: testGetRows expects exactly two rows.
--
CREATE TABLE `asset` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`obj_id` INT UNSIGNED NOT NULL,
	`filename` VARCHAR(255) NOT NULL,
	`mime_type` VARCHAR(128) NOT NULL,
	`size` INT UNSIGNED NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	KEY `obj_id` (`obj_id`),
	CONSTRAINT `asset_obj_id` FOREIGN KEY (`obj_id`) REFERENCES `obj` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- tag / tag_obj_assn: a many-to-many relationship. The `_assn` suffix and the
-- two-column primary key are what make the generator treat tag_obj_assn as an
-- association table instead of an entity, giving Tag and Obj their
-- associate/unassociate/count methods rather than a TagObjAssn class.
--
CREATE TABLE `tag` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(200) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tag_obj_assn` (
	`tag_id` INT UNSIGNED NOT NULL,
	`obj_id` INT UNSIGNED NOT NULL,
	PRIMARY KEY (`tag_id`, `obj_id`),
	KEY `obj_id` (`obj_id`),
	CONSTRAINT `tag_obj_assn_tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`id`),
	CONSTRAINT `tag_obj_assn_obj_id` FOREIGN KEY (`obj_id`) REFERENCES `obj` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- priority_type: a type table carrying extra columns beyond the required
-- integer PK and unique VARCHAR name. Those extras become additional properties
-- on the generated Type class, which is a different code path in
-- analyzeTypeTable() from a plain two-column type table like blog_type.
--
CREATE TABLE `priority_type` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(100) NOT NULL,
	`sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
	`is_default` TINYINT(1) NOT NULL DEFAULT 0,
	PRIMARY KEY (`id`),
	UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- category: three reference shapes the other tables do not have.
--   * `parent_id` points back at `category`, so the generated class references
--     itself and the object descriptions have to be disambiguated.
--   * `priority_type_id` points at a type table, which makes the reference a
--     type reference rather than an object one.
--   * `owner` is a foreign key whose column name does not end in `_id`, so the
--     generator appends _object to the reference name to keep it apart from the
--     integer column it was mapped from.
--
CREATE TABLE `category` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`parent_id` INT UNSIGNED NULL,
	`priority_type_id` INT UNSIGNED NOT NULL,
	`owner` INT UNSIGNED NULL,
	`name` VARCHAR(100) NOT NULL,
	PRIMARY KEY (`id`),
	KEY `parent_id` (`parent_id`),
	KEY `priority_type_id` (`priority_type_id`),
	KEY `owner` (`owner`),
	CONSTRAINT `category_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `category` (`id`),
	CONSTRAINT `category_priority_type_id` FOREIGN KEY (`priority_type_id`) REFERENCES `priority_type` (`id`),
	CONSTRAINT `category_owner` FOREIGN KEY (`owner`) REFERENCES `person` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- person_profile: a one-to-one. The foreign key at `person_id` is UNIQUE, which
-- makes the reverse reference on Person a single object rather than an array -
-- a different branch from every other reverse reference in this schema.
--
CREATE TABLE `person_profile` (
	`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`person_id` INT UNSIGNED NOT NULL,
	`bio` TEXT,
	`website` VARCHAR(255) NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `person_id` (`person_id`),
	CONSTRAINT `person_profile_person_id` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- person_person_assn: a graph association - both foreign keys point at the same
-- table. tag_obj_assn joins two different tables, so it never exercises the
-- prefix calculation that keeps the two sides of a self-join apart (`person` and
-- `friend` here) rather than generating two identically named methods.
--
CREATE TABLE `person_person_assn` (
	`person_id` INT UNSIGNED NOT NULL,
	`friend_id` INT UNSIGNED NOT NULL,
	PRIMARY KEY (`person_id`, `friend_id`),
	KEY `friend_id` (`friend_id`),
	CONSTRAINT `person_person_assn_person_id` FOREIGN KEY (`person_id`) REFERENCES `person` (`id`),
	CONSTRAINT `person_person_assn_friend_id` FOREIGN KEY (`friend_id`) REFERENCES `person` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Data. The first `person` row is asserted field by field in
-- testQueryFetchAssoc and testQueryFetch.
--
INSERT INTO `person` (`id`, `name`, `email`, `email_verified`, `password`) VALUES
	(1, 'Adam Kluczyk', 'klucznik@test.net', 0, 'f0af0f1e34c0c5f'),
	(2, 'Maria Nowak', 'maria@test.net', 1, 'c1b8e2a90d3f764'),
	(3, 'Piotr Lewandowski', 'piotr@test.net', 1, '9d4e70b25ac1f83');

-- creation_date is given explicitly rather than left to the CURRENT_TIMESTAMP
-- default, because TestDatabaseResult asserts the exact cast value.
INSERT INTO `obj` (`id`, `person_id`, `label`, `creation_date`) VALUES
	(1, 1, 'First object', '2024-01-15 10:00:00'),
	(2, 2, 'Second object', '2024-02-01 14:30:00');

INSERT INTO `blog_type` (`id`, `name`) VALUES
	(1, 'Post'),
	(2, 'Editorial');

INSERT INTO `blog_post` (`id`, `obj_id`, `type_id`, `author_id`, `title`, `body`) VALUES
	(1, 1, 1, 1, 'Hello world', 'The first post.'),
	(2, 2, 2, 1, 'Draft post', 'Not published yet.');

INSERT INTO `asset` (`id`, `obj_id`, `filename`, `mime_type`, `size`) VALUES
	(1, 1, 'logo.png', 'image/png', 20480),
	(2, 1, 'manual.pdf', 'application/pdf', 512000);

INSERT INTO `tag` (`id`, `name`) VALUES
	(1, 'php'),
	(2, 'testing'),
	(3, 'orm');

INSERT INTO `tag_obj_assn` (`tag_id`, `obj_id`) VALUES
	(1, 1),
	(2, 1),
	(3, 2);

INSERT INTO `priority_type` (`id`, `name`, `sort_order`, `is_default`) VALUES
	(1, 'Low', 30, 0),
	(2, 'Normal', 20, 1),
	(3, 'Urgent', 10, 0);

INSERT INTO `category` (`id`, `parent_id`, `priority_type_id`, `owner`, `name`) VALUES
	(1, NULL, 2, 1, 'Root'),
	(2, 1, 3, 2, 'Announcements'),
	(3, 1, 1, NULL, 'Archive');

INSERT INTO `person_profile` (`id`, `person_id`, `bio`, `website`) VALUES
	(1, 1, 'Maintainer of the framework.', 'https://example.test/adam'),
	(2, 2, 'Occasional contributor.', NULL);

INSERT INTO `person_person_assn` (`person_id`, `friend_id`) VALUES
	(1, 2),
	(1, 3),
	(2, 3);
