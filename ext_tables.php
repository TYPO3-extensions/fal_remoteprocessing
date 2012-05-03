<?php

if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}


$signalSlotDispatcher = t3lib_div::makeInstance('Tx_Extbase_Object_ObjectManager')->get('Tx_Extbase_SignalSlot_Dispatcher');

	// signals to be called so the remote service could get (additional) meta data
$signalSlotDispatcher->connect('t3lib_file_Service_IndexerService', 'preFileIndex', 'Tx_FalRemoteprocessing_RemoteFileInfoService', 'preFileIndex');
$signalSlotDispatcher->connect('t3lib_file_Service_IndexerService', 'preMultipleFileIndex', 'Tx_FalRemoteprocessing_RemoteFileInfoService', 'preMultipleFileIndex');

	// signal to be called in order to fill the fileInfo array with additional meta data
$signalSlotDispatcher->connect('t3lib_file_Service_IndexerService', 'preGatherFileInformation', 'Tx_FalRemoteprocessing_RemoteFileInfoService', 'preGatherFileInformation');

	// signal for the remote file processing (e.g. preview images)
$signalSlotDispatcher->connect('t3lib_file_Storage', t3lib_file_Storage::SIGNAL_PreFileProcess, 'Tx_FalRemoteprocessing_RemoteFileProcessingService', 'preFileProcess');




	// add three additional fields to the 'sys_file_storage' table
t3lib_div::loadTCA('sys_file_storage');
$additionalCols = array(
	'tx_falremoteprocessing_fileinfourl' => array(
		'label' => 'LLL:EXT:fal_remoteprocessing/Resources/Private/Language/db.xml:sys_file_storage.tx_falremoteprocessing_fileinfourl',
		'exclude' => '1',
		'config' => array(
			'type' => 'input',
			'size' => 40,
		)
	),
	'tx_falremoteprocessing_processorurl' => array(
		'label' => 'LLL:EXT:fal_remoteprocessing/Resources/Private/Language/db.xml:sys_file_storage.tx_falremoteprocessing_processorurl',
		'exclude' => '1',
		'config' => array(
			'type' => 'input',
			'size' => 40,
		)
	),
	'tx_falremoteprocessing_sharedsecret' => array(
		'label' => 'LLL:EXT:fal_remoteprocessing/Resources/Private/Language/db.xml:sys_file_storage.tx_falremoteprocessing_sharedsecret',
		'exclude' => '1',
		'config' => array(
			'type' => 'input',
			'size' => 20,
		)
	),
);

t3lib_extMgm::addTCAcolumns('sys_file_storage', $additionalCols);
t3lib_extMgm::addToAllTCATypes('sys_file_storage', 'tx_falremoteprocessing_fileinfourl,tx_falremoteprocessing_processorurl,tx_falremoteprocessing_sharedsecret', '', 'after:configuration');


