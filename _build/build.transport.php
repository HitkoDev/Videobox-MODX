<?php
ini_set('display_errors', 1);
require_once('build.functions.php');
$tstart = explode(' ', microtime());
$tstart = $tstart[1] + $tstart[0];
set_time_limit(0);
 
/* define package names */
define('PKG_NAME','Videobox');
define('PKG_NAME_LOWER','videobox');
define('PKG_VERSION','6.0.0');
define('PKG_RELEASE','rc6');
 
/* define build paths */
$root = dirname(dirname(__FILE__)).'/';
$sources = array(
    'root' => $root,
    'build' => $root . '_build/',
    'data' => $root . '_build/data/',
    'resolvers' => $root . '_build/resolvers/',
    'chunks' => $root.'core/components/'.PKG_NAME_LOWER.'/chunks/',
    'lexicon' => $root . 'core/components/'.PKG_NAME_LOWER.'/lexicon/',
    'docs' => $root.'core/components/'.PKG_NAME_LOWER.'/docs/',
    'elements' => $root.'core/components/'.PKG_NAME_LOWER.'/elements/',
    'source_assets' => $root.'assets/components/'.PKG_NAME_LOWER,
    'source_core' => $root.'core/components/'.PKG_NAME_LOWER,
);
unset($root);
 
/* override with your own defines here (see build.config.sample.php) */
require_once $sources['build'] . 'build.config.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
 
$modx= new modX();
$modx->initialize('mgr');
echo '<pre>'; /* used for nice formatting of log messages */
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');
 
$modx->loadClass('transport.modPackageBuilder','',false, true);
$builder = new modPackageBuilder($modx);
$builder->createPackage(PKG_NAME,PKG_VERSION,PKG_RELEASE);
$builder->registerNamespace(PKG_NAME_LOWER,false,true,'{core_path}components/'.PKG_NAME_LOWER.'/');

$category = $modx->newObject('modCategory');
$category->set('category', PKG_NAME);

$adaptersCategory = $modx->newObject('modCategory');
$adaptersCategory->set('category', 'Adapters');
$adaptersCategory->addOne($category);

/* add snippets */
$modx->log(modX::LOG_LEVEL_INFO,'Packaging in snippets...');
$snippets = include $sources['data'].'transport.snippets.php';
if (empty($snippets)) $modx->log(modX::LOG_LEVEL_ERROR,'Could not package in snippets.');
$category->addMany($snippets);

/* add adapter snippets */
$modx->log(modX::LOG_LEVEL_INFO,'Packaging in adapter snippets...');
$snippets = include $sources['data'].'transport.snippets.adapters.php';
if (empty($snippets)) $modx->log(modX::LOG_LEVEL_ERROR,'Could not package in adapter snippets.');
$adaptersCategory->addMany($snippets);

/* add adapter plugins */
$modx->log(modX::LOG_LEVEL_INFO,'Packaging in adapter plugins...');
$plugins = include $sources['data'].'transport.plugins.adapters.php';
if (empty($plugins)) $modx->log(modX::LOG_LEVEL_ERROR,'Could not package in adapter plugins.');
$adaptersCategory->addMany($plugins);
 
/* add chunks */
$modx->log(modX::LOG_LEVEL_INFO,'Packaging in chunks...');
$chunks = include $sources['data'].'transport.chunks.php';
if (empty($chunks)) $modx->log(modX::LOG_LEVEL_ERROR,'Could not package in chunks.');
$category->addMany($chunks);
 
/* copy files */

// Videobox-JS
copy($sources['root'] . 'node_modules/videobox/dist/videobox.min.css', $sources['root'] . 'assets/components/videobox/css/videobox.min.css');
copy($sources['root'] . 'node_modules/videobox/dist/videobox.css.map', $sources['root'] . 'assets/components/videobox/css/videobox.css.map');
copy($sources['root'] . 'node_modules/videobox/dist/overrides.min.css', $sources['root'] . 'assets/components/videobox/css/overrides.min.css');
copy($sources['root'] . 'node_modules/videobox/dist/overrides.css.map', $sources['root'] . 'assets/components/videobox/css/overrides.css.map');
copy($sources['root'] . 'node_modules/videobox/dist/videobox.bundle.js', $sources['root'] . 'assets/components/videobox/js/videobox.bundle.js');
copy($sources['root'] . 'node_modules/videobox/dist/videobox.bundle.map', $sources['root'] . 'assets/components/videobox/js/videobox.bundle.map');

copy($sources['root'] . 'node_modules/videobox/dist/nobg_audio.png', $sources['root'] . 'assets/components/videobox/img/nobg_audio.png');
copy($sources['root'] . 'node_modules/videobox/dist/nobg_video.png', $sources['root'] . 'assets/components/videobox/img/nobg_video.png');

// VideoJS
copy($sources['root'] . 'node_modules/video.js/dist/video.min.js', $sources['root'] . 'assets/components/videobox/video-js/video.min.js');

/* create category vehicle */
$vehicle = $builder->createVehicle($adaptersCategory, array(
    xPDOTransport::UNIQUE_KEY => 'category',
    xPDOTransport::PRESERVE_KEYS => false,
    xPDOTransport::UPDATE_OBJECT => true,
    xPDOTransport::RELATED_OBJECTS => true,
    xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array (
		'Plugins' => array (
			xPDOTransport::PRESERVE_KEYS => false,
			xPDOTransport::UPDATE_OBJECT => BUILD_PLUGIN_UPDATE,
			xPDOTransport::UNIQUE_KEY => 'name',
		),
        'Snippets' => array(
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::UNIQUE_KEY => 'name',
        ),
		'Chunks' => array (
			xPDOTransport::PRESERVE_KEYS => false,
			xPDOTransport::UPDATE_OBJECT => true,
			xPDOTransport::UNIQUE_KEY => 'name',
		),
		'Parent' => array (
			xPDOTransport::PRESERVE_KEYS => false,
			xPDOTransport::UPDATE_OBJECT => true,
			xPDOTransport::UNIQUE_KEY => 'category',
			xPDOTransport::RELATED_OBJECTS => true,
			xPDOTransport::RELATED_OBJECT_ATTRIBUTES => array (
				'Snippets' => array(
					xPDOTransport::PRESERVE_KEYS => false,
					xPDOTransport::UPDATE_OBJECT => true,
					xPDOTransport::UNIQUE_KEY => 'name',
				),
				'Chunks' => array (
					xPDOTransport::PRESERVE_KEYS => false,
					xPDOTransport::UPDATE_OBJECT => true,
					xPDOTransport::UNIQUE_KEY => 'name',
				),
			),
		),
    ),
));
$modx->log(modX::LOG_LEVEL_INFO,'Adding file resolvers to category...');
$vehicle->resolve('file',array(
    'source' => $sources['source_assets'],
    'target' => "return MODX_ASSETS_PATH . 'components/';",
	xPDOTransport::FILE_RESOLVE_OPTIONS => array(
		'copy_exclude_patterns' => array('/cache/', '/\.zip/', '/(?<!\.min|?<!\.bundle)\.(js|css)/'),
	),
));
$vehicle->resolve('file',array(
    'source' => $sources['source_core'],
    'target' => "return MODX_CORE_PATH . 'components/';",
));

$modx->log(modX::LOG_LEVEL_INFO,'Adding in PHP resolvers...');
$vehicle->resolve('php',array(
    'source' => $sources['resolvers'] . 'resolve.events.php',
));

$builder->putVehicle($vehicle);
 
/* zip up package */
$modx->log(modX::LOG_LEVEL_INFO,'Packing up transport package zip...');
$modx->log(modX::LOG_LEVEL_INFO,'Adding package attributes and setup options...');
$builder->setPackageAttributes(array(
	'name' => PKG_NAME,
	'author' => 'HitkoDev', 
    'license' => file_get_contents($sources['root'] . 'LICENSE.md'),
    'readme' => file_get_contents($sources['root'] . 'README.md'),
    'changelog' => file_get_contents($sources['root'] . 'CHANGELOG.md'),
));
$builder->pack();
 
$tend= explode(" ", microtime());
$tend= $tend[1] + $tend[0];
$totalTime= sprintf("%2.4f s",($tend - $tstart));
$modx->log(modX::LOG_LEVEL_INFO,"\n<br />Package Built.<br />\nExecution time: {$totalTime}\n");
 
 
session_write_close();
exit ();
