<?php
/**
 * Found by ControllerBase::findTemplate(), which builds the name from the
 * controller's file name and the action that called render().
 *
 * @var \Cog\Test\fixtures\ControllerBase\FixtureBaseController $controller
 * @var \Symfony\Component\HttpFoundation\Request $request
 */
?>
shown for <?= $request->getPathInfo() ?>
