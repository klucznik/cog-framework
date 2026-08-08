<?php

namespace Cog\Util;

use Cog\Exceptions\CogException;

abstract class Template {

	/**
	 * @param string $templateFile
	 * @param array $tokens
	 * @return string
	 * @throws CogException
	 */
	public static function render(string $templateFile, array $tokens = []): string {

		if (file_exists($templateFile) === false) {
			throw new CogException('Template not found ' . $templateFile);
		}

		extract($tokens, EXTR_OVERWRITE);

		$alreadyRendered = '';

		if (ob_get_level() > 0) {
			$alreadyRendered = ob_get_contents(); // Store the Output Buffer locally
			ob_clean();
		}

		ob_start();
		require $templateFile;
		$toReturn = ob_get_contents();
		ob_end_clean();

		echo $alreadyRendered;
		return $toReturn;
	}
}
