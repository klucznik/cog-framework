<?php

namespace Cog\Util;

use Cog\BaseApplication;
use Cog\Exceptions\CogException;
use DirectoryIterator;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

abstract class FileSystem {

	/**
	 * Remove anything which isn't a letter, digit, whitespace or any of the following characters -_~,;:[]().
	 *
	 * Letters and digits are matched in any script, so a name such as "żółć.txt"
	 * survives intact rather than being stripped down to its extension.
	 *
	 * @param string $filename
	 * @return string empty if the name is not valid UTF-8, or nothing is left of it
	 */
	public static function sanitizeFilename(string $filename) : string {
		$filename = preg_replace('/[^\p{L}\p{N}\s_~,;:\[\]().-]/u', '', $filename);

		if ($filename === null) { // invalid UTF-8, nothing safe can be salvaged
			return '';
		}

		return preg_replace('/\.{2,}/', '', $filename); // Remove any runs of periods
	}

	/**
	 * Gets mime information about a file
	 * @param string $filePath path to examined file
	 * @return string file's mime type, empty string if cannot detect
	 * @throws Exception
	 */
	public static function getMimeType(string $filePath) : string {
		$toReturn = '';

		$mimeTypes = BaseApplication::$container->get('mime');
		$mime = $mimeTypes->guessMimeType($filePath);

		if ($mime !== false) {
			$toReturn = $mime;
		}

		return $toReturn;
	}

	public static function removeDirectory(string $directoryPath) : bool {
		if (!is_dir($directoryPath)) {
			throw new CogException(sprintf('%s is not a directory', $directoryPath));

		}
		$it = new RecursiveDirectoryIterator($directoryPath, RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
		foreach($files as $file) {
			if ($file->isDir()) {
				rmdir($file->getPathname());
			} else {
				unlink($file->getPathname());
			}
		}

		return rmdir($directoryPath);
	}

	/**
	 * Removes all files from a given directory
	 * @param string $directoryPath
	 * @param array $filesNamesToOmit
	 * @return int mount of files removed
	 */
	public static function cleanDirectory(string $directoryPath, array $filesNamesToOmit = []) : int {
		$count = 0;

		foreach (new DirectoryIterator($directoryPath) as $file) {
			if ($file->isFile() && !in_array($file->getFilename(), $filesNamesToOmit, false)) {
				unlink($file->getPathname());
				$count++;
			}
		}

		return $count;
	}
}
