<?php

$extensionPath = t3lib_extMgm::extPath('fal_remoteprocessing');
return array(
	'tx_falremoteprocessing_remotefileprocessingservice' => $extensionPath . 'Classes/RemoteFileProcessingService.php',
	'tx_falremoteprocessing_remotefileinfoservice' => $extensionPath . 'Classes/RemoteFileInfoService.php'
);
