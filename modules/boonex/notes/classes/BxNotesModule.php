<?php defined('BX_DOL') or die('hack attempt');
/**
 * Copyright (c) BoonEx Pty Limited - http://www.boonex.com/
 * CC-BY License - http://creativecommons.org/licenses/by/3.0/
 * 
 * @defgroup    Notes Notes
 * @ingroup     DolphinModules
 *
 * @{
 */

bx_import ('BxDolModule');
bx_import ('BxDolAcl');

/**
 * Notes module
 */
class BxNotesModule extends BxDolModule {

    protected $_iProfileId;

    function __construct(&$aModule) {
        parent::__construct($aModule);
        $this->_iProfileId = bx_get_logged_profile_id();
    }

    // ====== SERVICE METHODS

    public function serviceBrowsePublic () {
        return $this->_serviceBrowse ('public');
    }

    public function serviceBrowseFeatured () {
        return $this->_serviceBrowse ('public');
    }

    public function serviceBrowseMy () {
        return $this->_serviceBrowse ('my');
    }

    public function _serviceBrowse ($sMode, $aParams = false) {

        $sClass = $this->_aModule['class_prefix'] . 'SearchResult';
        bx_import('SearchResult', $this->_aModule);
        $o = new $sClass($sMode, $aParams);
        
        $o->setDisplayEmptyMsg(true);

        if ($o->isError)
            return false;

        if ($s = $o->processing())
            return $s;
        else
            return false;
    }

    public function serviceEntityCreate () {
        bx_import('NoteForms', $this->_aModule);
        $oProfileForms = new BxNotesNoteForms($this);
        return $oProfileForms->addDataForm();
    }

    /**
     * @return edit note form string
     */
    public function serviceEntityEdit ($iContentId = 0) {
        if (!$iContentId)
            $iContentId = bx_process_input(bx_get('id'), BX_DATA_INT);
        if (!$iContentId)
            return false;
        bx_import('NoteForms', $this->_aModule);
        $oProfileForms = new BxNotesNoteForms($this);
        return $oProfileForms->editDataForm((int)$iContentId);
    }

    /**
     * @return delete note form string
     */
    public function serviceEntityDelete ($iContentId = 0) {
        if (!$iContentId)
            $iContentId = bx_process_input(bx_get('id'), BX_DATA_INT);
        if (!$iContentId)
            return false;
        bx_import('NoteForms', $this->_aModule);
        $oProfileForms = new BxNotesNoteForms($this);
        return $oProfileForms->deleteDataForm((int)$iContentId);
    }

    /**
     * A note's text with some additional controls, like menu
     */
    public function serviceEntityTextBlock ($iContentId = 0) {
        if (!$iContentId)
            $iContentId = bx_process_input(bx_get('id'), BX_DATA_INT);
        if (!$iContentId)
            return false;
        bx_import('NoteForms', $this->_aModule);
        $oProfileForms = new BxNotesNoteForms($this);
        return $oProfileForms->viewDataEntry((int)$iContentId);
    }

    public function serviceEntityInfo ($iContentId = 0) {
        if (!$iContentId)
            $iContentId = bx_process_input(bx_get('id'), BX_DATA_INT);
        if (!$iContentId)
            return false;
        bx_import('NoteForms', $this->_aModule);
        $oProfileForms = new BxNotesNoteForms($this);
        return $oProfileForms->viewDataForm((int)$iContentId);
    }

    public function serviceEntitySocialSharing ($iContentId = 0) {
        if (!$iContentId)
            $iContentId = bx_process_input(bx_get('id'), BX_DATA_INT);
        if (!$iContentId)
            return false;
        $aContentInfo = $this->_oDb->getContentInfoById($iContentId);
        if (!$aContentInfo)
            return false;

        bx_import('BxDolPermalinks');
        $sUrl = BxDolPermalinks::getInstance()->permalink('page.php?i=view-note&id=' . $aContentInfo['id']);

        $aCustomParams = false;
        if ($aContentInfo[BxNotesConfig::$FIELD_THUMB]) {
            bx_import('BxDolStorage');
            $oStorage = BxDolStorage::getObjectInstance(BxNotesConfig::$OBJECT_STORAGE);
            if ($oStorage && ($sImgUrl = $oStorage->getFileUrlById($aContentInfo[BxNotesConfig::$FIELD_THUMB]))) {
                $aCustomParams = array (
                    'img_url' => $sImgUrl,
                    'img_url_encoded' => rawurlencode($sImgUrl),
                );
            }
        }

        bx_import('BxTemplSocialSharing');
        return BxTemplSocialSharing::getInstance()->getCode(BX_DOL_URL_ROOT . $sUrl, $aContentInfo['title'], $aCustomParams);
    }

    public function serviceEntityComments ($iContentId = 0) {
        if (!$iContentId)
            $iContentId = bx_process_input(bx_get('id'), BX_DATA_INT);
        if (!$iContentId)
            return false;

		bx_import('BxTemplCmtsView');
		$oCmts = new BxTemplCmtsView(BxNotesConfig::$OBJECT_COMMENTS, $iContentId);
		if(!$oCmts->isEnabled())
			return false;

        return $oCmts->getCommentsBlock();
    }

    public function serviceEntityAuthor ($iContentId = 0) {
        if (!$iContentId)
            $iContentId = bx_process_input(bx_get('id'), BX_DATA_INT);
        if (!$iContentId)
            return false;
        $aContentInfo = $this->_oDb->getContentInfoById($iContentId);
        if (!$aContentInfo)
            return false;
        $oProfile = BxDolProfile::getInstance($aContentInfo[BxNotesConfig::$FIELD_AUTHOR]);
        if (!$oProfile) {
            bx_import('BxDolProfileUndefined');
            $oProfile = BxDolProfileUndefined::getInstance();
        }
        return $this->_oTemplate->entryAuthor ($aContentInfo, $oProfile);
    }

	public function serviceGetTimelineData()
    {
        return array(
            'handlers' => array(
                array('type' => 'insert', 'alert_unit' => $this->_aModule['name'], 'alert_action' => 'added', 'module_name' => $this->_aModule['name'], 'module_method' => 'get_timeline_post', 'module_class' => 'Module',  'groupable' => 0, 'group_by' => ''),
                array('type' => 'update', 'alert_unit' => $this->_aModule['name'], 'alert_action' => 'edited'),
                array('type' => 'delete', 'alert_unit' => $this->_aModule['name'], 'alert_action' => 'deleted')
            ),
            'alerts' => array(
                array('unit' => $this->_aModule['name'], 'action' => 'added'),
                array('unit' => $this->_aModule['name'], 'action' => 'edited'),
                array('unit' => $this->_aModule['name'], 'action' => 'deleted'),
            )
        );
    }

    public function serviceGetTimelinePost($aEvent)
    {
    	$aContentInfo = $this->_oDb->getContentInfoById($aEvent['object_id']);
    	if(empty($aContentInfo) || !is_array($aContentInfo))
    		return '';

    	bx_import('BxDolPermalinks');
		$sUrl = BX_DOL_URL_ROOT . BxDolPermalinks::getInstance()->permalink('page.php?i=view-note&id=' . $aContentInfo[BxNotesConfig::$FIELD_ID]);

		//--- Image(s)
	    $sImage = '';
        if($aContentInfo[BxNotesConfig::$FIELD_THUMB]) {
        	bx_import('BxDolStorage');
			$oStorage = BxDolStorage::getObjectInstance(BxNotesConfig::$OBJECT_STORAGE);
            if($oStorage)
				$sImage = $oStorage->getFileUrlById($aContentInfo[BxNotesConfig::$FIELD_THUMB]);
        }

        //--- Comments
        bx_import('BxDolCmts');
        $oCmts = BxDolCmts::getObjectInstance(BxNotesConfig::$OBJECT_COMMENTS, $aEvent['object_id']);

        $aComments = array(); 
		if($oCmts->isEnabled())
			$aComments = array(
				'system' => BxNotesConfig::$OBJECT_COMMENTS,
				'object_id' => $aContentInfo[BxNotesConfig::$FIELD_ID],
				'count' => $aContentInfo['comments']
			);

    	return array(
    		'owner_id' => $aContentInfo[BxNotesConfig::$FIELD_AUTHOR],
    		'content_type' => !empty($sImage) ? 'photo' : 'text', //how to parse content, 'text' by default.
    		'content' => array(
    			'url' => $sUrl,
    			'title' => $aContentInfo[BxNotesConfig::$FIELD_TITLE],
    			'text' => $aContentInfo[BxNotesConfig::$FIELD_TEXT],
    			'images' => array(
    				array(
    					'url' => $sUrl,
    					'src' => $sImage
    				)
    			)
    		), //a string to display or array to parse default template before displaying.
    		'comments' => $aComments,
    		'title' => '', //may be empty.
    		'description' => '' //may be empty. 
    	);
    }

    // ====== ACTION METHODS

    function actionBrowse ($sMode = '') {

        $sMode = bx_process_input($sMode);

/*
        if ('user' == $sMode || 'my' == $sMode) {
            $aProfile = getProfileInfo ($this->_iProfileId);
            if (0 == strcasecmp($sValue, $aProfile['NickName']) || 'my' == $sMode) {
                $this->_browseMy ($aProfile);
                return;
            }
        }

        if ('tag' == $sMode || 'category' == $sMode)
            $sValue = uri2title($sValue);
*/

        if (CHECK_ACTION_RESULT_ALLOWED !== $this->isAllowedBrowse()) {
            $this->_oTemplate->displayAccessDenied ();
            return;
        }

        bx_import ('SearchResult', $this->_aModule);
        $sClass = $this->_aModule['class_prefix'] . 'SearchResult';
        $o = new $sClass($sMode);

        if ($o->isError) {
            $this->_oTemplate->displayPageNotFound ();
            return;
        }

        if (bx_get('rss')) {
            echo $o->rss();
            exit;
        }

        if (!($s = $o->processing())) {
            $this->_oTemplate->displayNoData ();
            return;
        }

        // TODO: remake to use "pages"

        $this->_oTemplate->addCss ('main.css'); 

        $oTemplate = BxDolTemplate::getInstance();
        $oTemplate->setPageHeader ($o->aCurrent['title']);
        $oTemplate->setPageNameIndex (BX_PAGE_DEFAULT);
        $oTemplate->setPageContent ('page_main_code', $s);
        $oTemplate->getPageCode();
    }

    // ====== PERMISSION METHODS

    function _checkModeratorAccess ($isPerformAction = false) {
        // check moderator ACnoteL
        $aCheck = checkActionModule($this->_iProfileId, 'edit any note', $this->getName(), $isPerformAction); 
        return $aCheck[CHECK_ACTION_RESULT] === CHECK_ACTION_RESULT_ALLOWED;
    }

    /**
     * @return CHECK_ACTION_RESULT_ALLOWED if access is granted or error message if access is forbidden. So make sure to make "true === " checking.
     */
    function isAllowedView ($aDataEntry, $isPerformAction = false) {

        // moderator and owner always have access
        if ($aDataEntry[BxNotesConfig::$FIELD_AUTHOR] == $this->_iProfileId || $this->_checkModeratorAccess($isPerformAction))
            return CHECK_ACTION_RESULT_ALLOWED;

        // check ACL
        $aCheck = checkActionModule($this->_iProfileId, 'view note', $this->getName(), $isPerformAction);
        if ($aCheck[CHECK_ACTION_RESULT] !== CHECK_ACTION_RESULT_ALLOWED)
            return $aCheck[CHECK_ACTION_MESSAGE];

        // check privacy 
    	bx_import('BxDolPrivacy');
    	$oPrivacy = BxDolPrivacy::getObjectInstance(BxNotesConfig::$OBJECT_PRIVACY_VIEW);
		if ($oPrivacy && !$oPrivacy->check($aDataEntry[BxNotesConfig::$FIELD_ID]))
            return _t('_sys_access_denied_to_private_content');

        return CHECK_ACTION_RESULT_ALLOWED;
    }

    /**
     * @return CHECK_ACTION_RESULT_ALLOWED if access is granted or error message if access is forbidden. So make sure to make "true === " checking.
     */
    function isAllowedBrowse () {
        return CHECK_ACTION_RESULT_ALLOWED;
    }

    /**
     * @return CHECK_ACTION_RESULT_ALLOWED if access is granted or error message if access is forbidden. 
     */
    function isAllowedAdd ($isPerformAction = false) {
        // check ACL
        $aCheck = checkActionModule($this->_iProfileId, 'create note', $this->getName(), $isPerformAction);
        if ($aCheck[CHECK_ACTION_RESULT] !== CHECK_ACTION_RESULT_ALLOWED)
            return $aCheck[CHECK_ACTION_MESSAGE];
        return CHECK_ACTION_RESULT_ALLOWED;
    }

    /**
     * @return CHECK_ACTION_RESULT_ALLOWED if access is granted or error message if access is forbidden. 
     */
    function isAllowedEdit ($aDataEntry, $isPerformAction = false) {
        // moderator and owner always have access
        if ($aDataEntry[BxNotesConfig::$FIELD_AUTHOR] == $this->_iProfileId || $this->_checkModeratorAccess($isPerformAction))
            return CHECK_ACTION_RESULT_ALLOWED;
        return _t('_sys_txt_access_denied');
    }

    /**
     * @return CHECK_ACTION_RESULT_ALLOWED if access is granted or error message if access is forbidden. 
     */
    function isAllowedDelete (&$aDataEntry, $isPerformAction = false) {
        // moderator always has access
        if ($this->_checkModeratorAccess($isPerformAction))
            return CHECK_ACTION_RESULT_ALLOWED;

        // check ACL
        $aCheck = checkActionModule($this->_iProfileId, 'delete note', $this->getName(), $isPerformAction);
        if ($aDataEntry[BxNotesConfig::$FIELD_AUTHOR] == $this->_iProfileId && $aCheck[CHECK_ACTION_RESULT] === CHECK_ACTION_RESULT_ALLOWED)
            return CHECK_ACTION_RESULT_ALLOWED;

        return _t('_sys_txt_access_denied');
    }

    /**
     * @return CHECK_ACTION_RESULT_ALLOWED if access is granted or error message if access is forbidden. 
     */
    function isAllowedSetThumb () {
        // check ACL
        $aCheck = checkActionModule($this->_iProfileId, 'set thumb', $this->getName(), false);
        if ($aCheck[CHECK_ACTION_RESULT] !== CHECK_ACTION_RESULT_ALLOWED)
            return $aCheck[CHECK_ACTION_MESSAGE];
        return CHECK_ACTION_RESULT_ALLOWED;
    }

}

/** @} */ 
