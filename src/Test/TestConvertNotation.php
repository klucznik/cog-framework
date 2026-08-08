<?php

namespace Cog\Test;

use Cog\Type;
use Cog\Util\ConvertNotation;
use PHPUnit\Framework\TestCase;

class TestConvertNotation extends TestCase {

	public function testPascalCase() {
		$this->assertEquals('BlogPost', ConvertNotation::pascalCase('blog_post'));
		$this->assertEquals('BlogPost', ConvertNotation::pascalCase('blogPost'));
		$this->assertEquals('BlogPost', ConvertNotation::pascalCase('BlogPost'));
		$this->assertEquals('Person', ConvertNotation::pascalCase('person'));
		$this->assertEquals('', ConvertNotation::pascalCase(''));
	}

	public function testCamelCase() {
		$this->assertEquals('blogPost', ConvertNotation::camelCase('blog_post'));
		$this->assertEquals('blogPost', ConvertNotation::camelCase('BlogPost'));
		$this->assertEquals('blogPost', ConvertNotation::camelCase('blogPost'));
		$this->assertEquals('', ConvertNotation::camelCase(''));
	}

	public function testSnakeCase() {
		$this->assertEquals('blog_post', ConvertNotation::snakeCase('blogPost'));
		$this->assertEquals('blog_post', ConvertNotation::snakeCase('BlogPost'));
		$this->assertEquals('blog_post', ConvertNotation::snakeCase('blog_post'));
		$this->assertEquals('', ConvertNotation::snakeCase(''));
	}

	/** The three conversions have to agree with each other, whichever notation you start from. */
	public function testRoundTrip() {
		foreach (['blog_post', 'blogPost', 'BlogPost'] as $name) {
			$this->assertEquals('blog_post', ConvertNotation::snakeCase(ConvertNotation::pascalCase($name)));
			$this->assertEquals('blogPost', ConvertNotation::camelCase(ConvertNotation::snakeCase($name)));
		}
	}

	public function testWordsFromSnakeCase() {
		$this->assertEquals('Blog Post', ConvertNotation::wordsFromSnakeCase('blog_post'));
		$this->assertEquals('Email Verified', ConvertNotation::wordsFromSnakeCase('email_verified'));

		// Already-capitalised input is passed through rather than re-cased
		$this->assertEquals('Blog Post', ConvertNotation::wordsFromSnakeCase('Blog_Post'));
		$this->assertEquals('blog POST', ConvertNotation::wordsFromSnakeCase('blog_POST'));

		$this->assertEquals('', ConvertNotation::wordsFromSnakeCase(''));
		$this->assertEquals('Post', ConvertNotation::wordsFromSnakeCase('_post_'));
	}

	public function testWordsFromCamelCase() {
		$this->assertEquals('Blog post', ConvertNotation::wordsFromCamelCase('blogPost'));
		$this->assertEquals('Blog post', ConvertNotation::wordsFromCamelCase('BlogPost'));
		$this->assertEquals('Person', ConvertNotation::wordsFromCamelCase('person'));
		$this->assertEquals('', ConvertNotation::wordsFromCamelCase(''));
	}

	/** The digit/letter boundaries are the fiddly part of the hand-rolled loop. */
	public function testWordsFromCamelCaseWithDigits() {
		$this->assertEquals('Address line 2', ConvertNotation::wordsFromCamelCase('addressLine2'));
		$this->assertEquals('Iso 3166 code', ConvertNotation::wordsFromCamelCase('iso3166Code'));

		// A run of digits is one word, not one word per digit
		$this->assertEquals('Line 1234', ConvertNotation::wordsFromCamelCase('line1234'));
	}

	public function testPrefixFromType() {
		$this->assertEquals('obj', ConvertNotation::prefixFromType(Type::OBJECT));
		$this->assertEquals('obj', ConvertNotation::prefixFromType(Type::ARRAY));
		$this->assertEquals('bln', ConvertNotation::prefixFromType(Type::BOOLEAN));
		$this->assertEquals('dtt', ConvertNotation::prefixFromType(Type::DATETIME));
		$this->assertEquals('flt', ConvertNotation::prefixFromType(Type::FLOAT));
		$this->assertEquals('int', ConvertNotation::prefixFromType(Type::INTEGER));
		$this->assertEquals('str', ConvertNotation::prefixFromType(Type::STRING));

		$this->assertEquals('', ConvertNotation::prefixFromType('DateTimeImmutable'));
	}

	public function testPhpTypeFromType() {
		$this->assertEquals('object', ConvertNotation::phpTypeFromType(Type::OBJECT));
		$this->assertEquals('array', ConvertNotation::phpTypeFromType(Type::ARRAY));
		$this->assertEquals('bool', ConvertNotation::phpTypeFromType(Type::BOOLEAN));
		$this->assertEquals('string', ConvertNotation::phpTypeFromType(Type::STRING));
		$this->assertEquals('string', ConvertNotation::phpTypeFromType(Type::DATETIME));
		$this->assertEquals('float', ConvertNotation::phpTypeFromType(Type::FLOAT));
		$this->assertEquals('int', ConvertNotation::phpTypeFromType(Type::INTEGER));

		$this->assertEquals('', ConvertNotation::phpTypeFromType('DateTimeImmutable'));
	}

	/** Drops the three character type prefix, then camel cases whatever is left. */
	public function testTranslationNameFromString() {
		$this->assertEquals('firstName', ConvertNotation::translationNameFromString('strFirstName'));
		$this->assertEquals('emailVerified', ConvertNotation::translationNameFromString('blnEmailVerified'));
		$this->assertEquals('createdAt', ConvertNotation::translationNameFromString('dttCreatedAt'));
	}
}
