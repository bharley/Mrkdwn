<?php
/**
 * Copyright (c) 2013, Blake Harley
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the <organization> nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * Mrkdwn
 *
 * This class will parse Markdown into the proper HTML.
 *
 * To use this class, pass the text you want parsed into
 * the Mrkdwn::parse() method and the parsed text will
 * be returned.
 * 
 * @version 1.0
 * @author  Blake Harley <blake@blakeharley.com>
 * @license New BSD License (See top of file)
 * @link    https://github.com/Panoptisis/Mrkdwn
 */
class Mrkdwn
{
	/**
	 * An associative array of hashes to their HTML.
	 * 
	 * @var array
	 */
	protected $hashTable;

	/**
	 * Contains an array of URL references for linking.
	 * 
	 * @var array
	 */
	protected $references;

	/**
	 * The table of markdown characters and their hash.
	 * 
	 * @var array
	 */
	protected $specialTable;

	/**
	 * Sets up some things.
	 */
	public function __construct()
	{
		$specialChars = str_split('*_{}[]\\`#+-.!');

		$this->specialTable = array();
		foreach ($specialChars as $char)
		{
			$hash = md5($char);
			$this->specialTable[$hash] = $char;
		}
	}

	/**
	 * The markdown-formatted text to parse.
	 * 
	 * @param  string $markdown The text to parse for markdown
	 * @return string           The text formatted with HTML
	 */
	public function parse($markdown)
	{
		// Reset some variables
		$this->hashTable = array();
		$this->references = array();

		$markdown = $this->convertToUnix($markdown);
		$markdown = $this->hashifyBlocks($markdown);
		$markdown = $this->stripReferences($markdown);

		$markdown = $this->encodeEscape($markdown);
		$markdown = $this->replaceBlock($markdown);

		return $markdown;
	}

	/**
	 * Replaces block-style Markdown with their HTML counterparts.
	 * 
	 * @param  string  $string The markdown to parse
	 * @param  boolean $inList Passes whether or not we're in a list down the line
	 * @return string          The HTML-ified markdown
	 */
	public function replaceBlock($string, $inList = false)
	{
		$string = $this->replaceHeaders($string);
		$string = $this->replaceHorizontalRules($string);
		$string = $this->replaceLists($string, $inList);
		$string = $this->replaceBlockCode($string);
		$string = $this->replaceBlockQuotes($string);

		// Prevent "paragraphing" from destroying our nice blocks
		$string = $this->hashifyBlocks($string);

		$string = $this->paragraphify($string);

		return $string;
	}

	/**
	 * Replaces headers with the proper HTML.
	 * 
	 * @param  string $string The markdown to parse
	 * @return string         The HTML-ified markdown
	 */
	protected function replaceHeaders($string)
	{
		// For closures
		$obj = $this;

		// Header
		// #####
		$string = preg_replace_callback('/^(.+)[ \t]*\n(=+|-+)[ \t]*(?:\n+|\Z)/m', function($match) use ($obj)
		{
			$level = $match[2][0] == '=' ? 1 : 2;
			return "<h$level>{$obj->replaceInline($match[1])}</h$level>\n\n";
		}, $string);

		// # Header #
		$string = preg_replace_callback('/^(#{1,6})[ \t]*(.+?)[ \t]*#*(?:\n+|\Z)/m', function($match) use ($obj)
		{
			$level = strlen($match[1]);
			return "<h$level>{$obj->replaceInline($match[2])}</h$level>\n\n";
		}, $string);

		return $string;
	}

	/**
	 * Replaces horizontal rules with the proper HTML.
	 * 
	 * @param  string $string The markdown to parse
	 * @return string         The HTML-ified markdown
	 */
	protected function replaceHorizontalRules($string)
	{
		return preg_replace('/^ {0,3}(?:(?:\*[ \t]*){3,}|(?:-[ \t]*){3,}|(?:_[ \t]*){3,})$/m', "\n<hr />\n", $string);
	}

	/**
	 * Replaces ordered and unordered lists with their respective HTML.
	 * 
	 * @param  string $string The markdown to parse
	 * @return string         The HTML-ified markdown
	 */
	public function replaceLists($string, $inList = false)
	{
		// For closures
		$obj = $this;

		$start = $inList ? '^' : '(?:(?<=\n\n)|\A\n?)';
		$pattern = '/'. $start .'(( {0,3}(([*+-]|\d+\.)[ \t]+)(?s:.+?)(\Z|\n{2,}(?=\S)(?![ \t]*(?:[*+-]|\d+\.)[ \t]+))))/m';
		$string = preg_replace_callback($pattern, function($match) use ($obj)
		{
			// Fix up possible paragrap breaks and trim trailing newlines
			$list = preg_replace('/\n{2,}/', "\n\n\n", $match[1]);
			$list = preg_replace('/\n{2,}\Z/', "\n", $list);

			$listItems = preg_replace_callback('/(\n)?(^[ \t]*)((?:[*+-]|\d+\.))[ \t]+((?s:.+?)(\n{1,2}|\n?\Z))(?=\n*(\Z|\2((?:[*+-]|\d+\.))[ \t]+))/m', function($m) use ($obj)
			{
				$item = $m[4];
				if ($m[1] || preg_match('/\n{2}/', $item))
				{
					$item = $obj->replaceBlock($obj->outdent($item), true);
				}
				else
				{
					$item = rtrim($obj->replaceLists($obj->outdent($item), true), "\n");
					$item = $obj->replaceInline($item);
				}

				return "<li>$item</li>\n";
			}, $list);

			$listType = in_array($match[4], array('*', '+', '-')) ? 'ul' : 'ol';
			return "<$listType>\n$listItems</$listType>\n";
		}, $string);

		return $string;
	}

	/**
	 * Replace blocks of code with pre and code tags.
	 * 
	 * @param  string $string The markdown to parse
	 * @return string         The HTML-ified markdown
	 */
	protected function replaceBlockCode($string)
	{
		// For closures
		$obj = $this;
		return preg_replace_callback('/(?:\n\n|\A\n?)((?:(?: {4}|\t).*(\n+|\Z))+)/m', function($match) use ($obj)
		{
			$code = $obj->outdent($match[1]);
			$code = $obj->encodeSpecial(htmlentities($code));
			$code = preg_replace('/^\r*(.+?)\s*$/s', '$1', $code);

			return "\n\n<pre><code>$code\n</code></pre>\n\n";
		}, $string);
	}

	/**
	 * Replace block quotes the blockquote HTML tag.
	 * 
	 * @param  string $string The markdown to parse
	 * @return string         The HTML-ified markdown
	 */
	protected function replaceBlockQuotes($string)
	{
		// For closures
		$obj = $this;
		return preg_replace_callback('/((^[ \t]*>[ \t]?.+(\n|\Z)(.+\n)*\n*)+)/m', function($match) use ($obj)
		{
			// Remove the indentation and blank lines
			$quote = preg_replace('/^[ \t]*>(?:[ \t]*$|[ \t]?)/m', '', $match[1]);
			$quote = $obj->replaceBlock($quote);

			return "<blockquote>\n$quote\n</blockquote>\n\n";
		}, $string);
	}

	/**
	 * Wraps floating text with paragraph tags.
	 * 
	 * @param  string $string The markdown to parse
	 * @return string         The HTML-ified markdown
	 */
	protected function paragraphify($string)
	{
		// First, trim newlines
		$string = trim($string, "\n");

		// Explode the string on newlines
		$parts = preg_split('/\n{2,}/', $string);

		foreach ($parts as $k => $part)
		{
			// If this isn't a hash, wrap it
			if (!$this->hashExists($part))
			{
				$parts[$k] = '<p>'. $this->replaceInline($part) .'</p>';
			}
			// Otherwise, expand it
			else
			{
				$parts[$k] = $this->hashTable[$part];
			}
		}

		return implode("\n\n", $parts);
	}

	/**
	 * Replaces inline Markdown with their HTML counterparts.
	 * 
	 * @param  string $string The markdown to parse
	 * @return string         The HTML-ified markdown
	 */
	public function replaceInline($string)
	{
		$string = $this->replaceInlineCode($string);
		$string = $this->replaceAnchors($string);
		$string = $this->replaceImages($string);
		$string = $this->replaceStrongEmphasis($string);

		// Take care of "hard" breaks
		$string = preg_replace('/ {2,}\n/', "<br />\n", $string);

		// Replace escaped characters
		$string = str_replace(array_keys($this->specialTable), $this->specialTable, $string);

		return $string;
	}

	/**
	 * Replaces inline code spans.
	 * 
	 * @param  string $string The markdown to parse
	 * @return string         The parsed markdown
	 */
	protected function replaceInlineCode($string)
	{
		// For closures
		$obj = $this;
		return preg_replace_callback('/(`+)[ \t]*(.+?)[ \t]*(?<!`)\1(?!`)/s', function($match) use ($obj)
		{
			return '<code>'. $obj->encodeSpecial($match[2]) .'</code>';
		}, $string);
	}

	/**
	 * Replace image markdown with the proper HTML.
	 * 
	 * @param  string $string The markdown to parse
	 * @return string         The parsed markdown
	 */
	protected function replaceImages($string)
	{
		// For closures
		$obj = $this;

		// Reference-style Images
		$string = preg_replace_callback('/!\[(.*?)\] ?(?:\n *)?\[(.*?)\]/s', function($match) use ($obj)
		{
			if ($match[2])
			{
				$refId = $match[2];
				$alt = $match[1];
			}
			else
			{
				$refId = $match[1];
				$alt = '';
			}
			$refId = strtolower($refId);

			if (!$obj->hasReference($refId))
			{
				return $match[0];
			}

			$reference = $obj->getReference($refId);
			$title = $reference['title'] ? ' title="'. $reference['title'] .'"' : '';

			return '<img src="'. $reference['url'] .'" alt="'. $alt . '"'. $title .' />';
		}, $string);

		// Regular Images
		$string = preg_replace_callback('/!\[(.*?)\]\([ \t]*<?(\S+?)>?[ \t]*(([\'"])(.*?)\4[ \t]*)?\)/s', function($match)
		{
			$alt = $match[1];
			$url = $match[2];
			$title = $match[5] ? ' title="'. $match[5] .'"' : '';

			return '<img src="'. $url .'" alt="'. $alt . '"'. $title .' />';
		}, $string);

		return $string;
	}

	/**
	 * Replaces markdown anchor tags with their HTML equivalent.
	 * 
	 * @param  string $string The markdown to parse
	 * @return string         The parsed markdown
	 */
	protected function replaceAnchors($string)
	{
		// For closures
		$obj = $this;

		// Reference-style Links
		$string = preg_replace_callback('/\[(.*?)\] ?(?:\n *)?\[(.*?)\]/s', function($match) use ($obj)
		{
			$refId = strtolower($match[2] ?: $match[1]);
			$text = $match[1];

			if (!$obj->hasReference($refId))
			{
				return $match[0];
			}

			$reference = $obj->getReference($refId);
			$title = $reference['title'] ? ' title="'. $reference['title'] .'"' : '';

			return '<a href="'. $reference['url'] .'"'. $title .'>'. $text .'</a>';
		}, $string);

		// Regular Links
		$string = preg_replace_callback('/\[(.*?)\]\([ \t]*<?(\S+?)>?[ \t]*(([\'"])(.*?)\4[ \t]*)?\)/s', function($match)
		{
			$text = $match[1];
			$url = $match[2];
			$title = $match[5] ? ' title="'. $match[5] .'"' : '';

			return '<a href="'. $url .'"'. $title .'>'. $text .'</a>';
		}, $string);

		// Auto Links
		// TODO: Obfuscate email addresses
		$string = preg_replace('/<((https?|ftp|mailto):[^\'">\s]+)>/i', '<a href="$1">$1</a>', $string);

		return $string;
	}

	/**
	 * Replace strong and emphasis markdown with their respective HTML
	 * tags.
	 * 
	 * @param  string $string The markdown to parse
	 * @return string         The parsed markdown
	 */
	protected function replaceStrongEmphasis($string)
	{
		// Strong
		$string = preg_replace('/(\*\*|__)(?=\S)(.+?[*_]*)(?<=\S)\1/s', '<strong>$2</strong>', $string);
		// Emphasis
		$string = preg_replace('/(\*|_)(?=\S)(.+?)(?<=\S)\1/s', '<em>$2</em>', $string);

		return $string;
	}

	/**
	 * Replaces block-level HTML statements with hashes which map to
	 * the hash table.
	 * 
	 * @param  string $string The markdown to strip
	 * @return string         The markdown with block-type HTML replaced
	 */
	protected function hashifyBlocks($string)
	{
		// List taken from Markdown.pl
		$blockHtml = 'p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|script|noscript|form|fieldset|iframe|math|ins|del';
		$pattern = '#[ \t\n]*(<('.$blockHtml.').+</\2>)[ \t\n]*#is';

		$obj = $this;
		return preg_replace_callback($pattern, function($match) use ($obj)
		{
			return "\n\n". $obj->addToHashTable($match[1]) ."\n\n";
		}, $string);
	}

	/**
	 * Finds and removes any references found in the string.
	 * 
	 * @param  string $string The string to manipulate
	 * @return string         The string with references removed
	 */
	protected function stripReferences($string)
	{
		$pattern = '/^[ \t]{0,3}\[([^\]]+)\]\:[ \t]*\<?([^\s]+?)\>?(?:(?:[ \t]+|[ \t]*\n[ \t]*)(\".+\"|\(.+\)|\'.+\'))?[ \t]*$/m';
		if (preg_match_all($pattern, $string, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
		{
			foreach (array_reverse($matches) as $match)
			{
				$label = strtolower($match[1][0]);
				$url = htmlentities($match[2][0]);
				$title = isset($match[3]) ? substr($match[3][0], 1, -1) : '';

				$this->references[$label] = array(
					'url'   => $url,
					'title' => $title,
				);

				$string = substr_replace($string, '', $match[0][1], strlen($match[0][0]) + 1);
			}
		}

		return $string;
	}

	/**
	 * Encode escape characters.
	 * 
	 * @param  string $string The string to be encoded
	 * @return string         The encoded string
	 */
	public function encodeEscape($string)
	{
		$obj = $this;
		return preg_replace_callback('/\\\([*_{}\[\]\\\`#\+\-\.!])/', function($match) use ($obj)
		{
			return $obj->getEscapeHash($match[1]);
		}, $string);
	}

	/**
	 * Encodes special characters to prevent them from being replaced
	 * in later replacements.
	 * 
	 * @param  string $string The string to be encoded
	 * @return string         The encoded string
	 */
	public function encodeSpecial($string)
	{
		// Encode ampersands and angle brackets
		$string = str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $string);

		// Now escape Markdown things
		$obj = $this;
		$string = preg_replace_callback('/([\*\\\_{}\[\]])/', function($match) use ($obj)
		{
			return $obj->getEscapeHash($match[1]);
		}, $string);

		return $string;
	}

	/**
	 * Converts DOS and OSX line breaks to Unix-style linebreaks for
	 * easier Regex.
	 * 
	 * @param  string $string The text to clean
	 * @return string         The converted up text
	 */
	public function convertToUnix($string)
	{
		return str_replace(array("\r\n", "\r"), "\n", $string);
	}

	/**
	 * Removes the first level of tabs (or equivalent spaces).
	 * 
	 * @param  string $string The text to outdent
	 * @return string         The outdented string
	 */
	public function outdent($string)
	{
		return preg_replace('/^(\t| {1,4})/m', '', $string);
	}

	/**
	 * 
	 * 
	 * @param  string $specialChar The character to replace
	 * @return string              The hash that represents the character
	 */
	public function getEscapeHash($specialChar)
	{
		$flip = array_flip($this->specialTable);
		return $flip[$specialChar];
	}

	/**
	 * Adds the given text to the hash table.
	 * 
	 * @param  string $html The text to hash
	 * @return string       The MD5 of the given text
	 */
	public function addToHashTable($html)
	{
		$hash = md5($html);
		$this->hashTable[$hash] = $html;
		return $hash;
	}

	/**
	 * Returns whether or not the given hash exists.
	 * 
	 * @param  string  $hash The hash to check for
	 * @return boolean       True if there's a matching hash; false otherwise
	 */
	public function hashExists($hash)
	{
		return array_key_exists($hash, $this->hashTable);
	}

	/**
	 * Returns whether or not the given reference exists.
	 * 
	 * @param  string  $reference The reference identifier
	 * @return boolean            True if there's a matching reference; false otherwise
	 */
	public function hasReference($reference)
	{
		return array_key_exists($reference, $this->references);
	}

	/**
	 * Fetches the named reference.
	 * 
	 * @param  string $reference The reference identifier
	 * @return string            The reference value
	 */
	public function getReference($reference)
	{
		return $this->references[$reference];
	}
}
