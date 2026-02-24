<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.Blocks
 *
 * @copyright   Copyright (C) NPEU 2025.
 * @license     MIT License; see LICENSE.md
 */

namespace NPEU\Plugin\System\Blocks\Extension;

defined('_JEXEC') or die;

/*
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
*/
use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Table\Module as ModuleTable;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;


use NPEU\Plugin\System\Blocks\Helper\BlocksHelper;


use Joomla\CMS\Log\Log;


Log::addLogger(
    array('text_file' => 'debug-ajax.php'),
    Log::ALL,
    array('plg_blocks') // change to your component/plugin name
);


/**
 * Plugin for Blocks component.
 */
class Blocks extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    /**
     * An internal flag whether plugin should listen any event.
     *
     * @var bool
     *
     * @since   4.3.0
     */
    protected static $enabled = false;

    /**
     * Constructor
     *
     */
    public function __construct($subject, array $config = [], bool $enabled = true)
    {
        // The above enabled parameter was taken from the Guided Tour plugin but it always seems
        // to be false so I'm not sure where this param is passed from. Overriding it for now.
        $enabled = true;


        #$this->loadLanguage();
        $this->autoloadLanguage = $enabled;
        self::$enabled          = $enabled;

        parent::__construct($subject, $config);
    }


    /**
     * Resolve a SITE module layout path (works when running in administrator)
     *
     * @param  string  $moduleName  e.g. 'mod_custom' or 'mod_mything'
     * @param  string  $layout      e.g. 'default'
     * @return string|false         full path to layout file or false if not found
     */
    public function resolveSiteModuleLayoutPath(string $moduleName, string $layout = 'default')
    {
        // Normalize inputs
        $moduleName = trim($moduleName);
        $layout = trim($layout) ?: 'default';

        // candidate 1: active site template override

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select($db->quoteName('template'))
            ->from($db->quoteName('#__template_styles'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('home') . ' = 1')
            ->order($db->quoteName('id') . ' DESC')
            ->setLimit(1);

        try {
            $db->setQuery($query);
            $template = $db->loadResult();
        } catch (\Throwable $e) {
            $template = null;
        }
        Log::add('template: ' . $template, \Joomla\CMS\Log\Log::INFO, 'plg_blocks');
        if (!empty($template)) {
            $tplOverride = JPATH_SITE . '/templates/' . $template . '/html/' . $moduleName . '/' . $layout . '.php';
            if (is_file($tplOverride)) {
                return $tplOverride;
            }
        }

        // candidate 2: module's own tmpl folder
        $moduleTmpl = JPATH_SITE . '/modules/' . $moduleName . '/tmpl/' . $layout . '.php';
        if (is_file($moduleTmpl)) {
            return $moduleTmpl;
        }

        // candidate 3: sometimes module uses layout files directly under module (older styles)
        $moduleLayoutRoot = JPATH_SITE . '/modules/' . $moduleName . '/' . $layout . '.php';
        if (is_file($moduleLayoutRoot)) {
            return $moduleLayoutRoot;
        }

        // not found
        return false;
    }

    /**
     * function for getSubscribedEvents : new Joomla 4 feature
     *
     * @return array
     *
     * @since   4.3.0
     */
    public static function getSubscribedEvents(): array
    {
        return self::$enabled ? [
            'onAfterDispatch'      => 'onAfterDispatch',
            'onBeforeRender'       => 'onBeforeRender',
            'onAfterRender'        => 'onAfterRender',
            'onAjaxGetModuleById'  => 'onAjaxGetModuleById',
            'onContentPrepareData' => 'onContentPrepareData'
        ] : [];
    }

    /**
     * The save event.
     *
     * @param   Event  $event
     *
     * @return  boolean
     */
    /*public function onContentBeforeSave(Event $event): void
    {
        [$context, $object, $isNew, $data] = array_values($event->getArguments());

        echo '<pre>'; var_dump($data['link']); echo '<pre>';
        #echo '<pre>'; var_dump($data); echo '<pre>'; exit;


        // Check if we're saving a blocks item:
        if ($context == 'com_menus.item' && strpos($data['link'], 'option=com_blocks&view=blocks') !== false) {

            #echo '<pre>'; var_dump($data); echo '<pre>';

            $params = $data['params'];
            $rows   = $params['rows'];

            $content    = '';
            $module_ids = [];

            if (empty($rows)) {
                return;
            }

            $db = Factory::getDBO();

            foreach ($rows as $row) {
                for ($i = 1; $i<= 3; $i++) {
                    $module_id = $row['block_form_' . $i];

                    if ($module_id > 0) {
                        $module_ids[] = $module_id;
                    }
                }
            }

            if (empty($module_ids)) {
                return;
            }

            $query = $db->getQuery(true);
            $query->select('*')
                  ->from($db->quoteName('#__modules'))
                  ->where($db->quoteName('id') . ' IN (' . implode(',', $module_ids) . ')');
            $db->setQuery($query);

            $modules = $db->loadAssocList();

            echo '<pre>'; var_dump($modules); echo '<pre>';

        }
        exit;
        return;
    }*/

    /**
     * Get module output for admin preivew.
     */
    public function onAjaxGetModuleById(Event $event): void
    {
        //https://www.npeu.ox.ac.uk/administrator/index.php?option=com_ajax&format=raw&plugin=getModuleById

        // Note: I've spent a long time on this, trying to get a standard Joomla approach to loading
        // modules to work, but it won't. This is because we need to load and render a SITE module
        // but we're in an ADMIN context, so the usual stuff doesn't work.
        // So be aware that we HAVE to use the DB to get the module object, and AI helped to write
        // the template layout path function to find the right template for it to render.

        $app = Factory::getApplication();

        // Log application and user info
        Log::add('AJAX called. App client: ' . $app->getName(), Log::INFO, 'plg_blocks');

        if (!$app->isClient('administrator')) {
            return; // Only run in admin
        }
        $user = $app->getSession()->get('user');

        if(!$user->authorise('core.create', 'com_blocks')) {
            return; // Only authorised users
        }

        $input     = $app->getInput();
        $module    = false;
        $module_id = $input->get('module_id', false);

        if (!$module_id || !is_numeric($module_id)) {
            return; // Must have a module ID.
        }


        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('id') . ' = ' . $module_id);
        $db->setQuery($query);

        $module = $db->loadObject();


        $module->params = json_decode($module->params);

        $layout = 'default';

        $layoutPath = $this->resolveSiteModuleLayoutPath($module->module, $layout);

        if (!$layoutPath) {
            Log::add('Module layout not found for ' . $module->module . ' layout ' . $layout, \Joomla\CMS\Log\Log::WARNING, 'plg_blocks');
            return;
        }

        if (!is_file($layoutPath)) {
            return;
        }

        $params  = new \Joomla\Registry\Registry($module->params ?? '');
        $attribs = [];

        ob_start();

        try {
            include $layoutPath;
        } catch (\Throwable $e) {
            ob_end_clean();
            return;
        }

        $module->rendered_output = ob_get_clean();




        if ($event instanceof ResultAwareInterface) {
            $event->addResult($module);
        } else {
            $result = $event->getArgument('result') ?? [];
            $result[] = $module;
            $event->setArgument('result', $result);
        }

    }


    /**
     * Add CSS and JS for admin.
     */
    public function onBeforeRender(Event $event): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator')) {
            return; // Only run in admin
        }

        $option = $app->input->get('option');
        if (! ($option == 'com_menus' || $option == 'com_modules')) {
            return; // Only run in com_menus and com_modules
        }

        $dir = str_replace(JPATH_ROOT, '', dirname(dirname(__DIR__)));
        $document = Factory::getDocument();

        $document->addStyleSheet($dir . '/assets/css/blocks-admin.css');

        $document->addScript($dir . '/assets/js/blocks-admin.js');
    }


        /**
     * Add CSS and JS for admin.
     */
    public function onAfterRender(Event $event): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator')) {
            return; // Only run in admin
        }

        $option = $app->input->get('option');
        if (! ($option == 'com_menus' || $option == 'com_modules')) {
            return; // Only run in com_menus and com_modules
        }

        $sprite = BlocksHelper::getSpriteHtml();
        if ($sprite === '') {
            return;
        }

        $body = $app->getBody();

        // Avoid double injection
        if (str_contains($body, 'id="' . BlocksHelper::getIdPrefix())) {
            return;
        }

        $pos = strripos($body, '</body>');
        if ($pos !== false) {
            $body = substr_replace($body, $sprite . PHP_EOL, $pos, 0);
        } else {
            $body .= $sprite;
        }

        $app->setBody($body);
    }



    /**
     * Add CSS and JS for site.
     */
    public function onAfterDispatch(Event $event): void
    {
        $app = Factory::getApplication();

        if ($app->isClient('administrator')) {
            return; // Don't run in admin
        }

        $dir = str_replace(JPATH_ROOT, '', dirname(dirname(__DIR__)));
        $document = Factory::getDocument();

        $document->addStyleSheet($dir . '/assets/css/blocks.css');
    }

    /**
     * Add page assignment to new module
     *
     * @param   Event  $event
     *
     * @return  boolean
     */
    public function onContentPrepareData(Event $event): void
    {
        [$context, $data] = array_values($event->getArguments());

        if ($context != 'com_modules.module') {
            return;
        }

        $app    = Factory::getApplication();
        $option = $app->input->get('option');

        $session = $app->getSession();

        if ($app->isClient('administrator') && $option == 'com_modules' && is_null($data->id)) {
            $data->assigned = [$session->get('blocks_page_id')];
            $data->assignment = '1';
        }

    }
}
