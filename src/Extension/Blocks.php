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

use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

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
        //https://dev.npeu.ox.ac.uk/administrator/index.php?option=com_ajax&format=raw&plugin=getModuleById

        $app = Factory::getApplication();
        if (!$app->isClient('administrator')) {
            return; // Only run in admin
        }
        $user = $app->getSession()->get('user');
        #echo '<pre>'; var_dump($user); echo '<pre>'; exit;
        if(!$user->authorise('core.create', 'com_blocks')) {
            return; // Only authorised users
        }

        $input     = $app->getInput();
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

        $module_data = $db->loadObject();
        $module_data->params = json_decode($module_data->params);

        #$output = json_encode($module_data);
        $output = $module_data;

        #echo '<pre>'; var_dump($module_data); echo '<pre>'; exit;
        #echo '<pre>'; var_dump($output); echo '<pre>'; exit;
        /*
        So, rendering normal templates here doesn't work because we're in admin (at least I can't
        find a way).
        ALso it might be quite liberating and flexible to allow each module to provide its own Twig
        template (possibly hidden in admin config rather than user-specified, not sure).
        So here we should either get that temolate and add to data or render it and return the
        HTML output - I'm not sure at this stage. Probably render it and add the rendered output
        to the data so we have both for whatever reason.


        */
        // This is temporary - we need to run this through the Twig template but that doesn't
        // exist yet.
        $output->rendered_output = $module_data->content;

        #$module = ModuleHelper::getModuleById((int) $module_id);
        #echo '<pre>'; var_dump($module); echo '<pre>'; exit;

        #$module->displayFull = true;
        #echo ModuleHelper::renderModule($module);
        #echo HTMLHelper::_('content.prepare', '{loadmoduleid ' . $module_id . '}');



        #exit;

        if ($event instanceof ResultAwareInterface) {
            $event->addResult($output);
        } else {
            $result = $event->getArgument('result') ?? [];
            $result[] = $output;
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
        if ($option != 'com_menus') {
            return; // Only run in com_menus
        }

        $dir = str_replace(JPATH_ROOT, '', dirname(dirname(__DIR__)));
        $document = Factory::getDocument();

        $document->addStyleSheet($dir . '/assets/css/blocks-admin.css');

        $document->addScript($dir . '/assets/js/blocks-admin.js');
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