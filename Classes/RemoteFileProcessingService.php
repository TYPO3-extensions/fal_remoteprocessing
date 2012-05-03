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

class Tx_FalRemoteprocessing_RemoteFileProcessingService {

	/**
	 * checks if we can handle this processing deal
	 * right now we only handle certain contexts and webdav drivers that have a URL
	 *
	 * @param t3lib_file_Storage $storage
	 * @param t3lib_file_Driver_AbstractDriver $driver
	 * @param t3lib_file_ProcessedFile $processedFile
	 * @param t3lib_file_FileInterface $file
	 * @param string $context
	 * @param array $configuration
	 */
	protected function canProcess(t3lib_file_Storage $storage, t3lib_file_Driver_AbstractDriver $driver, t3lib_file_ProcessedFile $processedFile, t3lib_file_FileInterface $file, $context, array $configuration = array()) {
		$canProcess = TRUE;
		$storageRecord = $storage->getStorageRecord();
		if (($driver instanceof Tx_FalWebdav_Driver_WebDavDriver) === FALSE) {
			$canProcess = FALSE;
		}
		if ($context !== t3lib_file_ProcessedFile::CONTEXT_IMAGEPREVIEW && $context !== t3lib_file_ProcessedFile::CONTEXT_IMAGECROPSCALEMASK) {
			$canProcess = FALSE;
		}
		if ($storageRecord['tx_falremoteprocessing_processorurl'] === FALSE) {
			$canProcess = FALSE;
		}
		return $canProcess;
	}
	
	/**
	 * Emits file pre-processing signal.
	 *
	 * @param t3lib_file_Storage $storage
	 * @param t3lib_file_Driver_AbstractDriver $driver
	 * @param t3lib_file_ProcessedFile $processedFile
	 * @param t3lib_file_FileInterface $file
	 * @param string $context
	 * @param array $configuration
	 */
	public function preFileProcess(t3lib_file_Storage $storage, t3lib_file_Driver_AbstractDriver $driver, t3lib_file_ProcessedFile $processedFile, t3lib_file_FileInterface $file, $context, $configuration) {
		if ($processedFile->isProcessed() === FALSE && $this->canProcess($storage, $driver, $processedFile, $file, $context, $configuration)) {
			$this->callRemoteProcessor($file, $storage, $processedFile, $context, $configuration);
		}
	}

	/**
	 * internal function to fetch the meta data for certain files
	 *
	 * @param t3lib_file_File $file
	 * @param t3lib_file_Storage $storage the storage object to fetch the information
	 * @param t3lib_file_ProcessedFile $processedFile
	 * @param string $context
	 * @param array $configuration
	 */
	protected function callRemoteProcessor($file, t3lib_file_Storage $storage, t3lib_file_ProcessedFile $processedFile, $context, array $configuration) {
		$record = $storage->getStorageRecord();
		$processingFolder = $storage->getProcessingFolder();
		$requestUrl = $record['tx_falremoteprocessing_processorurl'];
		$hash = sha1($record['tx_falremoteprocessing_sharedsecret']);

			// do the remote call
		$requestUrl .= (strpos($requestUrl, '?') === FALSE ? '?' : '&')
			. 'hash=' . urlencode($hash)
			. '&file=' . urlencode($file->getIdentifier())
			. '&context=' . urlencode($context)
			. t3lib_div::implodeArrayForUrl('configuration', $configuration);
		$result = t3lib_div::getURL($requestUrl);
		$remoteData = json_decode($result, TRUE);
			// on success, the path to the target file was returned
		if ($remoteData['response'] === 'success' && strlen($remoteData['data']) > 0) {
			$processedFile->setIdentifier($processingFolder->getIdentifier() . $remoteData['data']);
			$processedFile->setProcessed(TRUE);
		}
	}

}
