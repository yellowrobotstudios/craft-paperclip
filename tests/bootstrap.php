<?php

require dirname(__DIR__) . '/vendor/autoload.php';

// Bootstrap a minimal Yii application so Settings validation works
// without needing the full Craft CMS environment
if (!class_exists('Yii')) {
    require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
}

// Create a minimal console app so Yii validators can resolve
new \yii\console\Application([
    'id' => 'paperclip-test',
    'basePath' => dirname(__DIR__),
]);
