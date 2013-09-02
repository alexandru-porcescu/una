<?php
/**
 * @package     Dolphin Core
 * @copyright   Copyright (c) BoonEx Pty Limited - http://www.boonex.com/
 * @license     CC-BY - http://creativecommons.org/licenses/by/3.0/
 */
defined('BX_DOL') or die('hack attempt');

bx_import('BxDolCmts');
bx_import('BxDolProfile');
bx_import('BxTemplPaginate');

/**
 * @see BxDolCmts
 */
class BxBaseCmtsView extends BxDolCmts {
	var $_sJsObjName;
    var $_sStylePrefix;

    function BxBaseCmtsView( $sSystem, $iId, $iInit = 1 ) {
        BxDolCmts::BxDolCmts( $sSystem, $iId, $iInit );
        if(empty($sSystem))
            return;

        $this->_sJsObjName = 'oCmts' . ucfirst($sSystem) . $iId;
        $this->_sStylePrefix = isset($this->_aSystem['root_style_prefix']) ? $this->_aSystem['root_style_prefix'] : 'cmt';

        BxDolTemplate::getInstance()->addJsTranslation('_sys_txt_cmt_loading');
    }

    /**
     * get full comments block with initializations
     */
    function getCommentsBlock($iParentId = 0) {
    	$aBp = array('parent_id' => $iParentId);

    	$sCmts = $this->getComments($aBp);

    	return BxDolTemplate::getInstance()->parseHtmlByName('comments_block.html', array(
    		'system' => $this->_sSystem,
    		'id' => $this->getId(),
    		'bx_if:show_empty' => array(
				'condition' => $sCmts == '',
				'content' => array(
					'style_prefix' => $this->_sStylePrefix
				)
			),
			'controls' => $this->_getControlsBox(),
    		'comments' => $sCmts,
    		'post_form_top' => $this->_getPostReplyBox($aBp, array('type' => $this->_sDisplayType, 'position' => BX_CMT_PFP_TOP)),
			'post_form_bottom'  => $this->_getPostReplyBox($aBp, array('type' => $this->_sDisplayType, 'position' => BX_CMT_PFP_BOTTOM)),
    		'script' => $this->getCmtsInit()
    	));
    }

    /**
     * get comments list for specified parent comment
     *
     * @param array $aBp - browse params array
     * @param array $aDp - display params array
     * 
     */
    function getComments($aBp = array(), $aDp = array())
    {
    	$this->_prepareParams($aBp, $aDp);

		$aCmts = $this->getCommentsArray($aBp['parent_id'], $aBp['order'], $aBp['start'], $aBp['per_view']);
		if(empty($aCmts) || !is_array($aCmts))
			return '';

		$sCmts = '';
		foreach($aCmts as $k => $r)
			$sCmts .= $this->getComment($r, $aDp);

		$sCmts = $this->_getMoreLink($sCmts, $aBp, $aDp);
		return $sCmts;
    }

    /**
     * get comment view block with initializations
     */
    function getCommentBlock($iCmtId = 0)
    {
    	return BxDolTemplate::getInstance()->parseHtmlByName('comment_block.html', array(
    		'system' => $this->_sSystem,
    		'id' => $this->getId(),
    		'comment' => $this->getComment($iCmtId, array('type' => BX_CMT_DISPLAY_THREADED, 'opened' => true)),
    		'script' => $this->getCmtsInit()
    	));
    }

    /**
     * get one just posted comment
     *
     * @param int $iCmtId - comment id
     * @return string
     */
    function getComment($mixedCmt, $aDp = array())
    {
    	$oTemplate = BxDolTemplate::getInstance();

    	$iUserId = $this->_getAuthorId();
    	$r = !is_array($mixedCmt) ? $this->getCommentRow((int)$mixedCmt) : $mixedCmt;

        list($sAuthorName, $sAuthorLink, $sAuthorIcon) = $this->_getAuthorInfo($r);

        $sClass = $sRet = '';
        if($r['cmt_rated'] == -1 || $r['cmt_rate'] < $this->_aSystem['viewing_threshold']) {
        	$oTemplate->pareseHtmlByName('comment_hidden.html', array(
        		'js_object' => $this->_sJsObjName,
        		'id' => $r['cmt_id'],
        		'title' => bx_process_output(_t('_hidden_comment', $sAuthorName)),
        		'bx_if:show_replies' => array(
        			'condition' => $r['cmt_replies'] > 0,
        			'content' => array(
						'replies' => _t('_Show N replies', $r['cmt_replies'])
        			)
        		)
        	));

            $sClass = ' cmt-hidden';
        }

		if($r['cmt_author_id'] == $iUserId)
			$sClass .= ' cmt-mine';

		$sActions = $this->_getActionsBox($r, $aDp);

		$sText = bx_process_output($r['cmt_text'], BX_DATA_TEXT_MULTILINE);
		$sTextMore = '';

		$iMaxLength = (int)$this->_aSystem['chars_display_max'];
		if(strlen($sText) > $iMaxLength) {
			$iLength = strpos($sText, ' ', $iMaxLength);
			
			$sTextMore = substr($sText, $iLength);
			$sText = substr($sText, 0, $iLength);
		}

		$aTmplReplyTo = array();
		if((int)$r['cmt_parent_id'] != 0) {
			$aParent = $this->getCommentRow($r['cmt_parent_id']);
			list($sParAuthorName, $sParAuthorLink, $sParAuthorIcon) = $this->_getAuthorInfo($aParent);

			$aTmplReplyTo = array(
				'style_prefix' => $this->_sStylePrefix,
        		'par_cmt_link' => $this->_sHomeUrl. '#' . $this->_sSystem . $r['cmt_parent_id'],
        		'par_cmt_author' => $sParAuthorName
        	);
		}
 
		$sReplies = '';
		if((int)$r['cmt_replies'] > 0 && !empty($aDp) && $aDp['type'] == BX_CMT_DISPLAY_THREADED && $aDp['opened'])
			$sReplies = $this->getComments(array('parent_id' => $r['cmt_id']));

        return $oTemplate->parseHtmlByName('comment.html', array(
        	'system' => $this->_sSystem,
        	'style_prefix' => $this->_sStylePrefix,
        	'id' => $r['cmt_id'],
        	'class' => $sClass,
        	'margin' => $this->_getLevelGap($r, $aDp),
        	'author_icon' => $sAuthorIcon,
        	'bx_if:show_author_link' => array(
        		'condition' => !empty($sAuthorLink),
        		'content' => array(
        			'author_link' => $sAuthorLink,
        			'author_name' => $sAuthorName
        		)
        	),
        	'bx_if:show_author_text' => array(
        		'condition' => empty($sAuthorLink),
        		'content' => array(
        			'author_name' => $sAuthorName
        		)
        	),
        	'bx_if:show_reply_to' => array(
        		'condition' => !empty($aTmplReplyTo),
        		'content' => $aTmplReplyTo
        	),
        	'text' => $sText,
        	'bx_if:show_more' => array(
        		'condition' => !empty($sTextMore),
        		'content' => array(
        			'js_object' => $this->_sJsObjName,
        			'text_more' => $sTextMore
        		)
        	),
        	'actions' => $sActions,
        	'replies' =>  $sReplies
        ));
    }

    function getFormBox($aBp = array(), $aDp = array())
    {
        return $this->_getPostReplyBox($aBp, $aDp);
    }

	function getForm($iCmtParentId, $sText, $sFunction)
	{
        return $this->_getPostReplyForm($iCmtParentId, $sText, $sFunction);
    }

    /**
     * Get comments css file string
     *
     * @return string
     */
    function getExtraCss ()
    {
        BxDolTemplate::getInstance()->addCss('cmts.css');
    }

    /**
     * Get comments js file string
     *
     * @return string
     */
    function getExtraJs ()
    {
        BxDolTemplate::getInstance()->addJs(array('common_anim.js', 'BxDolCmts.js'));
    }

    /**
     * Get initialization section of comments box
     *
     * @return string
     */
    function getCmtsInit()
    {
        $sToggleAdd = '';

        $ret = '';
        $ret .= $sToggleAdd . "
            <script  type=\"text/javascript\">
                var " . $this->_sJsObjName . " = new BxDolCmts({
                    sObjName: '" . $this->_sJsObjName . "',
                    sBaseUrl: '" . BX_DOL_URL_ROOT . "',
                    sSystem: '" . $this->getSystemName() . "',
                    sSystemTable: '" . $this->_aSystem['table_cmts'] . "',
                    iAuthorId: '" . $this->_getAuthorId() . "',
                    iObjId: '" . $this->getId () . "',
                    sOrder: '" . $this->getOrder() . "',
                    sPostFormPosition: '" . $this->_aSystem['post_form_position'] . "',
    				sBrowseType: '" . $this->_sBrowseType . "',
    				sDisplayType: '" . $this->_sDisplayType . "'});
                " . $this->_sJsObjName . ".oCmtElements = {";
        for (reset($this->_aCmtElements); list($k,$r) = each ($this->_aCmtElements); ) {
            $ret .= "\n'$k' : { 'reg' : '{$r['reg']}', 'msg' : \"". bx_js_string(trim($r['msg'])) . "\" },";
        }
        $ret = substr($ret, 0, -1);
        $ret .= "\n};\n";
        $ret .= "</script>";

        $this->getExtraJs();
        $this->getExtraCss();
        BxDolTemplate::getInstance()->addJsTranslation(array(
        	'_Error occured',
        	'_Are you sure?'
        ));
        
        return $ret;
    }

    /**
     * private functions
     */
    function _getControlsBox()
    {
    	$oTemplate = BxDolTemplate::getInstance();

    	$sDisplay = '';
    	$bDisplay = (int)$this->_aSystem['is_display_switch'] == 1;
    	if($bDisplay) {
			$aDisplayLinks = array(
				array('id' => $this->_sSystem . '-flat', 'name' => $this->_sSystem . '-flat', 'class' => '', 'title' => '_cmt_display_flat', 'target' => '_self', 'onclick' => 'javascript:' . $this->_sJsObjName . '.cmtChangeDisplay(this, \'flat\');'),
				array('id' => $this->_sSystem . '-threaded', 'name' => $this->_sSystem . '-threaded', 'class' => '', 'title' => '_cmt_display_threaded', 'target' => '_self', 'onclick' => 'javascript:' . $this->_sJsObjName . '.cmtChangeDisplay(this, \'threaded\');')
			);

    		bx_import('BxTemplMenuInteractive');
			$oMenu = new BxTemplMenuInteractive(array('template' => 'menu_interactive.html', 'menu_id'=> $this->_sSystem . '-display', 'menu_items' => $aDisplayLinks));
			$oMenu->setSelected('', $this->_sSystem . '-' . $this->_sDisplayType);
        	$sDisplay = $oMenu->getCode();
    	}

    	$sBrowse = '';
    	$bBrowse = (int)$this->_aSystem['is_browse_switch'] == 1;
    	if($bBrowse) {
    		$aBrowseLinks = array(
    			array('id' => $this->_sSystem . '-tail', 'name' => $this->_sSystem . '-tail', 'class' => '', 'title' => '_cmt_browse_tail', 'target' => '_self', 'onclick' => 'javascript:' . $this->_sJsObjName . '.cmtChangeBrowse(this, \'tail\');'),
				array('id' => $this->_sSystem . '-head', 'name' => $this->_sSystem . '-head', 'class' => '', 'title' => '_cmt_browse_head', 'target' => '_self', 'onclick' => 'javascript:' . $this->_sJsObjName . '.cmtChangeBrowse(this, \'head\');'),
				array('id' => $this->_sSystem . '-popular', 'name' => $this->_sSystem . '-popular', 'class' => '', 'title' => '_cmt_browse_popular', 'target' => '_self', 'onclick' => 'javascript:' . $this->_sJsObjName . '.cmtChangeBrowse(this, \'popular\');'),
				array('id' => $this->_sSystem . '-connection', 'name' => $this->_sSystem . '-connection', 'class' => '', 'title' => '_cmt_browse_connection', 'target' => '_self', 'onclick' => 'javascript:' . $this->_sJsObjName . '.cmtChangeBrowse(this, \'connection\');')
    		);

    		bx_import('BxTemplMenuInteractive');
			$oMenu = new BxTemplMenuInteractive(array('template' => 'menu_interactive.html', 'menu_id'=> $this->_sSystem . '-browse', 'menu_items' => $aBrowseLinks));
			$oMenu->setSelected('', $this->_sSystem . '-' . $this->_sBrowseType);
        	$sBrowse = $oMenu->getCode();
    	}
    	
    	return $oTemplate->parseHtmlByName('comments_controls.html', array(
			'js_object' => $this->_sJsObjName,
			'style_prefix' => $this->_sStylePrefix,
			'comments_count' => _t('_N_comments', $this->_oQuery->getCommentsCount($this->_iId)),
    		'display_switcher' => $bDisplay ? $sDisplay : '',
    		'bx_if:is_divider' => array(
    			'condition' => $bDisplay && $bBrowse,
    			'content' => array(
    				'style_prefix' => $this->_sStylePrefix,
    			)
    		),
    		'browse_switcher' => $bBrowse ? $sBrowse : '',
		));
    }

	function _getActionsBox(&$a, $aDp = array())
    {
        $iUserId = $this->_getAuthorId();
        $isEditAllowedPermanently = ($a['cmt_author_id'] == $iUserId && $this->isEditAllowed()) || $this->isEditAllowedAll();
        $isRemoveAllowedPermanently = ($a['cmt_author_id'] == $iUserId && $this->isRemoveAllowed()) || $this->isRemoveAllowedAll();

        return BxDolTemplate::getInstance()->parseHtmlByName('comment_actions.html', array(
        	'id' => $a['cmt_id'],
        	'style_prefix' => $this->_sStylePrefix,
        	'view_link' => bx_append_url_params($this->_sViewUrl, array(
        		'sys' => $this->_sSystem,
        		'id' => $this->_iId,
        		'cmt_id' => $a['cmt_id']
        	)),
        	'ago' => $a['cmt_ago'],
        	'points' => _t($a['cmt_rate'] == 1 || $a['cmt_rate'] == -1 ? '_N_point' : '_N_points', $a['cmt_rate']),
        	'bx_if:show_reply' => array(
				'condition' => $this->isPostReplyAllowed(),
        		'content' => array(
        			'js_object' => $this->_sJsObjName,
        			'style_prefix' => $this->_sStylePrefix,
        			'id' => $a['cmt_id'],
        			'text' => _t(isset($a['cmt_type']) && $a['cmt_type'] == 'comment' ? '_Comment_to_this_comment' : '_Reply_to_this_comment')
        		)
        	),
        	'bx_if:show_rate' => array(
				'condition' => $this->isRatable(),
        		'content' => array(
        			'js_object' => $this->_sJsObjName,
        			'style_prefix' => $this->_sStylePrefix,
        			'id' => $a['cmt_id']
        		)
        	),
        	'bx_if:show_replies' => array(
				'condition' => (int)$a['cmt_replies'] > 0 && !empty($aDp) && $aDp['type'] == BX_CMT_DISPLAY_THREADED && !$aDp['opened'],
        		'content' => array(
        			'js_object' => $this->_sJsObjName,
        			'style_prefix' => $this->_sStylePrefix,
        			'id' => $a['cmt_id'],
        			'text' => _t((isset($a['cmt_type']) && $a['cmt_type'] == 'comment' ? '_N_comments' : '_N_replies'), $a['cmt_replies'])
        		)
        	),
        	'bx_if:show_edit' => array(
				'condition' => $isEditAllowedPermanently,
        		'content' => array(
        			'js_object' => $this->_sJsObjName,
        			'style_prefix' => $this->_sStylePrefix,
        			'id' => $a['cmt_id']
        		)
        	),
        	'bx_if:show_delete' => array(
				'condition' => $isRemoveAllowedPermanently,
        		'content' => array(
        			'js_object' => $this->_sJsObjName,
        			'style_prefix' => $this->_sStylePrefix,
        			'id' => $a['cmt_id']
        		)
        	),
        ));
    }

    function _getPostReplyBox($aBp, $aDp)
    {
    	$iCmtParentId = isset($aBp['parent_id']) ? (int)$aBp['parent_id'] : 0;
    	$sPosition = isset($aDp['position']) ? $aDp['position'] : '';

    	if(!$this->isPostReplyAllowed())
    		return '';

    	bx_import('BxDolProfile');
		$oProfile = BxDolProfile::getInstanceAccountProfile($this->_getAuthorId());

		$sPositionSystem = $this->_aSystem['post_form_position'];
		if(!empty($sPosition) && $sPositionSystem != BX_CMT_PFP_BOTH && $sPositionSystem != $sPosition)
			return '';

    	return BxDolTemplate::getInstance()->parseHtmlByName('comment_reply_box.html', array(
    		'style_prefix' => $this->_sStylePrefix,
    		'margin' => $this->_getLevelGapByParent($iCmtParentId, $aDp),
    		'bx_if:show_class' => array(
    			'condition' => !empty($sPosition),
    			'content' => array(
    				'class' => $this->_sStylePrefix . '-reply-' . $sPosition
    			)
    		),
    		'author_icon' => $oProfile->getThumb(),
			'form' => $this->_getPostReplyForm($iCmtParentId)
    	));
    }

    function _getPostReplyForm($iCmtParentId = 0, $sText = "", $sFunction = "cmtSubmit(this)")
    {
    	return BxDolTemplate::getInstance()->parseHtmlByName('comment_reply_form.html', array(
    		'js_object' => $this->_sJsObjName,
    		'js_function' => $sFunction,
    		'parent_id' => $iCmtParentId,
    		'text' => bx_process_output($sText)
    	));
    }

	function _getMoreLink($sCmts, $aBp = array(), $aDp = array())
    {
    	$iStart = $iPerView = 0;
    	switch($aBp['type']) {
    		case BX_CMT_BROWSE_HEAD:
    			$iPerView = $aBp['per_view'];

    			$iStart = $aBp['start'] + $iPerView;
    			if($iStart >= $aBp['count'])
    				return $sCmts;

    			break;

    		case BX_CMT_BROWSE_TAIL:
    			$iPerView = $aBp['per_view'];

				$iStart = $aBp['start'] - $iPerView;
    			if($iStart < 0) {
    				$iPerView += $iStart;
    				$iStart = 0;
    			}

    			if($iStart == 0 && $iPerView == 0)
					return $sCmts;

    			break;
    	}

    	$sMore = BxDolTemplate::getInstance()->parseHtmlByName('comment_more.html', array(
			'js_object' => $this->_sJsObjName,
			'style_prefix' => $this->_sStylePrefix,
    		'margin' => $this->_getLevelGapByParent($aBp['parent_id'], $aDp),
			'parent_id' => $aBp['parent_id'],
    		'start' => $iStart,
    		'per_view' => $iPerView
		));

    	switch($aBp['type']) {
    		case BX_CMT_BROWSE_HEAD:
    			$sCmts .= $sMore;
    			break;

    		case BX_CMT_BROWSE_TAIL:
				$sCmts = $sMore . $sCmts;
    			break;
    	}

    	return $sCmts;
    }

    function _getLevelGap($a, $aDp = array())
    {
    	if($aDp['type'] != BX_CMT_DISPLAY_THREADED || !is_array($a) || !isset($a['cmt_level']))
    		return 0;

    	return 84 * ((int)$a['cmt_level'] <= $this->_iDpMaxLevel ? (int)$a['cmt_level'] : $this->_iDpMaxLevel);
    }

    function _getLevelGapByParent($iParentId, $aDp = array()) {
		if($aDp['type'] != BX_CMT_DISPLAY_THREADED || (int)$iParentId == 0)
    		return 0;

		$a = $this->getCommentRow($iParentId);
		if(isset($a['cmt_level']))
			$a['cmt_level'] += 1;

		return $this->_getLevelGap($a, $aDp);
    }

	function _getAuthorInfo($r)
    {
    	$sAuthorName = _t('_Anonymous');
    	$sAuthorLink = ''; 
        $sAuthorIcon = '';

		if((int)$r['cmt_author_id'] != 0) {
			$oProfile = BxDolProfile::getInstanceAccountProfile($r['cmt_author_id']);

			$sAuthorName = $oProfile->getDisplayName();
			$sAuthorLink = $oProfile->getUrl();
			$sAuthorIcon = $oProfile->getThumb();
		}

		return array($sAuthorName, $sAuthorLink, $sAuthorIcon);
    }

	function _prepareParams(&$aBp, &$aDp)
    {
    	$aBp['type'] = isset($aBp['type']) && !empty($aBp['type']) ? $aBp['type'] : $this->_sBrowseType;
    	$aBp['parent_id'] = isset($aBp['parent_id']) ? $aBp['parent_id'] : 0;
    	$aBp['start'] = isset($aBp['start']) ? $aBp['start'] : -1;
    	$aBp['per_view'] = isset($aBp['per_view']) ? $aBp['per_view'] : -1;
    	$aBp['order'] = isset($aBp['order']) ? $aBp['order'] : $this->_sOrder;

    	$aDp['type'] = isset($aDp['type']) && !empty($aDp['type']) ? $aDp['type'] : $this->_sDisplayType;
    	$aDp['opened'] = isset($aDp['opened']) ? $aDp['opened'] : $this->_bDpOpened;

		switch($aDp['type']) {
			case BX_CMT_DISPLAY_FLAT:
				$aBp['parent_id'] = -1;
				$aBp['per_view'] = $aBp['per_view'] != -1 ? $aBp['per_view'] : $this->getPerView(0);
				break;

			case BX_CMT_DISPLAY_THREADED:
				$aBp['per_view'] = $aBp['per_view'] != -1 ? $aBp['per_view'] : $this->getPerView($aBp['parent_id']);
				break;
		}

		$aBp['count'] = $this->_oQuery->getCommentsCount($this->_iId, $aBp['parent_id']);
		if($aBp['start'] != -1)
			return;

		$aBp['start'] = 0;
		if($aBp['type'] == BX_CMT_BROWSE_TAIL) {
			$aBp['start'] = $aBp['count'] - $aBp['per_view'];
			if($aBp['start'] < 0) {
    			$aBp['per_view'] += $aBp['start'];
    			$aBp['start'] = 0;
			}
		}
    }
}
