# add new fields to sys_file_storage
CREATE TABLE sys_file_storage (
	tx_falremoteprocessing_fileinfourl tinytext,
	tx_falremoteprocessing_processorurl tinytext,
	tx_falremoteprocessing_sharedsecret tinytext
);