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
 * fetches meta information about a file remotely
 **/
class Tx_FalRemoteprocessing_RemoteFileInfoService {

	/**
	 * internal cache of all information that was fetched from the remote
	 * storage
	 */
	protected $_cachedFileInfo = array();

	/**
	 * function to check if the call should be made on this storage
	 *
	 * @param t3lib_file_Storage $storage
	 * @return void
	 */
	protected function shouldFetchRemoteFileInfo(t3lib_file_Storage $storage) {
		$record = $storage->getStorageRecord();
		if (strlen($record['tx_falremoteprocessing_fileinfourl']) > 0) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 * signal that is emitted before a single file gets indexed
	 * right here, the call to the remote service is done
	 * in order to fetch the file info of the file object
	 * and then save it in $_cachedFileInfo
	 * 
	 * @param t3lib_file_File $fileObject the file object
	 * @param array $fileInfo
	 * @return void
	 */
	public function preFileIndex(t3lib_file_File $fileObject, $fileInfo) {
		if ($this->shouldFetchRemoteFileInfo($fileObject->getStorage())) {
			if (!isset($this->_cachedFileInfo[$fileObject->getIdentifier()])) {
				// fetch the data from the remote page
				$this->fetchDataFromRemoteCall(array($fileObject), $fileObject->getStorage());
			}
			
				// fill the fileInfo array for this fileObject
			if (isset($this->_cachedFileInfo[$fileObject->getIdentifier()])) {
				$fileInfo = array_merge($fileInfo, $this->_cachedFileInfo[$fileObject->getStorage()->getUid() . ':' . $fileObject->getIdentifier()]);
			}
		}
	}

	/**
	 * signal that is emitted before multiple files get indexed
	 * right here, the call to the remote service is done
	 * in order to fetch the file info of the file objects
	 * and then save them in $_cachedFileInfo
	 * 
	 * @param t3lib_file_File[] $fileObjectsToIndex the array with the files
	 * @return void
	 */
	public function preMultipleFileIndex(array $fileObjectsToIndex) {
		if (count($fileObjectsToIndex) > 0) {
			$fileObject = reset($fileObjectsToIndex);
			$storageObject = $fileObject->getStorage();
				// just fetch the filedata for the file objects
			if ($this->shouldFetchRemoteFileInfo($storageObject)) {
				$this->fetchDataFromRemoteCall($fileObjectsToIndex, $storageObject);
			}
		}
	}

	/**
	 * Emits pre-gather-file information
	 * This signal is only there to check if we already have fetched
	 * meta-data, and then add the 
	 *
	 * @param t3lib_file_File $fileObject
	 * @param array $fileInfo
	 * @param boolean $gatherDefaultInformation
	 */
	public function preGatherFileInformation(t3lib_file_File $fileObject, $fileInfo, $gatherDefaultInformation) {
		$storage = $fileObject->getStorage();
		if ($this->shouldFetchRemoteFileInfo($storage) && $gatherDefaultInformation->getDefaultFileInfo === TRUE) {
			if (!isset($this->_cachedFileInfo[$storage->getUid() . ':' . $fileObject->getIdentifier()])) {
				// fetch the data from the remote page
				$this->fetchDataFromRemoteCall(array($fileObject), $storage);
			}
			
				// fill the fileInfo array for this fileObject
			if (isset($this->_cachedFileInfo[$storage->getUid() . ':' . $fileObject->getIdentifier()])) {
				$fileInfo = array_merge($fileInfo, $this->_cachedFileInfo[$storage->getUid() . ':' . $fileObject->getIdentifier()]);
					// @todo: check if all the data necessary is set
				$gatherDefaultInformation->getDefaultFileInfo = FALSE;
			}
		}
	}

	/**
	 * internal function to fetch the meta data for certain files
	 *
	 * @param t3lib_file_File[] $files
	 * @param t3lib_file_Storage $storage the storage object to fetch the information
	 */
	protected function fetchDataFromRemoteCall(array $files, t3lib_file_Storage $storage) {
		$record = $storage->getStorageRecord();
		$requestUrl = $record['tx_falremoteprocessing_fileinfourl'];
		$hash = sha1($record['tx_falremoteprocessing_sharedsecret']);

		$fileLocations = array();
		foreach ($files as $fileObject) {
			$fileLocations[] = $fileObject->getIdentifier();
		}
		
			// do the remote call
		$requestUrl .= (strpos($requestUrl, '?') === FALSE ? '?' : '&') . 'hash=' . urlencode($hash) . t3lib_div::implodeArrayForUrl('files', $fileLocations);
		echo '<pre>FILE INFO' . CRLF . $requestUrl;
		ob_flush();
		exit;
		$result = t3lib_div::getURL($requestUrl);
		$remoteData = json_decode($result, TRUE);
		if ($remoteData['response'] === 'success') {
			foreach ($remoteData['data'] as $fileIdentifier => $fileData) {
				$identifier = $storage->getUid() . ':' . $fileIdentifier;
				if (isset($this->_cachedFileInfo[$identifier])) {
					$this->_cachedFileInfo[$identifier] = array_merge($this->_cachedFileInfo[$identifier], $fileData);
				} else {
					$this->_cachedFileInfo[$identifier] = $fileData;
				}
			}
		}
	}

}
