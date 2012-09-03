<?php

if (!defined('TL_ROOT'))
    die('You can not access this file directly!');

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2011 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  MEN AT WORK 2012
 * @package    generalDriver
 * @license    GNU/LGPL
 * @filesource
 */
class GeneralControllerDefault extends Controller implements InterfaceGeneralController
{
    /* /////////////////////////////////////////////////////////////////////////
     * -------------------------------------------------------------------------
     * Vars
     * -------------------------------------------------------------------------
     * ////////////////////////////////////////////////////////////////////// */

    // Obejcts -----------------------

    /**
     * Contao Session Object
     * @var Session
     */
    protected $objSession = null;

    /**
     * Current DC General
     * @var DC_General
     */
    protected $objDC = null;

    /**
     * Current dataprovider
     * @var InterfaceGeneralData
     */
    protected $objDataProvider = null;

    // Current -----------------------

    /**
     * Current DCA
     * @var array
     */
    protected $arrDCA;

    /**
     * A list with all current ID`s
     * @var array
     */
    protected $arrInsertIDs = array();

    // States ------------------------

    /**
     * State of Show/Close all
     * @var boolean
     */
    protected $blnShowAllEntries = false;

    // Misc. -------------------------

    /**
     * Error msg
     *
     * @var string
     */
    protected $notImplMsg = "<div style='text-align:center; font-weight:bold; padding:40px;'>The function/view &quot;%s&quot; is not implemented.</div>";

    /**
     * Field for the function sortCollection
     *
     * @var string $arrColSort
     */
    protected $arrColSort;

    // Const -------------------------

    /**
     * Const Language
     */

    const LANGUAGE_SL = 1;
    const LANGUAGE_ML = 2;

    /* /////////////////////////////////////////////////////////////////////////
     * -------------------------------------------------------------------------
     * Magic functions
     * -------------------------------------------------------------------------
     * ////////////////////////////////////////////////////////////////////// */

    public function __construct()
    {
        parent::__construct();

        // Init Helper
        $this->objSession = Session::getInstance();

        // Check some vars
        $this->blnShowAllEntries = ($this->Input->get('ptg') == 'all') ? 1 : 0;
    }

    public function __call($name, $arguments)
    {
        switch ($name)
        {
            default:
                return sprintf($this->notImplMsg, $name);
                break;
        };
    }

    /**
     * Load the current model from driver
     */
    protected function loadCurrentModel()
    {
        $this->objCurrentModel = $this->objDC->getCurrentModel();
    }

    /**
     * Load the dca from driver
     */
    protected function loadCurrentDCA()
    {
        $this->arrDCA = $this->objDC->getDCA();
    }

    /**
     * Get the dataprovider from DC
     */
    protected function loadCurrentDataProvider()
    {
        $this->objDataProvider = $this->objDC->getDataProvider();
    }

    /* /////////////////////////////////////////////////////////////////////////
     * -------------------------------------------------------------------------
     * Getter & Setter
     * -------------------------------------------------------------------------
     * ////////////////////////////////////////////////////////////////////// */

    /**
     * Get DC General
     * @return DC_General
     */
    public function getDC()
    {
        return $this->objDC;
    }

    /**
     * Set DC General
     * @param DC_General $objDC
     */
    public function setDC($objDC)
    {
        $this->objDC = $objDC;
    }

    /**
     * Get filter for the data provider
     * @return array();
     */
    protected function getFilter()
    {
        $arrFilterIds = $this->arrDCA['list']['sorting']['root'];

        // TODO implement panel filter from session
        $arrFilter = $this->objDC->getFilter();
        if (is_array($arrFilterIds) && !empty($arrFilterIds))
        {
            if (is_null($arrFilter))
            {
                $arrFilter = array();
            }

            $arrFilter['id'] = array_map('intval', $arrFilterIds);
        }

        return $arrFilter;
    }

    /**
     * Get limit for the data provider
     * @return array
     */
    protected function getLimit()
    {
        $arrLimit = array(0, 0);
        if (!is_null($this->objDC->getLimit()))
        {
            $arrLimit = explode(',', $this->objDC->getLimit());
        }

        return $arrLimit;
    }

    /**
     * Get sorting for the list view data provider
     *
     * @return mixed
     */
    protected function getListViewSorting()
    {
        $mixedOrderBy = $this->arrDCA['list']['sorting']['fields'];

        if (is_null($this->objDC->getFirstSorting()))
        {
            $this->objDC->setFirstSorting(preg_replace('/\s+.*$/i', '', $mixedOrderBy[0]));
        }

        // Check if current sorting is set
        if (!is_null($this->objDC->getSorting()))
        {
            $mixedOrderBy = $this->objDC->getSorting();
        }

        if (is_array($mixedOrderBy) && $mixedOrderBy[0] != '')
        {
            foreach ($mixedOrderBy as $key => $strField)
            {
                if ($this->arrDCA['fields'][$strField]['eval']['findInSet'])
                {
                    $arrOptionsCallback = $this->objDC->getCallbackClass()->optionsCallback($strField);

                    if (!is_null($arrOptionsCallback))
                    {
                        $keys = $arrOptionsCallback;
                    }
                    else
                    {
                        $keys = $this->arrDCA['fields'][$strField]['options'];
                    }

                    if ($this->arrDCA['fields'][$v]['eval']['isAssociative'] || array_is_assoc($keys))
                    {
                        $keys = array_keys($keys);
                    }

                    $mixedOrderBy[$key] = array(
                        'field'  => $strField,
                        'keys'   => $keys,
                        'action' => 'findInSet'
                    );
                }
                else
                {
                    $mixedOrderBy[$key] = $strField;
                }
            }
        }

        // Set sort order
        if ($this->arrDCA['list']['sorting']['mode'] == 1 && ($this->arrDCA['list']['sorting']['flag'] % 2) == 0)
        {
            $mixedOrderBy['sortOrder'] = " DESC";
        }

        return $mixedOrderBy;
    }

    /**
     * Get sorting for the parent view data provider
     *
     * @return mixed
     */
    protected function getParentViewSorting()
    {
        $mixedOrderBy = array();
        $firstOrderBy = array();

        // Check if current sorting is set
        if (!is_null($this->objDC->getSorting()))
        {
            $mixedOrderBy = $this->objDC->getSorting();
        }

        if (is_array($mixedOrderBy) && $mixedOrderBy[0] != '')
        {
            $firstOrderBy = preg_replace('/\s+.*$/i', '', $mixedOrderBy[0]);

            // Order by the foreign key
            if (isset($this->arrDCA['fields'][$firstOrderBy]['foreignKey']))
            {
                $key = explode('.', $this->arrDCA['fields'][$firstOrderBy]['foreignKey'], 2);

                $this->foreignKey = true;

                // TODO remove sql
                $this->arrFields = array(
                    '*',
                    "(SELECT " . $key[1] . " FROM " . $key[0] . " WHERE " . $this->objDC->getTable() . "." . $firstOrderBy . "=" . $key[0] . ".id) AS foreignKey"
                );

                $mixedOrderBy[0] = 'foreignKey';
            }
        }
        else if (is_array($GLOBALS['TL_DCA'][$this->objDC->getTable()]['list']['sorting']['fields']))
        {
            $mixedOrderBy = $GLOBALS['TL_DCA'][$this->objDC->getTable()]['list']['sorting']['fields'];
            $firstOrderBy = preg_replace('/\s+.*$/i', '', $mixedOrderBy[0]);
        }

        if (is_array($mixedOrderBy) && $mixedOrderBy[0] != '')
        {
            foreach ($mixedOrderBy as $key => $strField)
            {
                $mixedOrderBy[$key] = $strField;
            }
        }

        $this->objDC->setFirstSorting($firstOrderBy);

        return $mixedOrderBy;
    }

    /* /////////////////////////////////////////////////////////////////////////
     * -------------------------------------------------------------------------
     *  Core Support functions
     * -------------------------------------------------------------------------
     * ////////////////////////////////////////////////////////////////////// */

    /**
     * Redirects to the real back end module.
     */
    protected function redirectHome()
    {
        if ($this->Input->get('table') && $this->Input->get('id'))
        {
            $this->redirect(sprintf('contao/main.php?do=%s&table=%s&id=%s', $this->Input->get('do'), $this->objDC->getTable(), $this->Input->get('id')));
        }

        $this->redirect('contao/main.php?do=' . $this->Input->get('do'));
    }

    /**
     * Check if the curren model support multi language.
     * Load the language from SESSION, POST or use a fallback.
     *
     * @param DC_General $this->objDC
     * 
     * @return int return the mode multilanguage, singellanguage or unsupportet language
     */
    protected function checkLanguage()
    {
        // Load basic informations
        $intID                 = $this->objDC->getId();
        $objDataProvider       = $this->objDC->getDataProvider();
        $objLanguagesSupported = $this->objDC->getDataProvider()->getLanguages($intID);

        // Load language from Session
        $arrSession = $this->Session->get("dc_general");
        if (!is_array($arrSession))
        {
            $arrSession = array();
        }

        // try to get the language from session
        if (isset($arrSession["ml_support"][$this->objDC->getTable()][$intID]))
        {
            $strCurrentLanguage = $arrSession["ml_support"][$this->objDC->getTable()][$intID];
        }
        else
        {
            $strCurrentLanguage = $GLOBALS['TL_LANGUAGE'];
        }

        //Check if we have some languages
        if ($objLanguagesSupported == null)
        {
            return self::LANGUAGE_SL;
        }

        // Make a array from the collection
        $arrLanguage = array();
        foreach ($objLanguagesSupported as $value)
        {
            $arrLanguage[$value->getID()] = $value->getProperty("name");
        }

        // Get/Check the new language
        if (strlen($this->Input->post("language")) != 0 && $_POST['FORM_SUBMIT'] == 'language_switch')
        {
            if (key_exists($this->Input->post("language"), $arrLanguage))
            {
                $strCurrentLanguage                                         = $this->Input->post("language");
                $arrSession["ml_support"][$this->objDC->getTable()][$intID] = $strCurrentLanguage;
            }
            else if (key_exists($strCurrentLanguage, $arrLanguage))
            {
                $arrSession["ml_support"][$this->objDC->getTable()][$intID] = $strCurrentLanguage;
            }
            else
            {
                $objlanguageFallback                                        = $objDataProvider->getFallbackLanguage();
                $strCurrentLanguage                                         = $objlanguageFallback->getID();
                $arrSession["ml_support"][$this->objDC->getTable()][$intID] = $strCurrentLanguage;
            }
        }

        $this->Session->set("dc_general", $arrSession);

        $objDataProvider->setCurrentLanguage($strCurrentLanguage);

        return self::LANGUAGE_ML;
    }

    /* /////////////////////////////////////////////////////////////////////////
     * -------------------------------------------------------------------------
     * Clipboard functions
     * -------------------------------------------------------------------------
     * ////////////////////////////////////////////////////////////////////// */

    /**
     * Clear clipboard
     * @param boolean $blnRedirect True - redirect to home site
     */
    protected function resetClipboard($blnRedirect = false)
    {
        // Get clipboard
        $arrClipboard = $this->loadClipboard();

        $this->objDC->setClipboardState(false);
        unset($arrClipboard[$this->objDC->getTable()]);

        // Save 
        $this->saveClipboard($arrClipboard);

        // Redirect
        if ($blnRedirect == true)
        {
            $this->redirectHome();
        }

        // Set DC state
        $this->objDC->setClipboardState(false);
    }

    /**
     * Check clipboard state. Clear or save state of it.
     */
    protected function checkClipboard()
    {
        $arrClipboard = $this->loadClipboard();

        // Reset Clipboard
        if ($this->Input->get('clipboard') == '1')
        {
            $this->resetClipboard(true);
        }
        // Add new entry
        else if ($this->Input->get('act') == 'paste')
        {
            $this->objDC->setClipboardState(true);

            $arrClipboard[$this->objDC->getTable()] = array(
                'id'     => $this->Input->get('id'),
                'childs' => $this->Input->get('childs'),
                'mode'   => $this->Input->get('mode')
            );

            switch ($this->Input->get('mode'))
            {
                case 'cut':
                    // Id Array
                    $arrIDs = array();
                    $arrIDs[] = $this->Input->get('id');

                    // Get the join field
                    $arrJoinCondition = $this->objDC->getJoinConditions('self');

                    // Run each id
                    for ($i = 0; $i < count($arrIDs); $i++)
                    {
                        // Get current model
                        $objCurrentConfig = $this->objDC->getDataProvider()->getEmptyConfig();
                        $objCurrentConfig->setId($arrIDs[$i]);
                        $objCurrentConfig->setFields(array($arrJoinCondition[0]['srcField']));

                        $objCurrentModel = $this->objDC->getDataProvider()->fetch($objCurrentConfig);

                        $strFilter = $arrJoinCondition[0]['dstField'] . $arrJoinCondition[0]['operation'] . $objCurrentModel->getProperty($arrJoinCondition[0]['srcField']);

                        $objChildConfig = $this->objDC->getDataProvider()->getEmptyConfig();
                        $objChildConfig->setFilter(array($strFilter));
                        $objChildConfig->setIdOnly(true);

                        $objChildCollection = $this->objDC->getDataProvider()->fetchAll($objChildConfig);

                        foreach ($objChildCollection as $key => $value)
                        {
                            $arrIDs[] = $value;
                        }
                    }

                    $arrClipboard[$this->objDC->getTable()]['ignoredIDs'] = $arrIDs;

                    break;
            }

            $this->objDC->setClipboard($arrClipboard[$this->objDC->getTable()]);
        }
        // Check clipboard from session
        else if (key_exists($this->objDC->getTable(), $arrClipboard))
        {
            $this->objDC->setClipboardState(true);
            $this->objDC->setClipboard($arrClipboard[$this->objDC->getTable()]);
        }

        $this->saveClipboard($arrClipboard);
    }

    protected function loadClipboard()
    {
        $arrClipboard = $this->Session->get('CLIPBOARD');
        if (!is_array($arrClipboard))
        {
            $arrClipboard = array();
        }

        return $arrClipboard;
    }

    protected function saveClipboard($arrClipboard)
    {
        if (is_array($arrClipboard))
        {
            $this->Session->set('CLIPBOARD', $arrClipboard);
        }
    }

    /* /////////////////////////////////////////////////////////////////////////
     * -------------------------------------------------------------------------
     * Check Function
     * -------------------------------------------------------------------------
     * ////////////////////////////////////////////////////////////////////// */

    /**
     * Check if is editable AND not clodes
     */
    protected function checkIsWritable()
    {
        // Check if table is editable
        if (!$this->objDC->isEditable())
        {
            $this->log('Table ' . $this->objDC->getTable() . ' is not editable', 'DC_General - Controller - copy()', TL_ERROR);
            $this->redirect('contao/main.php?act=error');
        }

        // Check if table is editable
        if ($this->objDC->isClosed())
        {
            $this->log('Table ' . $this->objDC->getTable() . ' is closed', 'DC_General - Controller - copy()', TL_ERROR);
            $this->redirect('contao/main.php?act=error');
        }
    }

    /* /////////////////////////////////////////////////////////////////////////
     * -------------------------------------------------------------------------
     * Core Functions
     * -------------------------------------------------------------------------
     * ////////////////////////////////////////////////////////////////////// */

    /**
     * Cut and paste
     */
    public function cut()
    {
        // Load some vars
        $this->loadCurrentDCA();
        $this->loadCurrentDataProvider();

        $intMode = $this->Input->get('mode');
        $intPid  = $this->Input->get('pid');
        $intId   = $this->Input->get('id');

        // Checks
        $this->checkIsWritable();

        if (strlen($intMode) == 0 || strlen($intPid) == 0 || strlen($intId) == 0)
        {
            $this->log('Missing parameter for copy in ' . $this->objDC->getTable(), 'DC_General - Controller - copy()', TL_ERROR);
            $this->redirect('contao/main.php?act=error');
        }

        // Load the source model
        $objSrcModel = $this->objDataProvider->fetch($this->objDataProvider->getEmptyConfig()->setId($intId));

        // Check mode
        switch ($this->arrDCA['list']['sorting']['mode'])
        {
            case 5:
                switch ($intMode)
                {
                    // Insert After => Get the parent from he target id
                    case 1:
                        $this->setParent($objSrcModel, $this->getParent('self', null, $intPid), 'self');
                        break;

                    // Insert Into => use the pid
                    case 2:
                        if ($this->isRootEntry('self', $intPid))
                        {
                            $this->setRoot($objSrcModel, 'self');
                        }
                        else
                        {
                            $objParentConfig = $this->objDC->getDataProvider()->getEmptyConfig();
                            $objParentConfig->setId($intPid);

                            $objParentModel = $this->objDC->getDataProvider()->fetch($objParentConfig);

                            $this->setParent($objSrcModel, $objParentModel, 'self');
                        }
                        break;

                    default:
                        $this->log('Unknown create mode for copy in ' . $this->objDC->getTable(), 'DC_General - Controller - copy()', TL_ERROR);
                        $this->redirect('contao/main.php?act=error');
                        break;
                }

                $this->objDataProvider->save($objSrcModel);
                break;

            default:
                return vsprintf($this->notImplMsg, 'cut - Mode ' . $this->arrDCA['list']['sorting']['mode']);
                break;
        }

        // Reset clipboard + redirect
        $this->resetClipboard(true);
    }

    /**
     * Copy a entry and all childs
     * 
     * @return string error msg for an unknown mode
     */
    public function copy()
    {
        // Load current DCA
        $this->loadCurrentDCA();
        $this->loadCurrentDataProvider();

        // Check 
        $this->checkIsWritable();

        switch ($this->arrDCA['list']['sorting']['mode'])
        {
            case 5:
                // Init Vars
                $intMode   = $this->Input->get('mode');
                $intPid    = $this->Input->get('pid');
                $intId     = $this->Input->get('id');
                $intChilds = $this->Input->get('childs');

                if (strlen($intMode) == 0 || strlen($intPid) == 0 || strlen($intId) == 0)
                {
                    $this->log('Missing parameter for copy in ' . $this->objDC->getTable(), 'DC_General - Controller - copy()', TL_ERROR);
                    $this->redirect('contao/main.php?act=error');
                }

                // Get the join field
                $arrJoinCondition = $this->objDC->getJoinConditions('self');

                // Insert the copy
                $this->insertCopyModel($intId, $intPid, $intMode, $intChilds, $arrJoinCondition[0]['srcField'], $arrJoinCondition[0]['dstField'], $arrJoinCondition[0]['operation']);
                break;

            default:
                return vsprintf($this->notImplMsg, 'copy - Mode ' . $this->arrDCA['list']['sorting']['mode']);
                break;
        }

        // Reset clipboard + redirect
        $this->resetClipboard(true);
    }

    /**
     * Create a new entry
     */
    public function create()
    {
        // Load current values
        $this->loadCurrentDCA();
        $this->loadCurrentDataProvider();

        // Check
        $this->checkIsWritable();

        // Load fields and co
        $this->objDC->loadEditableFields();
        $this->objDC->setWidgetID($this->objDC->getId());

        // Check if we have fields
        if (!$this->objDC->hasEditableFields())
        {
            $this->redirect($this->getReferer());
        }

        // Load something
        $this->objDC->preloadTinyMce();

        // Check load multi language check
        $this->checkLanguage($this->objDC);

        // Set buttons
        $this->objDC->addButton("save");
        $this->objDC->addButton("saveNclose");

        // Load record from data provider
        $objDBModel = $this->objDataProvider->getEmptyModel();
        $this->objDC->setCurrentModel($objDBModel);

        if ($this->arrDCA['list']['sorting']['mode'] == 5 && $this->Input->get('mode') != '')
        {
            // check if the pid id/word is set
            if ($this->Input->get('pid') == '')
            {
                $this->log('Missing pid for new entry in ' . $this->objDC->getTable(), 'DC_General - Controller - create()', TL_ERROR);
                $this->redirect('contao/main.php?act=error');
            }

            switch ($this->Input->get('mode'))
            {
                case 1:
                    $this->setParent($objDBModel, $this->getParent('self', $objCurrentModel, $this->Input->get('pid')), 'self');
                    break;

                case 2:
                    if ($this->isRootEntry('self', $this->Input->get('pid')))
                    {
                        $this->setRoot($objDBModel, 'self');
                    }
                    else
                    {
                        $objParentConfig = $this->objDC->getDataProvider()->getEmptyConfig();
                        $objParentConfig->setId($this->Input->get('pid'));

                        $objParentModel = $this->objDC->getDataProvider()->fetch($objParentConfig);

                        $this->setParent($objDBModel, $objParentModel, 'self');
                    }
                    break;

                default:
                    $this->log('Unknown create mode for new entry in ' . $this->objDC->getTable(), 'DC_General - Controller - create()', TL_ERROR);
                    $this->redirect('contao/main.php?act=error');
                    break;
            }

            // Reste clipboard
            $this->resetClipboard();
        }

        // Check if we have a auto submit
        if ($this->objDC->isAutoSubmitted())
        {
            // process input and update changed properties.
            foreach (array_keys($this->objDC->getFieldList()) as $key)
            {
                $this->objDC->processInput($key);
            }
        }

        // Check submit
        if ($this->objDC->isSubmitted() == true)
        {
            if (isset($_POST["save"]))
            {
                // process input and update changed properties.
                if (($objModell = $this->doSave($this->objDC)) !== false)
                {
                    // Callback
                    $this->objDC->getCallbackClass()->oncreateCallback($objDBModel->getID(), $objDBModel->getPropertiesAsArray());
                    // Log
                    $this->log('A new entry in table "' . $this->objDC->getTable() . '" has been created (ID: ' . $objModell->getID() . ')', 'DC_General - Controller - create()', TL_GENERAL);
                    // Redirect
                    $this->redirect($this->addToUrl("id=" . $objDBModel->getID() . "&amp;act=edit"));
                }
            }
            else if (isset($_POST["saveNclose"]))
            {
                // process input and update changed properties.
                if (($objModell = $this->doSave($this->objDC)) !== false)
                {
                    setcookie('BE_PAGE_OFFSET', 0, 0, '/');

                    $_SESSION['TL_INFO']    = '';
                    $_SESSION['TL_ERROR']   = '';
                    $_SESSION['TL_CONFIRM'] = '';

                    // Callback
                    $this->objDC->getCallbackClass()->oncreateCallback($objDBModel->getID(), $objDBModel->getPropertiesAsArray());
                    // Log
                    $this->log('A new entry in table "' . $this->objDC->getTable() . '" has been created (ID: ' . $objModell->getID() . ')', 'DC_General - Controller - create()', TL_GENERAL);
                    // Redirect
                    $this->redirect($this->getReferer());
                }
            }
        }
    }

    public function delete()
    {
        // Load current values
        $this->loadCurrentDCA();
        $this->loadCurrentDataProvider();

        // Init some vars
        $intRecordID = $this->objDC->getId();

        // Check if we have a id
        if (strlen($intRecordID) == 0)
        {
            $this->reload();
        }

        // Check if is it allowed to delete a record
        if ($this->arrDCA['config']['notDeletable'])
        {
            $this->log('Table "' . $this->objDC->getTable() . '" is not deletable', 'DC_General - Controller - delete()', TL_ERROR);
            $this->redirect('contao/main.php?act=error');
        }

        // Callback
        $this->objDC->setCurrentModel($this->objDataProvider->fetch($this->objDataProvider->getEmptyConfig()->setId($intRecordID)));
        $this->objDC->getCallbackClass()->ondeleteCallback();

        $arrDelIDs = array();

        // Delete record
        switch ($this->arrDCA['list']['sorting']['mode'])
        {
            case 1:
            case 2:
            case 3:
            case 4:
                $arrDelIDs = array();
                $arrDelIDs[] = $intRecordID;
                break;

            case 5:
                $arrJoinCondition = $this->objDC->getJoinConditions('self');

                $arrDelIDs = array();
                $arrDelIDs[] = $intRecordID;

                // Get all child entries
                for ($i = 0; $i < count($arrDelIDs); $i++)
                {
                    // Get the current model
                    $objTempModel = $this->objDC->getDataProvider()->fetch($this->objDC->getDataProvider()->getEmptyConfig()->setId($arrDelIDs[$i]));

                    // Build filter
                    $strFilter      = $arrJoinCondition[0]['dstField'] . $arrJoinCondition[0]['operation'] . $objTempModel->getProperty($arrJoinCondition[0]['srcField']);
                    $objChildConfig = $this->objDC->getDataProvider()->getEmptyConfig();
                    $objChildConfig->setFilter(array($strFilter));

                    // Get child collection
                    $objChildCollection = $this->objDC->getDataProvider()->fetchAll($objChildConfig);

                    foreach ($objChildCollection as $value)
                    {
                        $arrDelIDs[] = $value->getID();
                    }
                }
                break;
        }

        // Delete all entries
        foreach ($arrDelIDs as $value)
        {
            $this->objDC->getDataProvider()->delete($value);

            // Add a log entry unless we are deleting from tl_log itself
            if ($this->objDC->getTable() != 'tl_log')
            {
                $this->log('DELETE FROM ' . $this->objDC->getTable() . ' WHERE id=' . $value, 'DC_General - Controller - delete()', TL_GENERAL);
            }
        }

        $this->redirect($this->getReferer());
    }

    public function edit()
    {
        // Load some vars
        $this->loadCurrentDCA();
        $this->loadCurrentDataProvider();

        // Check 
        $this->checkIsWritable();
        $this->checkLanguage($this->objDC);

        // Load an older Version
        if (strlen($this->Input->post("version")) != 0 && $this->objDC->isVersionSubmit())
        {
            $this->loadVersion($this->objDC->getId(), $this->Input->post("version"));
        }

        // Load fields and co
        $this->objDC->loadEditableFields();
        $this->objDC->setWidgetID($this->objDC->getId());

        // Check if we have fields
        if (!$this->objDC->hasEditableFields())
        {
            $this->redirect($this->getReferer());
        }

        // Load something
        $this->objDC->preloadTinyMce();

        // Set buttons
        $this->objDC->addButton("save");
        $this->objDC->addButton("saveNclose");

        // Load record from data provider
        $objDBModel = $this->objDataProvider->fetch($this->objDataProvider->getEmptyConfig()->setId($this->objDC->getId()));
        if ($objDBModel == null)
        {
            $objDBModel = $this->objDataProvider->getEmptyModel();
        }

        $this->objDC->setCurrentModel($objDBModel);

        // Check if we have a auto submit
        if ($this->objDC->isAutoSubmitted())
        {
            // process input and update changed properties.
            foreach (array_keys($this->objDC->getFieldList()) as $key)
            {
                $varNewValue = $this->objDC->processInput($key);
                if ($objDBModel->getProperty($key) != $varNewValue)
                {
                    $objDBModel->setProperty($key, $varNewValue);
                }
            }

            $this->objDC->setCurrentModel($objDBModel);
        }

        // Check submit
        if ($this->objDC->isSubmitted() == true)
        {
            if (isset($_POST["save"]))
            {
                // process input and update changed properties.
                if ($this->doSave($this->objDC) !== false)
                {
                    $this->reload();
                }
            }
            else if (isset($_POST["saveNclose"]))
            {
                // process input and update changed properties.
                if ($this->doSave($this->objDC) !== false)
                {
                    setcookie('BE_PAGE_OFFSET', 0, 0, '/');

                    $_SESSION['TL_INFO']    = '';
                    $_SESSION['TL_ERROR']   = '';
                    $_SESSION['TL_CONFIRM'] = '';

                    $this->redirect($this->getReferer());
                }
            }

            // Maybe Callbacks ?
        }
    }

    /**
     * Show informations about one entry
     */
    public function show()
    {
        // Load check multi language
        $this->loadCurrentDCA();
        $this->loadCurrentDataProvider();

        // Check
        $this->checkLanguage($this->objDC);

        // Load record from data provider
        $objDBModel = $this->objDataProvider->fetch($this->objDataProvider->getEmptyConfig()->setId($this->objDC->getId()));

        if ($objDBModel == null)
        {
            $this->log('Could not find ID ' . $this->objDC->getId() . ' in Table ' . $this->objDC->getTable() . '.', 'DC_General show()', TL_ERROR);
            $this->redirect('contao/main.php?act=error');
        }

        $this->objDC->setCurrentModel($objDBModel);
    }

    /**
     * Show all entries from a table
     *
     * @return void | String if error
     */
    public function showAll()
    {
        // Load current values
        $this->loadCurrentDCA();
        $this->loadCurrentModel();

        // Check clipboard
        $this->checkClipboard();

        $this->objDC->setButtonId('tl_buttons');

        // Custom filter
        if (is_array($this->arrDCA['list']['sorting']['filter']) && !empty($this->arrDCA['list']['sorting']['filter']))
        {
            foreach ($this->arrDCA['list']['sorting']['filter'] as $filter)
            {
                $this->objDC->setFilter(array($filter[0] . " = '" . $filter[1] . "'"));
            }
        }

        // Get the IDs of all root records (list view or parent view)
        if (is_array($this->arrDCA['list']['sorting']['root']))
        {
            $this->objDC->setRootIds(array_unique($this->arrDCA['list']['sorting']['root']));
        }

        if ($this->Input->get('table') && !is_null($this->objDC->getParentTable()) && $this->objDC->getDataProvider()->fieldExists('pid'))
        {
            $this->objDC->setFilter(array("pid = '" . CURRENT_ID . "'"));
        }

        $this->panel($this->objDC);

        // Switch mode
        switch ($this->arrDCA['list']['sorting']['mode'])
        {
            case 1:
            case 2:
            case 3:
                $this->listView();
                break;

            case 4:
                $this->parentView();
                break;

            case 5:
                $this->treeViewM5();
                break;

            default:
                return $this->notImplMsg;
                break;
        }
    }

    /* /////////////////////////////////////////////////////////////////////////
     * -------------------------------------------------------------------------
     * AJAX
     * -------------------------------------------------------------------------
     * ////////////////////////////////////////////////////////////////////// */

    public function ajaxTreeView($intID, $intLevel)
    {
        // Load current informations
        $this->loadCurrentDCA();
        $this->loadCurrentDataProvider();

        // Load some infromations from DCA
        $arrNeededFields = $this->arrDCA['list']['label']['fields'];
        $arrLablesFields = $this->arrDCA['list']['label']['fields'];
        $arrTitlePattern = $this->arrDCA['list']['label']['format'];
        $arrChildFilter  = $this->objDC->getJoinConditions('self');

        $strToggleID = $this->objDC->getTable() . '_tree';

        $arrToggle = $this->Session->get($strToggleID);
        if (!is_array($arrToggle))
        {
            $arrToggle = array();
        }

        $arrToggle[$intID] = 1;

        $this->Session->set($strToggleID, $arrToggle);

        // Init some vars
        $objTableTreeData      = $this->objDataProvider->getEmptyCollection();
        $objRootConfig         = $this->objDataProvider->getEmptyConfig();
        $arrChildFilterPattern = array();

        // Build a filter array for the join conditions
        foreach ($arrChildFilter as $key => $value)
        {
            if ($value['srcField'] != '')
            {
                $arrNeededFields[]                      = trim($value['srcField']);
                $arrChildFilterPattern[$key]['field']   = $value['srcField'];
                $arrChildFilterPattern[$key]['pattern'] = $value['dstField'] . ' ' . $value['operation'] . ' %s';
            }
            else
            {
                $arrChildFilterPattern[$key]['pattern'] = $value['dstField'] . ' ' . $value['operation'];
            }
        }

        // Set fields limit
        $objRootConfig->setFields(array_keys(array_flip($arrNeededFields)));

        $arrJoinConditions = $this->objDC->getJoinConditions('self');
        $arrCurrentFiler   = array();

        foreach ($arrJoinConditions as $key => $value)
        {
            $arrNeededFields[] = trim($value['srcField']);
//            $arrCurrentFiler[]['field'] = $value['srcField'];
            $arrCurrentFiler[] = $value['dstField'] . ' ' . $value['operation'] . $intID;


//            $arrJoinConditions[$key] = $value['field'] . $value['operation'] . $value['value'];
        }

        // Set Filter for root elements
        $objRootConfig->setFilter($arrCurrentFiler);

        // Fetch all root elements
        $objRootCollection = $this->objDataProvider->fetchAll($objRootConfig);

        foreach ($objRootCollection as $objRootModel)
        {
            $objTableTreeData->add($objRootModel);

            // Build full lable
            $arrField = array();
            foreach ($arrLablesFields as $strField)
            {
                $arrField[] = $objRootModel->getProperty($strField);
            }

            $objRootModel->setProperty('dc_gen_tv_title', vsprintf($arrTitlePattern, $arrField));
            $objRootModel->setProperty('dc_gen_tv_level', $intLevel + 1);

            // Callback
            $strLabel = $this->objDC->getCallbackClass()->labelCallback($objRootModel, $objRootModel->getProperty('dc_gen_tv_title'), $arrField);
            if ($strLabel != '')
            {
                $objRootModel->setProperty('dc_gen_tv_title', $strLabel);
            }

            // Get toogle state
            if ($arrToggle[$objRootModel->getID()] == 1)
            {
                $objRootModel->setProperty('dc_gen_tv_open', true);
            }
            else
            {
                $objRootModel->setProperty('dc_gen_tv_open', false);
            }

            // Check if we have children
            if ($this->hasChildren($objRootModel, 'self') == true)
            {
                $objRootModel->setProperty('dc_gen_tv_children', true);
            }
            else
            {
                $objRootModel->setProperty('dc_gen_tv_children', false);
            }

            // If open load all children
            if ($objRootModel->getProperty('dc_gen_tv_children') == true && $objRootModel->getProperty('dc_gen_tv_open') == true)
            {
                $objRootModel->setProperty('dc_gen_children_collection', $this->generateTreeViews($arrTitlePattern, $arrNeededFields, $arrLablesFields, $arrChildFilterPattern, $intLevel + 2, $objRootModel, $arrToggle));
            }
        }

        $this->objDC->setCurrentCollecion($objTableTreeData);
    }

    /**
     * ToDo: Bugy
     *
     * @param DC_General $this->objDC
     * @param type $strMethod
     * @param type $strSelector
     */
    public function generateAjaxPalette($strMethod, $strSelector)
    {
        // Check if table is editable
        if (!$this->objDC->isEditable())
        {
            $this->log('Table ' . $this->objDC->getTable() . ' is not editable', 'DC_General edit()', TL_ERROR);
            $this->redirect('contao/main.php?act=error');
        }

        // Load fields and co
        $this->objDC->loadEditableFields();
        $this->objDC->setWidgetID($this->objDC->getId());

        // Check if we have fields
        if (!$this->objDC->hasEditableFields())
        {
            $this->redirect($this->getReferer());
        }

        // Load something
        $this->objDC->preloadTinyMce();

        // Load record from data provider
        $objDBModel = $this->objDC->getDataProvider()->fetch($this->objDC->getDataProvider()->getEmptyConfig()->setId($this->objDC->getId()));
        if ($objDBModel == null)
        {
            $objDBModel = $this->objDC->getDataProvider()->getEmptyModel();
        }
        $this->objDC->setCurrentModel($objDBModel);
    }

    /* /////////////////////////////////////////////////////////////////////////
     * -------------------------------------------------------------------------
     * Edit modes
     * -------------------------------------------------------------------------
     * ////////////////////////////////////////////////////////////////////// */

    /**
     * Load an older version
     */
    protected function loadVersion($intID, $mixVersion)
    {
        // Load record from version
        $objVersionModel = $this->objDataProvider->getVersion($intID, $mixVersion);

        // Redirect if there is no record with the given ID
        if ($objVersionModel == null)
        {
            $this->log('Could not load record ID ' . $intID . ' of table "' . $this->objDC->getTable() . '"', 'DC_General - Controller - edit()', TL_ERROR);
            $this->redirect('contao/main.php?act=error');
        }

        $this->objDataProvider->save($objVersionModel);
        $this->objDataProvider->setVersionActive($intID, $mixVersion);

        // Callback onrestoreCallback
        $arrData       = $objVersionModel->getPropertiesAsArray();
        $arrData["id"] = $objVersionModel->getID();

        $this->objDC->getCallbackClass()->onrestoreCallback($intID, $this->objDC->getTable(), $arrData, $mixVersion);

        $this->log(sprintf('Version %s of record ID %s (table %s) has been restored', $this->Input->post('version'), $this->objDC->getId(), $this->objDC->getTable()), 'DC_General - Controller - edit()', TL_GENERAL);

        // Reload page with new recored
        $this->reload();
    }

    /**
     * Perform low level saving of the current model in a DC.
     * NOTE: the model will get populated with the new values within this function.
     * Therefore the current submitted data will be stored within the model but only on
     * success also be saved into the DB.
     *
     * @param DC_General $this->objDC the DC that adapts the save operation.
     *
     * @return bool|InterfaceGeneralModel Model if the save operation was successful, false otherwise.
     */
    protected function doSave()
    {
        $objDBModel = $this->objDC->getCurrentModel();
        $arrDCA     = $this->objDC->getDCA();

        // Check if table is closed
        if ($arrDCA['config']['closed'] == true)
        {
            // TODO show alarm message
            $this->redirect($this->getReferer());
        }

        // process input and update changed properties.
        foreach (array_keys($this->objDC->getFieldList()) as $key)
        {
            $varNewValue = $this->objDC->processInput($key);

            if ($objDBModel->getProperty($key) != $varNewValue)
            {
                $objDBModel->setProperty($key, $varNewValue);
            }
        }

        // if we may not store the value, we keep the changes
        // in the current model and return (DO NOT SAVE!).
        if ($this->objDC->isNoReload() == true)
        {
            return false;
        }

        // Callback
        $this->objDC->getCallbackClass()->onsubmitCallback();

        // Refresh timestamp
        if ($this->objDC->getDataProvider()->fieldExists("tstamp") == true)
        {
            $objDBModel->setProperty("tstamp", time());
        }

        // everything went ok, now save the new record
        $this->objDC->getDataProvider()->save($objDBModel);

        // Check if versioning is enabled
        if (isset($arrDCA['config']['enableVersioning']) && $arrDCA['config']['enableVersioning'] == true)
        {
            // Compare version and current record
            $mixCurrentVersion = $this->objDC->getDataProvider()->getActiveVersion($objDBModel->getID());
            if ($mixCurrentVersion != null)
            {
                $mixCurrentVersion = $this->objDC->getDataProvider()->getVersion($objDBModel->getID(), $mixCurrentVersion);

                if ($this->objDC->getDataProvider()->sameModels($objDBModel, $mixCurrentVersion) == false)
                {
                    // TODO: FE|BE switch
                    $this->import('BackendUser', 'User');
                    $this->objDC->getDataProvider()->saveVersion($objDBModel, $this->User->username);
                }
            }
            else
            {
                // TODO: FE|BE switch
                $this->import('BackendUser', 'User');
                $this->objDC->getDataProvider()->saveVersion($objDBModel, $this->User->username);
            }
        }

        // Return the current model
        return $objDBModel;
    }

    /* /////////////////////////////////////////////////////////////////////////
     * -------------------------------------------------------------------------
     * Copy modes
     * -------------------------------------------------------------------------
     * ////////////////////////////////////////////////////////////////////// */

    /**
     * @todo Make it fine
     *
     * @param type $intSrcID
     * @param type $intDstID
     * @param type $intMode
     * @param type $blnChilds
     * @param type $strDstField
     * @param type $strSrcField
     * @param type $strOperation
     */
    protected function insertCopyModel($intIdSource, $intIdTarget, $intMode, $blnChilds, $strFieldId, $strFieldPid, $strOperation)
    {
        // Get dataprovider
        $objDataProvider = $this->objDC->getDataProvider();

        // Load the source model
        $objSrcModel = $objDataProvider->fetch($objDataProvider->getEmptyConfig()->setId($intIdSource));

        // Create a empty model for the copy
        $objCopyModel = $objDataProvider->getEmptyModel();

        // Load all params
        $arrProperties = $objSrcModel->getPropertiesAsArray();

        // Clear some fields, see dca
        foreach ($arrProperties as $key => $value)
        {
            // If the field is not known, remove it
            if (!key_exists($key, $this->arrDCA['fields']))
            {
                continue;
            }

            // Check doNotCopy
            if ($this->arrDCA['fields'][$key]['eval']['doNotCopy'] == true)
            {
                unset($arrProperties[$key]);
                continue;
            }

            // Check fallback
            if ($this->arrDCA['fields'][$key]['eval']['fallback'] == true)
            {
                $objDataProvider->resetFallback($key);
            }

            // Check unique
            if ($this->arrDCA['fields'][$key]['eval']['unique'] == true && $objDataProvider->isUniqueValue($key, $value))
            {
                throw new Exception(vsprintf($GLOBALS['TL_LANG']['ERR']['unique'], $key));
            }
        }

        // Add the properties to the empty model
        $objCopyModel->setPropertiesAsArray($arrProperties);

        // Check mode insert into/after
        switch ($intMode)
        {
            //Insert After => Get the parent from he target id
            case 1:
                $this->setParent($objCopyModel, $this->getParent('self', null, $intIdTarget), 'self');
                break;

            // Insert Into => use the pid
            case 2:
                if ($this->isRootEntry('self', $intIdTarget))
                {
                    $this->setRoot($objCopyModel, 'self');
                }
                else
                {
                    $objParentConfig = $this->objDC->getDataProvider()->getEmptyConfig();
                    $objParentConfig->setId($intIdTarget);

                    $objParentModel = $this->objDC->getDataProvider()->fetch($objParentConfig);

                    $this->setParent($objCopyModel, $objParentModel, 'self');
                }
                break;

            default:
                $this->log('Unknown create mode for copy in ' . $this->objDC->getTable(), 'DC_General - Controller - copy()', TL_ERROR);
                $this->redirect('contao/main.php?act=error');
                break;
        }

        $objDataProvider->save($objCopyModel);

        $this->arrInsertIDs[$objCopyModel->getID()] = true;

        if ($blnChilds == true)
        {
            $strFilter      = $strFieldPid . $strOperation . $objSrcModel->getProperty($strFieldId);
            $objChildConfig = $objDataProvider->getEmptyConfig()->setFilter(array($strFilter));
            $objChildCollection = $objDataProvider->fetchAll($objChildConfig);

            foreach ($objChildCollection as $key => $value)
            {
                if (key_exists($value->getID(), $this->arrInsertIDs))
                {
                    continue;
                }

                $this->insertCopyModel($value->getID(), $objCopyModel->getID(), 2, $blnChilds, $strFieldId, $strFieldPid, $strOperation);
            }
        }
    }

    /* /////////////////////////////////////////////////////////////////////////
     * -------------------------------------------------------------------------
     * showAll Modis
     * -------------------------------------------------------------------------
     * ////////////////////////////////////////////////////////////////////// */

    protected function treeViewM5()
    {
        // Load some infromations from DCA
        $arrNeededFields = $this->arrDCA['list']['label']['fields'];
        $arrLablesFields = $this->arrDCA['list']['label']['fields'];
        $arrTitlePattern = $this->arrDCA['list']['label']['format'];
        $arrRootEntries  = $this->objDC->getRootConditions('self');
        $arrChildFilter  = $this->objDC->getJoinConditions('self');

        foreach ($arrRootEntries as $key => $value)
        {
            $arrNeededFields[]    = $value['field'];
            $arrRootEntries[$key] = $value['field'] . $value['operation'] . $value['value'];
        }

        $strToggleID = $this->objDC->getTable() . '_tree';

        $arrToggle = $this->Session->get($strToggleID);
        if (!is_array($arrToggle))
        {
            $arrToggle = array();
        }

        // Check if the open/close all is active
        if ($this->blnShowAllEntries == true)
        {
            if (key_exists('all', $arrToggle))
            {
                $arrToggle = array();
            }
            else
            {
                $arrToggle = array();
                $arrToggle['all'] = 1;
            }

            // Save in session and redirect
            $this->Session->set($strToggleID, $arrToggle);
            $this->redirectHome();
        }

        // Init some vars
        $objTableTreeData      = $this->objDC->getDataProvider()->getEmptyCollection();
        $objRootConfig         = $this->objDC->getDataProvider()->getEmptyConfig();
        $arrChildFilterPattern = array();

        // Build a filter array for the join conditions
        foreach ($arrChildFilter as $key => $value)
        {
            if ($value['dstField'] != '')
            {
                $arrNeededFields[]                      = trim($value['srcField']);
                $arrChildFilterPattern[$key]['field']   = $value['srcField'];
                $arrChildFilterPattern[$key]['pattern'] = $value['dstField'] . ' ' . $value['operation'] . ' %s';
            }
            else
            {
                $arrChildFilterPattern[$key]['pattern'] = $value['srcField'] . ' ' . $value['operation'];
            }
        }

        // Set fields limit
        $objRootConfig->setFields(array_keys(array_flip($arrNeededFields)));

        // Set Filter for root elements
        $objRootConfig->setFilter($arrRootEntries);

        // Fetch all root elements
        $objRootCollection = $this->objDC->getDataProvider()->fetchAll($objRootConfig);

        foreach ($objRootCollection as $objRootModel)
        {
            $objTableTreeData->add($objRootModel);

            // Build full lable
            $arrField = array();
            foreach ($arrLablesFields as $strField)
            {
                $arrField[] = $objRootModel->getProperty($strField);
            }

            $objRootModel->setProperty('dc_gen_tv_title', vsprintf($arrTitlePattern, $arrField));
            $objRootModel->setProperty('dc_gen_tv_level', 0);

            // Callback
            $strLabel = $this->objDC->getCallbackClass()->labelCallback($objRootModel, $objRootModel->getProperty('dc_gen_tv_title'), $arrField);
            if ($strLabel != '')
            {
                $objRootModel->setProperty('dc_gen_tv_title', $strLabel);
            }

            // Get toogle state
            if ($arrToggle['all'] == 1 && !(key_exists($objRootModel->getID(), $arrToggle) && $arrToggle[$objRootModel->getID()] == 0))
            {
                $objRootModel->setProperty('dc_gen_tv_open', true);
            }
            // Get toogle state
            else if (key_exists($objRootModel->getID(), $arrToggle) && $arrToggle[$objRootModel->getID()] == 1)
            {
                $objRootModel->setProperty('dc_gen_tv_open', true);
            }
            else
            {
                $objRootModel->setProperty('dc_gen_tv_open', false);
            }

            // Check if we have children
            if ($this->hasChildren($objRootModel, 'self') == true)
            {
                $objRootModel->setProperty('dc_gen_tv_children', true);
            }
            else
            {
                $objRootModel->setProperty('dc_gen_tv_children', false);
            }

            // If open load all children
            if ($objRootModel->getProperty('dc_gen_tv_children') == true && $objRootModel->getProperty('dc_gen_tv_open') == true)
            {
                $objRootModel->setProperty('dc_gen_children_collection', $this->generateTreeViews($arrTitlePattern, $arrNeededFields, $arrLablesFields, $arrChildFilterPattern, 1, $objRootModel, $arrToggle));
            }
        }

        $this->objDC->setCurrentCollecion($objTableTreeData);
    }

    protected function generateTreeViews($arrTitlePattern, $arrNeededFields, $arrLablesFields, $arrFilterPattern, $intLevel, $objParentModel, $arrToggle)
    {
        $objCollection = $this->objDC->getDataProvider()->getEmptyCollection();
        $arrFilter     = array();

        // Build filter Settings
        foreach ($arrFilterPattern as $valueFilter)
        {
            if (isset($valueFilter['field']) && $valueFilter['field'] != '')
            {
                $arrFilter[] = vsprintf($valueFilter['pattern'], $objParentModel->getProperty($valueFilter['field']));
            }
            else
            {
                $arrFilter[] = $valueFilter['pattern'];
            }
        }

        // Create a new Config
        $objChildConfig = $this->objDC->getDataProvider()->getEmptyConfig();
        $objChildConfig->setFilter($arrFilter);
        $objChildConfig->setFields(array_keys(array_flip($arrNeededFields)));

        // Fetch all children
        $objChildCollection = $this->objDC->getDataProvider()->fetchAll($objChildConfig);

        // Run each entry
        foreach ($objChildCollection as $objChildModel)
        {
            $objCollection->add($objChildModel);

            // Build full lable
            $arrField = array();
            foreach ($arrLablesFields as $strField)
            {
                $arrField[] = $objChildModel->getProperty($strField);
            }

            $objChildModel->setProperty('dc_gen_tv_title', vsprintf($arrTitlePattern, $arrField));
            $objChildModel->setProperty('dc_gen_tv_level', $intLevel);

            // Callback
            $strLabel = $this->objDC->getCallbackClass()->labelCallback($objChildModel, $objChildModel->getProperty('dc_gen_tv_title'), $arrField);
            if ($strLabel != '')
            {
                $objChildModel->setProperty('dc_gen_tv_title', $strLabel);
            }

            // Get toogle state
            if ($arrToggle['all'] == 1 && !(key_exists($objChildModel->getID(), $arrToggle) && $arrToggle[$objChildModel->getID()] == 0))
            {
                $objChildModel->setProperty('dc_gen_tv_open', true);
            }
            // Get toogle state
            else if (key_exists($objChildModel->getID(), $arrToggle) && $arrToggle[$objChildModel->getID()] == 1)
            {
                $objChildModel->setProperty('dc_gen_tv_open', true);
            }
            else
            {
                $objChildModel->setProperty('dc_gen_tv_open', false);
            }

            // Check if we have children
            if ($this->hasChildren($objChildModel, 'self') == true)
            {
                $objChildModel->setProperty('dc_gen_tv_children', true);
            }
            else
            {
                $objChildModel->setProperty('dc_gen_tv_children', false);
            }

            if ($objChildModel->getProperty('dc_gen_tv_children') == true && $objChildModel->getProperty('dc_gen_tv_open') == true)
            {
                $objChildModel->setProperty('dc_gen_children_collection', $this->generateTreeViews($arrTitlePattern, $arrNeededFields, $arrLablesFields, $arrFilterPattern, $intLevel + 1, $objChildModel, $arrToggle));
            }
        }

        return $objCollection;
    }

    protected function listView()
    {
        $objDataProvider = $this->objDC->getDataProvider();
        $arrCurrentDCA   = $this->arrDCA;

        // Get limits
        $arrLimit = $this->getLimit();

        // Load record from data provider
        $objConfig = $this->objDC->getDataProvider()->getEmptyConfig()
                ->setIdOnly(true)
                ->setStart($arrLimit[0])
                ->setAmount($arrLimit[1])
                ->setFilter($this->getFilter())
                ->setSorting($this->getListViewSorting());

        $objCollection = $objDataProvider->fetchEach($this->objDC->getDataProvider()->getEmptyConfig()->setIds($objDataProvider->fetchAll($objConfig))->setSorting($this->getListViewSorting()));

        // Rename each pid to its label and resort the result (sort by parent table)
        if ($this->arrDCA['list']['sorting']['mode'] == 3)
        {
            $this->objDC->setFirstSorting('pid');
            $showFields = $this->arrDCA['list']['label']['fields'];

            foreach ($objCollection as $objModel)
            {
                $objFieldModel = $this->objDC->getDataProvider('parent')->fetch($this->objDC->getDataProvider()->getEmptyConfig()->setId($objModel->getID()));
                $objModel->setProperty('pid', $objFieldModel->getProperty($showFields[0]));
            }

            $this->arrColSort = array(
                'field'   => 'pid',
                'reverse' => false
            );

            $objCollection->sort(array($this, 'sortCollection'));
        }

        // TODO set global current in DC_General
        /* $this->current[] = $objModelRow->getProperty('id'); */
        $showFields = $arrCurrentDCA['list']['label']['fields'];

        if (is_array($showFields))
        {
            // Label
            foreach ($showFields as $v)
            {
                // Decrypt the value
                if ($this->arrDCA['fields'][$v]['eval']['encrypt'])
                {
                    $objModelRow->setProperty($v, deserialize($objModelRow->getProperty($v)));

                    $this->import('Encryption');
                    $objModelRow->setProperty($v, $this->Encryption->decrypt($objModelRow->getProperty($v)));
                }

                if (strpos($v, ':') !== false)
                {
                    list($strKey, $strTable) = explode(':', $v);
                    list($strTable, $strField) = explode('.', $strTable);


                    $objModel = $this->objDC->getDataProvider($strTable)->fetch(
                            $this->objDC->getDataProvider()->getEmptyConfig()
                                    ->setId($row[$strKey])
                                    ->setFields(array($strField))
                    );

                    $objModelRow->setProperty('%args%', (($objModel->hasProperties()) ? $objModel->getProperty($strField) : ''));
                }
            }
        }

        $this->objDC->setCurrentCollecion($objCollection);
    }

    /**
     * Show header of the parent table and list all records of the current table
     * @return string
     */
    protected function parentView()
    {
        // Load language file and data container array of the parent table
        $this->loadLanguageFile($this->objDC->getParentTable());
        $this->loadDataContainer($this->objDC->getParentTable());

        $objParentDC    = new DC_General($this->objDC->getParentTable());
        $this->parentDc = $objParentDC->getDCA();

        // Get limits
        $arrLimit = $this->getLimit();

        // Load record from data provider
        $objConfig = $this->objDC->getDataProvider()->getEmptyConfig()
                ->setStart($arrLimit[0])
                ->setAmount($arrLimit[1])
                ->setFilter($this->getFilter())
                ->setSorting($this->getParentViewSorting());

        if ($this->foreignKey)
        {
            $objConfig->setFields($this->arrFields);
        }

        $this->objDC->setCurrentCollecion($this->objDC->getDataProvider()->fetchAll($objConfig));

        if (!is_null($this->objDC->getParentTable()))
        {

            // Load record from parent data provider
            $objCollection = $this->objDC->getDataProvider('parent')->getEmptyCollection();
            $objCollection->add(
                    $this->objDC->getDataProvider('parent')->fetch(
                            $this->objDC->getDataProvider()->getEmptyConfig()
                                    ->setId(CURRENT_ID)
                    )
            );

            $this->objDC->setCurrentParentCollection($objCollection);

            // List all records of the child table
            if (!$this->Input->get('act') || $this->Input->get('act') == 'paste' || $this->Input->get('act') == 'select')
            {
                $headerFields = $this->arrDCA['list']['sorting']['headerFields'];

                foreach ($headerFields as $v)
                {
                    $_v = deserialize($this->objDC->getCurrentParentCollection()->get(0)->getProperty($v));

                    if ($v == 'tstamp')
                    {
                        $objCollection = $this->objDC->getDataProvider()->fetchAll(
                                $this->objDC->getDataProvider()->getEmptyConfig()
                                        ->setFilter(array("pid = '" . $this->objDC->getCurrentParentCollection()->get(0)->getID() . "'"))
                                        ->setFields(array('MAX(tstamp) AS tstamp'))
                        );

                        $objTStampModel = $objCollection->get(0);

                        if (!$objTStampModel->getProperty('tstamp'))
                        {
                            $objTStampModel->setProperty('tstamp', $this->objDC->getCurrentParentCollection()->get(0)->getProperty($v));
                        }

                        $this->objDC->getCurrentParentCollection()->get(0)->setProperty($v, $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], max($this->objDC->getCurrentParentCollection()->get(0)->getProperty($v), $objTStampModel->getProperty('tstamp'))));
                    }
                    elseif (isset($this->parentDc['fields'][$v]['foreignKey']))
                    {
                        $arrForeignKey = explode('.', $this->parentDc['fields'][$v]['foreignKey'], 2);

                        $objLabelModel = $this->objDC->getDataProvider($arrForeignKey[0])->fetch(
                                $this->objDC->getDataProvider()->getEmptyConfig()
                                        ->setId($_v)
                                        ->setFields(array($arrForeignKey[1] . " AS value"))
                        );

                        if ($objLabelModel->hasProperties())
                        {
                            $this->objDC->getCurrentParentCollection()->get(0)->setProperty($v, $objLabelModel->getProperty('value'));
                        }
                    }
                }
            }
        }
    }

    /* /////////////////////////////////////////////////////////////////////////
     * -------------------------------------------------------------------------
     * Panels
     * -------------------------------------------------------------------------
     * ////////////////////////////////////////////////////////////////////// */

    /**
     * Build the sort panel and write it to DC_General
     */
    protected function panel()
    {
        $arrPanelView = array();

        $filter = $this->filterMenu();
        $search = $this->searchMenu();
        $limit  = $this->limitMenu();
        $sort   = $this->sortMenu();

        if (!strlen($this->arrDCA['list']['sorting']['panelLayout']) || !is_array($filter) && !is_array($search) && !is_array($limit) && !is_array($sort))
        {
            return;
        }

        if ($this->Input->post('FORM_SUBMIT') == 'tl_filters')
        {
            $this->reload();
        }

        $panelLayout = $this->arrDCA['list']['sorting']['panelLayout'];
        $arrPanels   = trimsplit(';', $panelLayout);

        for ($i = 0; $i < count($arrPanels); $i++)
        {
            $arrSubPanels = trimsplit(',', $arrPanels[$i]);

            foreach ($arrSubPanels as $strSubPanel)
            {
                if (is_array($$strSubPanel) && count($$strSubPanel) > 0)
                {
                    $arrPanelView[$i][$strSubPanel] = $$strSubPanel;
                }
            }

            if (is_array($arrPanelView[$i]))
            {
                $arrPanelView[$i] = array_reverse($arrPanelView[$i]);
            }
        }

        if (count($arrPanelView) > 0)
        {
            $this->objDC->setPanelView(array_values($arrPanelView));
        }
    }

    /**
     * Generate the filter panel and return it as HTML string
     * @return string
     */
    protected function filterMenu()
    {
        $this->objDC->setButtonId('tl_buttons_a');
        $arrSortingFields = array();
        $arrSession = $this->Session->getData();
        $strFilter  = ($this->arrDCA['list']['sorting']['mode'] == 4) ? $this->objDC->getTable() . '_' . CURRENT_ID : $this->objDC->getTable();

        // Get sorting fields
        foreach ($this->arrDCA['fields'] as $k => $v)
        {
            if ($v['filter'])
            {
                $arrSortingFields[] = $k;
            }
        }

        // Return if there are no sorting fields
        if (empty($arrSortingFields))
        {
            return array();
        }

        // Set filter
        $arrSession = $this->filterMenuSetFilter($arrSortingFields, $arrSession, $strFilter);

        // Add options
        $arrPanelView = $this->filterMenuAddOptions($arrSortingFields, $arrSession, $strFilter);

        return $arrPanelView;
    }

    /**
     * Return a search form that allows to search results using regular expressions
     *
     * @return string
     */
    protected function searchMenu()
    {
        $searchFields = array();
        $session      = $this->objSession->getData();
        $arrPanelView = array();

        // Get search fields
        foreach ($this->arrDCA['fields'] as $k => $v)
        {
            if ($v['search'])
            {
                $searchFields[] = $k;
            }
        }

        // Return if there are no search fields
        if (empty($searchFields))
        {
            return array();
        }

        // Store search value in the current session
        if ($this->Input->post('FORM_SUBMIT') == 'tl_filters')
        {
            $session['search'][$this->objDC->getTable()]['value'] = '';
            $session['search'][$this->objDC->getTable()]['field'] = $this->Input->post('tl_field', true);

            // Make sure the regular expression is valid
            if ($this->Input->postRaw('tl_value') != '')
            {
                try
                {
                    $objConfig = $this->objDC->getDataProvider()->getEmptyConfig()
                            ->setAmount(1)
                            ->setFilter(array($this->Input->post('tl_field', true) . " REGEXP '" . $this->Input->postRaw('tl_value') . "'"))
                            ->setSorting($this->getListViewSorting());

                    $this->objDC->getDataProvider()->fetchAll($objConfig);

                    $session['search'][$this->objDC->getTable()]['value'] = $this->Input->postRaw('tl_value');
                }
                catch (Exception $e)
                {
                    // Do nothing
                }
            }

            $this->objSession->setData($session);
        }

        // Set search value from session
        else if ($session['search'][$this->objDC->getTable()]['value'] != '')
        {
            if (substr($GLOBALS['TL_CONFIG']['dbCollation'], -3) == '_ci')
            {
                $this->objDC->setFilter(array("LOWER(CAST(" . $session['search'][$this->objDC->getTable()]['field'] . " AS CHAR)) REGEXP LOWER('" . $session['search'][$this->objDC->getTable()]['value'] . "')"));
            }
            else
            {
                $this->objDC->setFilter(array("CAST(" . $session['search'][$this->objDC->getTable()]['field'] . " AS CHAR) REGEXP '" . $session['search'][$this->objDC->getTable()]['value'] . "'"));
            }
        }

        $arrOptions = array();

        foreach ($searchFields as $field)
        {
            $mixedOptionsLabel = strlen($this->arrDCA['fields'][$field]['label'][0]) ? $this->arrDCA['fields'][$field]['label'][0] : $GLOBALS['TL_LANG']['MSC'][$field];

            $arrOptions[utf8_romanize($mixedOptionsLabel) . '_' . $field] = array(
                'value'   => specialchars($field),
                'select'  => (($field == $session['search'][$this->objDC->getTable()]['field']) ? ' selected="selected"' : ''),
                'content' => $mixedOptionsLabel
            );
        }

        // Sort by option values
        uksort($arrOptions, 'strcasecmp');
        $arrPanelView['option'] = $arrOptions;

        $active = strlen($session['search'][$this->objDC->getTable()]['value']) ? true : false;

        $arrPanelView['select'] = array(
            'class' => 'tl_select' . ($active ? ' active' : '')
        );

        $arrPanelView['input'] = array(
            'class' => 'tl_text' . (($active) ? ' active' : ''),
            'value' => specialchars($session['search'][$this->objDC->getTable()]['value'])
        );

        return $arrPanelView;
    }

    /**
     * Return a select menu to limit results
     *
     * @param boolean
     *
     * @return string
     */
    protected function limitMenu($blnOptional = false)
    {
        $arrPanelView = array();

        $session = $this->objSession->getData();

        $filter = ($this->arrDCA['list']['sorting']['mode'] == 4) ? $this->objDC->getTable() . '_' . CURRENT_ID : $this->objDC->getTable();

        // Set limit from user input
        if ($this->Input->post('FORM_SUBMIT') == 'tl_filters' || $this->Input->post('FORM_SUBMIT') == 'tl_filters_limit')
        {
            if ($this->Input->post('tl_limit') != 'tl_limit')
            {
                $session['filter'][$filter]['limit'] = $this->Input->post('tl_limit');
            }
            else
            {
                unset($session['filter'][$filter]['limit']);
            }

            $this->objSession->setData($session);

            if ($this->Input->post('FORM_SUBMIT') == 'tl_filters_limit')
            {
                $this->reload();
            }
        }

        // Set limit from table configuration
        else
        {
            if (strlen($session['filter'][$filter]['limit']))
            {
                $this->objDC->setLimit((($session['filter'][$filter]['limit'] == 'all') ? null : $session['filter'][$filter]['limit']));
            }
            else
            {
                $this->objDC->setLimit('0,' . $GLOBALS['TL_CONFIG']['resultsPerPage']);
            }

            $intCount               = $this->objDC->getDataProvider()->getCount($this->objDC->getDataProvider()->getEmptyConfig()->setFilter($this->getFilter()));
            $blnIsMaxResultsPerPage = false;

            // Overall limit
            if ($intCount > $GLOBALS['TL_CONFIG']['maxResultsPerPage'] && (is_null($this->objDC->getLimit()) || preg_replace('/^.*,/i', '', $this->objDC->getLimit()) == $GLOBALS['TL_CONFIG']['maxResultsPerPage']))
            {
                if (is_null($this->objDC->getLimit()))
                {
                    $this->objDC->setLimit('0,' . $GLOBALS['TL_CONFIG']['maxResultsPerPage']);
                }

                $blnIsMaxResultsPerPage                 = true;
                $GLOBALS['TL_CONFIG']['resultsPerPage'] = $GLOBALS['TL_CONFIG']['maxResultsPerPage'];
                $session['filter'][$filter]['limit']    = $GLOBALS['TL_CONFIG']['maxResultsPerPage'];
            }

            // Build options
            if ($intCount > 0)
            {
                $arrPanelView['option'][0] = array();
                $options_total = ceil($intCount / $GLOBALS['TL_CONFIG']['resultsPerPage']);

                // Reset limit if other parameters have decreased the number of results
                if (!is_null($this->objDC->getLimit()) && ($this->objDC->getLimit() == '' || preg_replace('/,.*$/i', '', $this->objDC->getLimit()) > $intCount))
                {
                    $this->objDC->setLimit('0,' . $GLOBALS['TL_CONFIG']['resultsPerPage']);
                }

                // Build options
                for ($i = 0; $i < $options_total; $i++)
                {
                    $this_limit  = ($i * $GLOBALS['TL_CONFIG']['resultsPerPage']) . ',' . $GLOBALS['TL_CONFIG']['resultsPerPage'];
                    $upper_limit = ($i * $GLOBALS['TL_CONFIG']['resultsPerPage'] + $GLOBALS['TL_CONFIG']['resultsPerPage']);

                    if ($upper_limit > $intCount)
                    {
                        $upper_limit = $intCount;
                    }

                    $arrPanelView['option'][] = array(
                        'value'   => $this_limit,
                        'select'  => $this->optionSelected($this->objDC->getLimit(), $this_limit),
                        'content' => ($i * $GLOBALS['TL_CONFIG']['resultsPerPage'] + 1) . ' - ' . $upper_limit
                    );
                }

                if (!$blnIsMaxResultsPerPage)
                {
                    $arrPanelView['option'][] = array(
                        'value'   => 'all',
                        'select'  => $this->optionSelected($this->objDC->getLimit(), null),
                        'content' => $GLOBALS['TL_LANG']['MSC']['filterAll']
                    );
                }
            }

            // Return if there is only one page
            if ($blnOptional && ($intCount < 1 || $options_total < 2))
            {
                return array();
            }

            $arrPanelView['select'] = array(
                'class' => (($session['filter'][$filter]['limit'] != 'all' && $intCount > $GLOBALS['TL_CONFIG']['resultsPerPage']) ? ' active' : '')
            );

            $arrPanelView['option'][0] = array(
                'value'   => 'tl_limit',
                'select'  => '',
                'content' => $GLOBALS['TL_LANG']['MSC']['filterRecords']
            );
        }

        return $arrPanelView;
    }

    /**
     * Return a select menu that allows to sort results by a particular field
     *
     * @return string
     */
    protected function sortMenu()
    {
        $arrPanelView = array();

        if ($this->arrDCA['list']['sorting']['mode'] != 2 && $this->arrDCA['list']['sorting']['mode'] != 4)
        {
            return array();
        }

        $sortingFields = array();

        // Get sorting fields
        foreach ($this->arrDCA['fields'] as $k => $v)
        {
            if ($v['sorting'])
            {
                $sortingFields[] = $k;
            }
        }

        // Return if there are no sorting fields
        if (empty($sortingFields))
        {
            return array();
        }

        $this->objDC->setButtonId('tl_buttons_a');
        $session      = $this->objSession->getData();
        $orderBy      = $this->arrDCA['list']['sorting']['fields'];
        $firstOrderBy = preg_replace('/\s+.*$/i', '', $orderBy[0]);

        // Add PID to order fields
        if ($this->arrDCA['list']['sorting']['mode'] == 3 && $this->objDC->getDataProvider()->fieldExists('pid'))
        {
            array_unshift($orderBy, 'pid');
        }

        // Set sorting from user input
        if ($this->Input->post('FORM_SUBMIT') == 'tl_filters')
        {
            $session['sorting'][$this->objDC->getTable()] = in_array($this->arrDCA['fields'][$this->Input->post('tl_sort')]['flag'], array(2, 4, 6, 8, 10, 12)) ? $this->Input->post('tl_sort') . ' DESC' : $this->Input->post('tl_sort');
            $this->objSession->setData($session);
        }

        // Overwrite the "orderBy" value with the session value
        elseif (strlen($session['sorting'][$this->objDC->getTable()]))
        {
            $overwrite = preg_quote(preg_replace('/\s+.*$/i', '', $session['sorting'][$this->objDC->getTable()]), '/');
            $orderBy   = array_diff($orderBy, preg_grep('/^' . $overwrite . '/i', $orderBy));

            array_unshift($orderBy, $session['sorting'][$this->objDC->getTable()]);

            $this->objDC->setFirstSorting($overwrite);
            $this->objDC->setSorting($orderBy);
        }

        $arrOptions = array();

        foreach ($sortingFields as $field)
        {
            $mixedOptionsLabel = strlen($this->arrDCA['fields'][$field]['label'][0]) ? $this->arrDCA['fields'][$field]['label'][0] : $GLOBALS['TL_LANG']['MSC'][$field];

            if (is_array($mixedOptionsLabel))
            {
                $mixedOptionsLabel = $mixedOptionsLabel[0];
            }

            $arrOptions[$mixedOptionsLabel] = array(
                'value'   => specialchars($field),
                'select'  => ((!strlen($session['sorting'][$this->objDC->getTable()]) && $field == $firstOrderBy || $field == str_replace(' DESC', '', $session['sorting'][$this->objDC->getTable()])) ? ' selected="selected"' : ''),
                'content' => $mixedOptionsLabel
            );
        }

        // Sort by option values
        uksort($arrOptions, 'strcasecmp');
        $arrPanelView['option'] = $arrOptions;

        return $arrPanelView;
    }

    /* /////////////////////////////////////////////////////////////////////////
     * -------------------------------------------------------------------------
     * Helper DataProvider
     * -------------------------------------------------------------------------
     * ////////////////////////////////////////////////////////////////////// */

    /**
     * Check if a entry has some childs
     *
     * @param array $arrFilterPattern
     * @param InterfaceGeneralModel $objParentModel
     *
     * @return boolean True => has children | False => no children
     */
    protected function hasChildren($objParentModel, $strTable)
    {
        $arrFilter = array();
        
        // Build filter Settings
        foreach ($this->objDC->getJoinConditions($strTable) as $valueFilter)
        {            
            if (isset($valueFilter['srcField']) && $valueFilter['srcField'] != '')
            {
                $arrFilter[] = $valueFilter['dstField'] . $valueFilter['operation'] . $objParentModel->getProperty($valueFilter['srcField']);
            }
            else
            {
                $arrFilter[] = $valueFilter['dstField'] . $valueFilter['operation'];
            }
        }

        // Create a new Config
        $objConfig = $this->objDC->getDataProvider()->getEmptyConfig();
        $objConfig->setFilter($arrFilter);

        // Fetch all children
        if ($this->objDC->getDataProvider()->getCount($objConfig) != 0)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    protected function setParent(InterfaceGeneralModel $objChildEntry, InterfaceGeneralModel $objParentEntry, $strTable)
    {
        $arrJoinCondition = $this->objDC->getJoinConditions($strTable);
        $objChildEntry->setProperty($arrJoinCondition[0]['dstField'], $objParentEntry->getProperty($arrJoinCondition[0]['srcField']));
    }

    protected function getParent($strTable, $objCurrentModel = null, $intCurrentID = null)
    {
        // Get the join field
        $arrJoinCondition = $this->objDC->getJoinConditions($strTable);

        // Check if something is set
        if ($objCurrentModel == null && $intCurrentID == null)
        {
            return null;
        }

        // If we have only the id load current model
        if ($objCurrentModel == null)
        {
            $objCurrentConfig = $this->objDC->getDataProvider()->getEmptyConfig();
            $objCurrentConfig->setId($intCurrentID);

            $objCurrentModel = $this->objDC->getDataProvider()->fetch($objCurrentConfig);
        }

        // Build child to parent
        $strFilter = $arrJoinCondition[0]['srcField'] . $arrJoinCondition[0]['operation'] . $objCurrentModel->getProperty($arrJoinCondition[0]['dstField']);

        // Load model
        $objParentConfig = $this->objDC->getDataProvider()->getEmptyConfig();
        $objParentConfig->setFilter(array($strFilter));

        return $this->objDC->getDataProvider()->fetch($objParentConfig);
    }

    protected function isRootEntry($strTable, $mixID)
    {
        // Get the join field
        $arrRootCondition = $this->objDC->getRootConditions($strTable);

        switch ($arrRootCondition[0]['operation'])
        {
            case '=':
                return ($mixID == $arrRootCondition[0]['value']);

            case '<':
                return ($arrRootCondition[0]['value'] < $mixID);

            case '>':
                return ($arrRootCondition[0]['value'] > $mixID);

            case '!=':
                return ($arrRootCondition[0]['value'] != $mixID);
        }

        return false;
    }

    protected function setRoot(InterfaceGeneralModel $objCurrentEntry, $strTable)
    {
        // Get the join field
        $arrRootCondition = $this->objDC->getRootConditions($strTable);
        $objCurrentEntry->setProperty($arrRootCondition[0]['field'], $arrRootCondition[0]['value']);
    }

    /* /////////////////////////////////////////////////////////////////////////
     * -------------------------------------------------------------------------
     * Helper
     * -------------------------------------------------------------------------
     * ////////////////////////////////////////////////////////////////////// */

    /**
     * Set filter from user input and table configuration for filter menu
     *
     * @param array $arrSortingFields
     * @param array $arrSession
     * @param string $strFilter
     * @return array
     */
    protected function filterMenuSetFilter($arrSortingFields, $arrSession, $strFilter)
    {
        // Set filter from user input
        if ($this->Input->post('FORM_SUBMIT') == 'tl_filters')
        {
            foreach ($arrSortingFields as $field)
            {
                if ($this->Input->post($field, true) != 'tl_' . $field)
                {
                    $arrSession['filter'][$strFilter][$field] = $this->Input->post($field, true);
                }
                else
                {
                    unset($arrSession['filter'][$strFilter][$field]);
                }
            }

            $this->Session->setData($arrSession);
        }

        // Set filter from table configuration
        else
        {
            foreach ($arrSortingFields as $field)
            {
                if (isset($arrSession['filter'][$strFilter][$field]))
                {

                    // Sort by day
                    if (in_array($this->arrDCA['fields'][$field]['flag'], array(5, 6)))
                    {
                        if ($arrSession['filter'][$strFilter][$field] == '')
                        {
                            $this->objDC->setFilter(array($field . " = ''"));
                        }
                        else
                        {
                            $objDate = new Date($arrSession['filter'][$strFilter][$field]);
                            $this->objDC->setFilter(array($field . " BETWEEN '" . $objDate->dayBegin . "' AND '" . $objDate->dayEnd . "'"));
                        }
                    }

                    // Sort by month
                    elseif (in_array($this->arrDCA['fields'][$field]['flag'], array(7, 8)))
                    {
                        if ($arrSession['filter'][$strFilter][$field] == '')
                        {
                            $this->objDC->setFilter(array($field . " = ''"));
                        }
                        else
                        {
                            $objDate = new Date($arrSession['filter'][$strFilter][$field]);
                            $this->objDC->setFilter(array($field . " BETWEEN '" . $objDate->monthBegin . "' AND '" . $objDate->monthEnd . "'"));
                        }
                    }

                    // Sort by year
                    elseif (in_array($this->arrDCA['fields'][$field]['flag'], array(9, 10)))
                    {
                        if ($arrSession['filter'][$strFilter][$field] == '')
                        {
                            $this->objDC->setFilter(array($field . " = ''"));
                        }
                        else
                        {
                            $objDate = new Date($arrSession['filter'][$strFilter][$field]);
                            $this->objDC->setFilter(array($field . " BETWEEN '" . $objDate->yearBegin . "' AND '" . $objDate->yearEnd . "'"));
                        }
                    }

                    // Manual filter
                    elseif ($this->arrDCA['fields'][$field]['eval']['multiple'])
                    {
                        // TODO fiond in set
                        // CSV lists (see #2890)
                        /* if (isset($this->dca['fields'][$field]['eval']['csv']))
                          {
                          $this->procedure[] = $this->Database->findInSet('?', $field, true);
                          $this->values[] = $session['filter'][$filter][$field];
                          }
                          else
                          {
                          $this->procedure[] = $field . ' LIKE ?';
                          $this->values[] = '%"' . $session['filter'][$filter][$field] . '"%';
                          } */
                    }

                    // Other sort algorithm
                    else
                    {
                        $this->objDC->setFilter(array($field . " = '" . $arrSession['filter'][$strFilter][$field] . "'"));
                    }
                }
            }
        }

        return $arrSession;
    }

    /**
     * Add sorting options to filter menu
     *
     * @param array $arrSortingFields
     * @param array $arrSession
     * @param string $strFilter
     * @return array
     */
    protected function filterMenuAddOptions($arrSortingFields, $arrSession, $strFilter)
    {
        $arrPanelView = array();

        // Add sorting options
        foreach ($arrSortingFields as $cnt => $field)
        {
            $arrProcedure = array();

            if ($this->arrDCA['list']['sorting']['mode'] == 4)
            {

                $arrProcedure[] = "pid = '" . CURRENT_ID . "'";
            }

            if (!is_null($this->objDC->getRootIds()) && is_array($this->objDC->getRootIds()))
            {
                $arrProcedure[] = "id IN(" . implode(',', array_map('intval', $this->objDC->getRootIds())) . ")";
            }

            $objCollection = $this->objDC->getDataProvider()->fetchAll($this->objDC->getDataProvider()->getEmptyConfig()->setFields(array("DISTINCT(" . $field . ")"))->setFilter($arrProcedure));

            // Begin select menu
            $arrPanelView[$field] = array(
                'select' => array(
                    'name'   => $field,
                    'id'     => $field,
                    'class'  => 'tl_select' . (isset($arrSession['filter'][$strFilter][$field]) ? ' active' : '')
                ),
                'option' => array(
                    array(
                        'value'   => 'tl_' . $field,
                        'content' => (is_array($this->arrDCA['fields'][$field]['label']) ? $this->arrDCA['fields'][$field]['label'][0] : $this->arrDCA['fields'][$field]['label'])
                    ),
                    array(
                        'value'   => 'tl_' . $field,
                        'content' => '---'
                    )
                )
            );

            if ($objCollection->length() > 0)
            {
                $options = array();

                foreach ($objCollection as $intIndex => $objModel)
                {
                    $options[$intIndex] = $objModel->getProperty($field);
                }

                // Sort by day
                if (in_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$field]['flag'], array(5, 6)))
                {
                    $this->arrColSort = array(
                        'field'   => $field,
                        'reverse' => ($this->arrDCA['fields'][$field]['flag'] == 6) ? true : false
                    );

                    $objCollection->sort(array($this, 'sortCollection'));

                    foreach ($objCollection as $intIndex => $objModel)
                    {
                        if ($objModel->getProperty($field) == '')
                        {
                            $options[$objModel->getProperty($field)] = '-';
                        }
                        else
                        {
                            $options[$objModel->getProperty($field)] = $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $objModel->getProperty($field));
                        }

                        unset($options[$intIndex]);
                    }
                }

                // Sort by month
                elseif (in_array($this->arrDCA['fields'][$field]['flag'], array(7, 8)))
                {
                    $this->arrColSort = array(
                        'field'   => $field,
                        'reverse' => ($this->arrDCA['fields'][$field]['flag'] == 8) ? true : false
                    );

                    $objCollection->sort(array($this, 'sortCollection'));

                    foreach ($objCollection as $intIndex => $objModel)
                    {
                        if ($objModel->getProperty($field) == '')
                        {
                            $options[$objModel->getProperty($field)] = '-';
                        }
                        else
                        {
                            $options[$objModel->getProperty($field)] = date('Y-m', $objModel->getProperty($field));
                            $intMonth                                = (date('m', $objModel->getProperty($field)) - 1);

                            if (isset($GLOBALS['TL_LANG']['MONTHS'][$intMonth]))
                            {
                                $options[$objModel->getProperty($field)] = $GLOBALS['TL_LANG']['MONTHS'][$intMonth] . ' ' . date('Y', $objModel->getProperty($field));
                            }
                        }

                        unset($options[$intIndex]);
                    }
                }

                // Sort by year
                elseif (in_array($this->arrDCA['fields'][$field]['flag'], array(9, 10)))
                {
                    $this->arrColSort = array(
                        'field'   => $field,
                        'reverse' => ($this->arrDCA['fields'][$field]['flag'] == 10) ? true : false
                    );

                    $objCollection->sort(array($this, 'sortCollection'));

                    foreach ($objCollection as $intIndex => $objModel)
                    {
                        if ($objModel->getProperty($field) == '')
                        {
                            $options[$objModel->getProperty($field)] = '-';
                        }
                        else
                        {
                            $options[$objModel->getProperty($field)] = date('Y', $objModel->getProperty($field));
                        }

                        unset($options[$intIndex]);
                    }
                }

                // Manual filter
                if ($this->arrDCA['fields'][$field]['eval']['multiple'])
                {
                    $moptions = array();

                    foreach ($objCollection as $objModel)
                    {
                        if (isset($this->arrDCA['fields'][$field]['eval']['csv']))
                        {
                            $doptions = trimsplit($this->arrDCA['fields'][$field]['eval']['csv'], $objModel->getProperty($field));
                        }
                        else
                        {
                            $doptions = deserialize($objModel->getProperty($field));
                        }

                        if (is_array($doptions))
                        {
                            $moptions = array_merge($moptions, $doptions);
                        }
                    }

                    $options = $moptions;
                }

                $options            = array_unique($options);
                $arrOptionsCallback = array();

                // Load options callback
                if (is_array($this->arrDCA['fields'][$field]['options_callback']) && !$this->arrDCA['fields'][$field]['reference'])
                {
                    $arrOptionsCallback = $this->objDC->getCallbackClass()->optionsCallback($field);

                    // Sort options according to the keys of the callback array
                    if (!is_null($arrOptionsCallback))
                    {
                        $options = array_intersect(array_keys($arrOptionsCallback), $options);
                    }
                }

                $arrOptions = array();
                $arrSortOptions = array();
                $blnDate = in_array($this->arrDCA['fields'][$field]['flag'], array(5, 6, 7, 8, 9, 10));

                // Options
                foreach ($options as $kk => $vv)
                {
                    $value = $blnDate ? $kk : $vv;

                    // Replace the ID with the foreign key
                    if (isset($this->arrDCA['fields'][$field]['foreignKey']))
                    {
                        $key = explode('.', $this->arrDCA['fields'][$field]['foreignKey'], 2);

                        $objModel = $this->objDC->getDataProvider($key[0])->fetch(
                                $this->objDC->getDataProvider($key[0])->getEmptyConfig()
                                        ->setId($vv)
                                        ->setFields(array($key[1] . ' AS value'))
                        );

                        if ($objModel->hasProperties())
                        {
                            $vv = $objModel->getProperty('value');
                        }
                    }

                    // Replace boolean checkbox value with "yes" and "no"
                    elseif ($this->arrDCA['fields'][$field]['eval']['isBoolean'] || ($this->arrDCA['fields'][$field]['inputType'] == 'checkbox' && !$this->arrDCA['fields'][$field]['eval']['multiple']))
                    {
                        $vv = ($vv != '') ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
                    }

                    // Options callback
                    elseif (!is_null($arrOptionsCallback))
                    {
                        $vv = $arrOptionsCallback[$vv];
                    }

                    // Get the name of the parent record
                    elseif ($field == 'pid')
                    {
                        // Load language file and data container array of the parent table
                        $this->loadLanguageFile($this->objDC->getParentTable());
                        $this->loadDataContainer($this->objDC->getParentTable());

                        $objParentDC  = new DC_General($this->objDC->getParentTable());
                        $arrParentDca = $objParentDC->getDCA();

                        $showFields = $arrParentDca['list']['label']['fields'];

                        if (!$showFields[0])
                        {
                            $showFields[0] = 'id';
                        }

                        $objModel = $this->objDC->getDataProvider('parent')->fetch(
                                $this->objDC->getDataProvider('parent')->getEmptyConfig()
                                        ->setId($vv)
                                        ->setFields(array($showFields[0]))
                        );

                        if ($objModel->hasProperties())
                        {
                            $vv = $objModel->getProperty($showFields[0]);
                        }
                    }

                    $strOptionsLabel = '';

                    // Use reference array
                    if (isset($this->arrDCA['fields'][$field]['reference']))
                    {
                        $strOptionsLabel = is_array($this->arrDCA['fields'][$field]['reference'][$vv]) ? $this->arrDCA['fields'][$field]['reference'][$vv][0] : $this->arrDCA['fields'][$field]['reference'][$vv];
                    }

                    // Associative array
                    elseif ($this->arrDCA['fields'][$field]['eval']['isAssociative'] || array_is_assoc($this->arrDCA['fields'][$field]['options']))
                    {
                        $strOptionsLabel = $this->arrDCA['fields'][$field]['options'][$vv];
                    }

                    // No empty options allowed
                    if (!strlen($strOptionsLabel))
                    {
                        $strOptionsLabel = strlen($vv) ? $vv : '-';
                    }

                    $arrOptions[utf8_romanize($strOptionsLabel)] = array(
                        'value'   => specialchars($value),
                        'select'  => ((isset($arrSession['filter'][$strFilter][$field]) && $value == $arrSession['filter'][$strFilter][$field]) ? ' selected="selected"' : ''),
                        'content' => $strOptionsLabel
                    );

                    $arrSortOptions[] = utf8_romanize($strOptionsLabel);
                }

                // Sort by option values
                if (!$blnDate)
                {
                    natcasesort($arrSortOptions);

                    if (in_array($this->arrDCA['fields'][$field]['flag'], array(2, 4, 12)))
                    {
                        $arrSortOptions = array_reverse($arrSortOptions, true);
                    }
                }

                foreach ($arrSortOptions as $value)
                {
                    $arrPanelView[$field]['option'][] = $arrOptions[$value];
                }
            }
            // Force a line-break after six elements
            if ((($cnt + 1) % 6) == 0)
            {
                $arrPanelView[] = 'new';
            }
        }

        return $arrPanelView;
    }

    public function sortCollection(InterfaceGeneralModel $a, InterfaceGeneralModel $b)
    {
        if ($a->getProperty($this->arrColSort['field']) == $b->getProperty($this->arrColSort['field']))
        {
            return 0;
        }

        if (!$this->arrColSort['reverse'])
        {
            return ($a->getProperty($this->arrColSort['field']) < $b->getProperty($this->arrColSort['field'])) ? -1 : 1;
        }
        else
        {
            return ($a->getProperty($this->arrColSort['field']) < $b->getProperty($this->arrColSort['field'])) ? 1 : -1;
        }
    }

}

?>