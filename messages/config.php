<?php
/**
 * This is the configuration for generating message translations
 * for the Yii framework. It is used by the 'yiic message' command.
 */
return array(
	'sourcePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	'messagePath'=>dirname(__FILE__),
	'languages'=>array('pl'),
	'fileTypes'=>array('php'),
    'overwrite'=>true,
	'exclude'=>array(
		'.svn',
		'.git',
		'.gitignore',
		'yiilite.php',
		'yiit.php',
		'yiic.php',
		'/messages',
		'/tests',
		'/migrations',
		'/extensions',
	),
);
