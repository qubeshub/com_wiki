<?php
/**
 * @package    hubzero-cms
 * @copyright  Copyright (c) 2005-2020 The Regents of the University of California.
 * @license    http://opensource.org/licenses/MIT MIT
 */

// No direct access.
defined('_HZEXEC_') or die();

/**
 * Based on code by Brion Vibber <brion@pobox.com>
 * Some of these functions are adapted from places in MediaWiki.
 * Should probably merge them for consistency.
 */

/**
 * Return UTF-8 sequence for a given Unicode code point.
 * May die if fed out of range data.
 *
 * @param  $codepoint Integer:
 * @return String
 * @public
 */
function codepointToUtf8($codepoint)
{
	if ($codepoint < 0x80)
	{
		return chr($codepoint);
	}
	if ($codepoint < 0x800)
	{
		return chr($codepoint >> 6 & 0x3f | 0xc0) . chr($codepoint & 0x3f | 0x80);
	}
	if ($codepoint < 0x10000)
	{
		return chr($codepoint >> 12 & 0x0f | 0xe0) . chr($codepoint >> 6 & 0x3f | 0x80) . chr($codepoint & 0x3f | 0x80);
	}
	if ($codepoint < 0x110000)
	{
		return chr($codepoint >> 18 & 0x07 | 0xf0) . chr($codepoint >> 12 & 0x3f | 0x80) . chr($codepoint >> 6 & 0x3f | 0x80) . chr($codepoint & 0x3f | 0x80);
	}

	echo "Asked for code outside of range ($codepoint)\n";
	die(-1);
}

/**
 * Take a series of space-separated hexadecimal numbers representing
 * Unicode code points and return a UTF-8 string composed of those
 * characters. Used by UTF-8 data generation and testing routines.
 *
 * @param   $sequence String
 * @return  String
 * @private
 */
function hexSequenceToUtf8($sequence)
{
	$utf = '';
	foreach (explode(' ', $sequence) as $hex)
	{
		$n = hexdec($hex);
		$utf .= codepointToUtf8($n);
	}
	return $utf;
}

/**
 * Take a UTF-8 string and return a space-separated series of hex
 * numbers representing Unicode code points. For debugging.
 *
 * @param   $str   String: UTF-8 string.
 * @return  string
 * @private
 */
function utf8ToHexSequence($str)
{
	return rtrim(preg_replace_callback(
		'/(.)/uS',
		function ($m)
		{
			return sprintf("%04x ", utf8ToCodepoint($m[1]));
		},
		$str
	));
}

/**
 * Determine the Unicode codepoint of a single-character UTF-8 sequence.
 * Does not check for invalid input data.
 *
 * @param  $char   String
 * @return Integer
 * @public
 */
function utf8ToCodepoint($char)
{
	// Find the length
	$z = ord($char[0]);
	if ($z & 0x80)
	{
		$length = 0;
		while ($z & 0x80)
		{
			$length++;
			$z <<= 1;
		}
	}
	else
	{
		$length = 1;
	}

	if ($length != strlen($char))
	{
		return false;
	}
	if ($length == 1)
	{
		return ord($char);
	}

	// Mask off the length-determining bits and shift back to the original location
	$z &= 0xff;
	$z >>= $length;

	// Add in the free bits from subsequent bytes
	for ($i=1; $i<$length; $i++)
	{
		$z <<= 6;
		$z |= ord($char[$i]) & 0x3f;
	}

	return $z;
}

/**
 * Escape a string for inclusion in a PHP single-quoted string literal.
 *
 * @param  $string String: string to be escaped.
 * @return String: escaped string.
 * @public
 */
function escapeSingleString($string)
{
	return strtr($string,
		array(
			'\\' => '\\\\',
			'\'' => '\\\''
		));
}
