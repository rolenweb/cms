<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */


namespace craft\test\mockclasses\controllers;


use craft\web\Controller;

/**
 * Unit tests for TestController
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 3.0
 */
class TestController extends Controller
{
    protected $allowAnonymous = ['allow-anonymous'];

    public function actionNotAllowAnonymous()
    {

    }
    public function actionAllowAnonymous()
    {

    }
}