<?php
/**
 * This is the configuration for generating message translations
 * for the Yii framework. It is used by the 'yiic message' command.
 */
return [
    'sourcePath' => dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
    'messagePath' => dirname(__FILE__),
    'languages' => ['pl'],
    'fileTypes' => ['php'],
    'overwrite' => true,
    'exclude' => [
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
    ],
];
