<?php
/**
 * Main template file.
 *
 * @package {{theme_slug}}
 */

use PressGang\Controllers\PostsController;

$controller = new PostsController();
$controller->render();
