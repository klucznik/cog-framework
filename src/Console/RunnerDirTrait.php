<?php

namespace Cog\Console;

/**
 * Trait RunnerDirTrait
 * @package Cog\Console
 *
 * Locates the directory the command runner (./cog) itself resides in, which is
 * what relative codegen paths are resolved against.
 */
trait RunnerDirTrait {

	/**
	 * returns the directory the command runner (./cog) itself resides
	 * @return string
	 */
	protected function getRunnerDir(): string {
		return dirname(realpath($_SERVER['SCRIPT_FILENAME']));
	}
}
