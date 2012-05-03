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

include_once('RemoteBase.php');

class T3_RemoteFileInfo extends T3_RemoteBase {


	/**
	 * step 3: fetch the file info for each file
	 *
	 * @param $files the cleaned files
	 * @return $fileData
	 */
	 public function fetchFileData($files) {
		$fileData = array();
		foreach ($files as $fileName) {
			$absoluteFilePath = T3_REMOTE_BASEDIR . $fileName;
			$splFileObject = new SplFileInfo($absoluteFilePath);
			$filePath = $splFileObject->getPathname();
			$fileInfo = new finfo();
			$fileData[$fileName] = array(
				'size' => $splFileObject->getSize(),
				'atime' => $splFileObject->getATime(),
				'mtime' => $splFileObject->getMTime(),
				'ctime' => $splFileObject->getCTime(),
				'mimetype' => $fileInfo->file($filePath, FILEINFO_MIME_TYPE),
				'name' => $splFileObject->getFilename(),
				'sha1' => sha1_file($absoluteFilePath),
				'identifier' => $fileName,	// could be: $containerPath . $splFileObject->getFilename(),
			);
		}
		return $fileData;
	 }
}

$t3RemoteObject = new T3_RemoteFileInfo();
$cleanFiles = $t3RemoteObject->checkIncomingParameters($_REQUEST['files']);
$fileData = $t3RemoteObject->fetchFileData($cleanFiles);

// return the file info as JSON
$t3RemoteObject->returnAsJson($fileData, 'success');
