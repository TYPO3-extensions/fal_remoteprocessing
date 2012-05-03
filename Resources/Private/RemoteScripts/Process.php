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
 * this script is useful to be put on a remote server
 * and accessed by a TYPO3 installation to remotely process 
 * a file on this server
 */

include_once('RemoteBase.php');

class T3_RemoteProcess extends T3_RemoteBase {

	protected $jpgQuality = 90;
	protected $sharpenImage = TRUE;

	 /**
	  * main function to process any files
	  *
	  * @param $file the filename to process
	  * @param $targetFile the target filename
	  * @param $context the context --- what to do
	  * @param $configuration the default configuration
	  */
	 public function processFile($originalFile, $context, $configuration) {
	 
		 	// create the directory to cache, if necessary
	 	if (!is_dir(T3_REMOTE_BASEDIR . T3_REMOTE_PROCESSINGDIR)) {
	 		mkdir(T3_REMOTE_BASEDIR . T3_REMOTE_PROCESSINGDIR, 0755, TRUE);
	 	}

	 	switch ($context) {
	 			// create a preview image (mostly for the backend)
	 		case 'image.preview':
	 			$result = $this->processPreviewImage($originalFile, $configuration);
	 		break;

	 			// create a cropped / scaled / whatever image
	 		case 'image.cropscalemask':
	 			$fileNameForCache = $this->getProcessedFileName('csm', $originalFile, $configuration);
	 		break;

	 			// unknown context
	 		default:
	 			$error = 'Invalid context.';
	 			$this->returnAsJson($error, 'error');
	 		break;
	 	}

			// if the result was false, we quit here
		if ($result === FALSE) {
	 		$this->returnAsJson('Error while processing.', 'error');
		} else {
				// return the new file as JSON
			$this->returnAsJson($result, 'success');
		}
	 }
	 
	 /**
	  * the simple process to deliver a preview image
	  *
	  * @param string $originalFileName
	  * @param array $configuration
	  * @return mixed - the filename on success, otherwise false
	  */
	 protected function processPreviewImage($originalFile, $configuration) {
		$targetFileName = $this->getProcessedFileName('preview', $originalFile, $configuration);
		
		if (!file_exists(T3_REMOTE_BASEDIR . T3_REMOTE_PROCESSINGDIR . $targetFileName)) {
			$created = $this->generatePreviewImage(
				$originalFile,
				$targetFileName,
				(isset($configuration['width']) ? $configuration['width'] : 64),
				(isset($configuration['height']) ? $configuration['height'] : 64)
			);
			if ($created === TRUE) {
				return $targetFileName;
			} else {
				return FALSE;
			}
		} else {
			return $targetFileName;
		}
	 }

	/**
	 * generates the given cache file for the given source file with the given resolution
	 * does the actual work for a preview image
	 * @param string $originalFile
	 * @param string $targetFile
	 * @param integer $width
	 * @param integer $height
	 * @return 
	 */
	public function generatePreviewImage($originalFile, $targetFile, $width = 64, $height = 64) {
		$targetDestination = T3_REMOTE_BASEDIR . T3_REMOTE_PROCESSINGDIR . $targetFile;

		$extension = strtolower(pathinfo(T3_REMOTE_BASEDIR . $originalFile, PATHINFO_EXTENSION));

			// Check the image dimensions
		list($originalWidth, $originalHeight) = GetImageSize(T3_REMOTE_BASEDIR . $originalFile);

		// Do we need to downscale the image?
		// no, because the width of the source image is already less than the client width
		if ($originalWidth <= $width || $originalHeight <= $height) {
			return $originalFile;
		}

		$ratio = $originalWidth / $originalHeight;
		$height = ceil($width * $ratio);

		switch ($extension) {
			case 'png':
				$src = ImageCreateFromPng(T3_REMOTE_BASEDIR . $originalFile); // original image
			break;
			case 'gif':
				$src = ImageCreateFromGif(T3_REMOTE_BASEDIR . $originalFile); // original image
			break;
			default:
				$src = ImageCreateFromJpeg(T3_REMOTE_BASEDIR . $originalFile); // original image
			break;
		}

		$dst = ImageCreateTrueColor($width, $height); // re-sized image

		if ($extension == 'png') {
			imagealphablending($dst, FALSE);
			imagesavealpha($dst, TRUE);
			$transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
			imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);
		}
		ImageCopyResampled($dst, $src, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight); // do the resize in memory
		ImageDestroy($src);

		// sharpen the image?
		// NOTE: requires PHP compiled with the bundled version of GD (see http://php.net/manual/en/function.imageconvolution.php)
		if ($this->sharpenImage == TRUE && function_exists('imageconvolution')) {
			$intSharpness = $this->findSharp($originalWidth, $width);
			$arrMatrix = array(
				array(-1, -2, -1),
				array(-2, $intSharpness + 12, -2),
				array(-1, -2, -1)
			);
			imageconvolution($dst, $arrMatrix, $intSharpness, 0);
		}

		// save the new file in the appropriate path, and send a version to the browser
		switch ($extension) {
			case 'png':
				$gotSaved = ImagePng($dst, $targetDestination);
			break;
			case 'gif':
				$gotSaved = ImageGif($dst, $targetDestination);
			break;
			default:
				$gotSaved = ImageJpeg($dst, $targetDestination, $this->jpgQuality);
			break;
		}
		ImageDestroy($dst);

		if (!$gotSaved && !file_exists($targetDestination)) {
			return FALSE;
		} else {
			return TRUE;
		}
	}

	protected function findSharp($intOrig, $intFinal) {
		$intFinal = $intFinal * (750.0 / $intOrig);
		$intA			= 52;
		$intB			= -0.27810650887573124;
		$intC			= .00047337278106508946;
		$intRes		= $intA + $intB * $intFinal + $intC * $intFinal * $intFinal;
		return max(round($intRes), 0);
	}


	 /**
	  * helper function to get the checksum for a file, based on SHA1
	  * 
	  * @param string $file
	  * @param array $configuration
	  * @return string the checksum for this file
	  */
	 protected function createChecksumForFile($file, $configuration) {
	 	return sha1($file . serialize($configuration));
	 }
	 
	 /**
	  * create the filename for a processed file
	  * based on the parameters
	  *
	  * @param string $prefix 
	  * @param string $file
	  * @param array $configuration
	  * @return string the filename
	  */
	 protected function getProcessedFileName($prefix, $file, $configuration) {
		$fileExtension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		$checksum = $this->createChecksumForFile($file, $configuration);
		return $prefix . '_' . $checksum . '.' . $fileExtension;
	 }

}

$t3RemoteObject = new T3_RemoteProcess();
list($cleanFileName) = $t3RemoteObject->checkIncomingParameters(array($_REQUEST['file']));
if (strlen($cleanFileName) > 0) {
	$t3RemoteObject->processFile(
		$cleanFileName,
		$_REQUEST['context'],
		(isset($_REQUEST['configuration']) ? $_REQUEST['configuration'] : array())
	);
} else {
		// errors while cleaning the filename
	$t3RemoteObject->returnAsJson('Invalid file.', 'error');
}
