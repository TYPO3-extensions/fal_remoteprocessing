<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Benjamin Mack <benni@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * 
 * this script is useful to be put on a remote server
 * and accessed by a TYPO3 installation to fetch information
 * about files on this server
 *
 * it is a simple spaghetti script that 
 * fetches an array with file information for any files on this
 * local filesystem
 * however, this script is restricted to certain directories
 */

error_reporting(E_ALL ^ E_NOTICE);

class T3_RemoteBase {
	const FILE_Settings = 'remote.ini';

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @var string
	 */
	protected $scriptPath;

	/**
	 * initialize the remote connector
	 * does the basic security check
	 */
	public function __construct() {
		$this->scriptPath = rtrim(dirname(__FILE__), '/') . '/';
		$this->parseSettings();
		$this->defineConstants();

		$this->checkHash($_REQUEST['hash']);
	}

	/**
	 * step 1: security measures
	 * very simple right now
	 *
	 * @param $hash
	 * @return void
	 */
	public function checkHash($hash) {
		if (sha1(T3_REMOTE_SHAREDSECRET) !== $hash) {
			$this->returnAsJson('Invalid security hash.', 'error');
		}
	}

	/**
	 * step 2: check for the existance of all requested files
	 *
	 * @param array $requestedFiles
	 * @return array
	 */
	public function checkIncomingParameters($requestedFiles) {
		$cleanFiles = array();

		if (!is_array($requestedFiles)) {
			$error = 'No files given.';
			$this->returnAsJson($error, 'error');
		}
		$requestedFiles = array_unique($requestedFiles);
		foreach ($requestedFiles as $file) {
			$file = urldecode($file);
				// if we find any strange values, skip this file
			if (strpos($file, '../') !== FALSE || strpos($file, '/..') !== FALSE || strpos($file, ':') !== FALSE) {
				continue;
			}
			if (file_exists(T3_REMOTE_BASEDIR . $file) || is_dir(T3_REMOTE_BASEDIR . $file)) {
				$cleanFiles[] = $file;
			}
		}
		return $cleanFiles;
	}


	/**
	 * sets some JSON headers, outputs the data and exits
	 *
	 * @param $data
	 * @param $response: success or error 
	 */
	public function returnAsJson($data, $response) {
			// set headers
		header('Content-Type: text/javascript; charset=utf-8');
		$responseData = array(
			'response' => $response,
			'data'     => $data
		);
		echo json_encode($responseData);
		exit;
	}

	/**
	 * Parses the settings from .ini file
	 *
	 * @throws RuntimeException
	 */
	protected function parseSettings() {
		if (file_exists($this->scriptPath . self::FILE_Settings) === FALSE) {
			throw new RuntimeException('Settings file not found');
		}

		$this->settings = parse_ini_file($this->scriptPath . self::FILE_Settings, TRUE);
	}

	/**
	 * Gets a setting for a particular key.
	 *
	 * @param string $key
	 * @param string $default
	 * @return string|NULL
	 */
	protected function getSetting($key, $default = NULL) {
		$setting = $this->settings;
		$parts = explode('.', $key);

		foreach ($parts as $part) {
			if (isset($setting[$part])) {
				$setting = $setting[$part];
			} else {
				$setting = NULL;
				break;
			}
		}

		if ($setting === NULL && $default !== NULL) {
			$setting = $this->getSetting($default);
		}

		return $setting;
	}

	/**
	 * Defines the constants.
	 *
	 * @return void
	 */
	protected function defineConstants() {
		define(
			'T3_REMOTE_BASEDIR',
			$this->asDirectory($this->getSetting('storage.basePath'))
		);
		define(
			'T3_REMOTE_PROCESSINGDIR',
			$this->asDirectory($this->getSetting('storage.processingPath'))
		);
		define(
			'T3_REMOTE_SHAREDSECRET',
			trim($this->getSetting('security.sharedSecret'))
		);
	}

	/**
	 * @param string $value
	 * @return string
	 */
	protected function asDirectory($value) {
		return rtrim($value, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	}
}

?>