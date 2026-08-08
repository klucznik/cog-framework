<?php

namespace Cog\Test;

use Cog\Util\StringUtils;
use PHPUnit\Framework\TestCase;

class TestStringUtils extends TestCase {

	protected string $string = 'here is the colorful kahless?';
	protected string $multiByteString = 'żółć gęślą jaźń';
	protected string $xmlString = 'Escape this & this.';


	public function testFirstCharacter() {
		$this->assertEquals('h', StringUtils::firstCharacter($this->string));
		$this->assertEquals('ż', StringUtils::firstCharacter($this->multiByteString));

		$this->assertEquals('3', StringUtils::firstCharacter(3));

		$this->assertNull(StringUtils::firstCharacter(''));
	}

	public function testLastCharacter() {
		$this->assertEquals('?', StringUtils::lastCharacter($this->string));
		$this->assertEquals('ń', StringUtils::lastCharacter($this->multiByteString));

		$this->assertEquals('3', StringUtils::lastCharacter(3));

		$this->assertNull(StringUtils::lastCharacter(''));
	}

	public function testByte() {
		$this->assertEquals('N/A', StringUtils::getByteSize(null));
		$this->assertEquals('0 bytes', StringUtils::getByteSize(0));
		$this->assertEquals('1 byte', StringUtils::getByteSize(1));
		$this->assertEquals('512 bytes', StringUtils::getByteSize(512));
		$this->assertEquals('-512 bytes', StringUtils::getByteSize(-512));
		$this->assertEquals('1.6 KB', StringUtils::getByteSize(1680));
		$this->assertEquals('512.0 MB', StringUtils::getByteSize(512 * 1024 * 1024));
		$this->assertEquals('512.0 GB', StringUtils::getByteSize(512 * 1024 * 1024 * 1024));
		$this->assertEquals('512.0 TB', StringUtils::getByteSize(512 * 1024 * 1024 * 1024 * 1024));
		$this->assertEquals('512.0 PB', StringUtils::getByteSize(512 * 1024 * 1024 * 1024 * 1024 * 1024));
	}

	public function testContains() {
		$this->assertTrue(str_contains($this->string, 'the'));
		$this->assertTrue(str_contains($this->multiByteString, 'gęślą'));
	}

	public function testLength() {
		$this->assertFalse(StringUtils::isLengthBetween($this->string, 0, 10));
		$this->assertFalse(StringUtils::isLengthBetween($this->string, -1, 10));
		$this->assertTrue(StringUtils::isLengthBetween(' ', 1, 1));
		$this->assertTrue(StringUtils::isLengthBetween('', 0, 1));
	}

	public function testHighlight() {
		$this->assertEquals('here is the <b>colorful</b> kahless?', StringUtils::highlightWords($this->string, 'colorful'));
		$this->assertEquals('żółć <b>gęślą</b> jaźń', StringUtils::highlightWords($this->multiByteString, 'gęślą'));
	}

	public function testHighlightMultipleWords() {
		$this->assertEquals(
			'<b>here</b> is <b>the</b> colorful kahless?',
			StringUtils::highlightWords($this->string, ['here', 'the'])
		);

		// Nothing to match leaves the string untouched
		$this->assertEquals($this->string, StringUtils::highlightWords($this->string, []));
		$this->assertEquals($this->string, StringUtils::highlightWords($this->string, 'klingon'));
	}

	/** Matching is case insensitive, but the matched text keeps its own casing. */
	public function testHighlightIsCaseInsensitive() {
		$this->assertEquals(
			'<b>Here</b> is the colorful kahless?',
			StringUtils::highlightWords('Here is the colorful kahless?', 'here')
		);

		$this->assertEquals(
			'<b>COLORFUL</b> and <b>colorful</b>',
			StringUtils::highlightWords('COLORFUL and colorful', 'Colorful')
		);

		$this->assertEquals('<b>GĘŚLĄ</b>', StringUtils::highlightWords('GĘŚLĄ', 'gęślą'));
	}

	/** Regular expression metacharacters in the needle are matched literally. */
	public function testHighlightWithMetacharacters() {
		$this->assertEquals('a <b>c++</b> program', StringUtils::highlightWords('a c++ program', 'c++'));
		$this->assertEquals('what<b>?</b>', StringUtils::highlightWords('what?', '?'));
		$this->assertEquals('a.b', StringUtils::highlightWords('a.b', 'axb'));

		// An empty needle would otherwise match at every position
		$this->assertEquals($this->string, StringUtils::highlightWords($this->string, ''));
	}

	public function testAddslashes() {
		$this->assertEquals('', StringUtils::addslashes(null));
		$this->assertEquals('', StringUtils::addslashes(''));
		$this->assertEquals("O\\'Brien", StringUtils::addslashes("O'Brien"));
		$this->assertEquals('say \"what\"', StringUtils::addslashes('say "what"'));
		$this->assertEquals($this->string, StringUtils::addslashes($this->string));
	}

	public function testTrim() {
		$this->assertEquals('', StringUtils::trim(null));
		$this->assertEquals('', StringUtils::trim('   '));
		$this->assertEquals('trimmed', StringUtils::trim("  \t trimmed \n "));
		$this->assertEquals('inner  space', StringUtils::trim(' inner  space '));
	}

	public function testTrimWithCharacterList() {
		$this->assertEquals('hi', StringUtils::trim('xxhixx', 'x'));
		$this->assertEquals('slug', StringUtils::trim('--slug--', '-'));
		$this->assertEquals('  padded  ', StringUtils::trim('--  padded  --', '-'));
	}

	/**
	 * The inflector returns several candidates and the first one wins, so
	 * "person" pluralises to "persons" rather than "people".
	 */
	public function testPluralize() {
		$this->assertEquals('boxes', StringUtils::pluralize('box'));
		$this->assertEquals('blog_posts', StringUtils::pluralize('blog_post'));
		$this->assertEquals('persons', StringUtils::pluralize('person'));
	}

	public function testEntities() {
		$this->assertEquals('here is the colorful kahless?', StringUtils::htmlEntities($this->string));
		$this->assertEquals('ż&oacute;łć gęślą jaźń', StringUtils::htmlEntities($this->multiByteString));
	}

	public function testXml() {
		$this->assertEquals('here is the colorful kahless?', StringUtils::xmlEscape($this->string));
		$this->assertEquals('żółć gęślą jaźń', StringUtils::xmlEscape($this->multiByteString));
		$this->assertEquals('<![CDATA[Escape this & this.]]>', StringUtils::xmlEscape($this->xmlString));
		$this->assertEquals('<![CDATA[<b>bold</b>]]>', StringUtils::xmlEscape('<b>bold</b>'));
	}

	/** A CDATA terminator inside the payload has to be split so it cannot close the section early. */
	public function testXmlEscapesNestedCdataTerminator() {
		$this->assertEquals('<![CDATA[a & b ]]]]><![CDATA[> c]]>', StringUtils::xmlEscape('a & b ]]> c'));

		// No < or & means no CDATA wrapper, so the terminator is left alone
		$this->assertEquals('a ]]> b', StringUtils::xmlEscape('a ]]> b'));
	}
}

